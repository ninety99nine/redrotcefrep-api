<?php

namespace App\Repositories;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Store;
use App\Models\Order;
use App\Jobs\SendSms;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\View\View;
use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Enums\OrderStatus;
use App\Models\Transaction;
use App\Models\OrderProduct;
use App\Models\PaymentMethod;
use App\Traits\Base\BaseTrait;
use App\Models\DeliveryAddress;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\AWS\AWSService;
use App\Enums\OrderPaymentStatus;
use Illuminate\Support\Facades\DB;
use App\Models\MobileVerification;
use App\Traits\MessageCrafterTrait;
use App\Enums\PaymentMethodCategory;
use App\Enums\TransactionFailureType;
use App\Http\Resources\UserResources;
use App\Enums\OrderCancellationReason;
use App\Services\QrCode\QrCodeService;
use App\Http\Resources\OrderResources;
use App\Enums\TransactionPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TransactionVerificationType;
use App\Notifications\Orders\OrderCreated;
use App\Notifications\Orders\OrderUpdated;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;
use App\Http\Resources\PaymentMethodResources;
use Illuminate\Validation\ValidationException;
use App\Notifications\Orders\OrderMarkedAsPaid;
use App\Notifications\Orders\OrderStatusUpdated;
use App\Services\PhoneNumber\PhoneNumberService;
use App\Notifications\Orders\OrderPaymentRequest;
use App\Services\CodeGenerator\CodeGeneratorService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\Billing\OrangeMoney\OrangeMoneyService;
use \Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Services\Billing\DirectPayOnline\DirectPayOnlineService;

class OrderRepository extends BaseRepository
{
    use AuthTrait, BaseTrait, MessageCrafterTrait;

    /**
     * Show orders.
     *
     * @param array $data
     * @return array|BinaryFileResponse|OrderResources
     */
    public function showOrders(array $data = []): array|BinaryFileResponse|OrderResources
    {
        if($this->getQuery() == null) {

            $userId = isset($data['user_id']) ? $data['user_id'] : null;
            $storeId = isset($data['store_id']) ? $data['store_id'] : null;
            $customerId = isset($data['customer_id']) ? $data['customer_id'] : null;
            $placedByUserId = isset($data['placed_by_user_id']) ? $data['placed_by_user_id'] : null;
            $createdByUserId = isset($data['created_by_user_id']) ? $data['created_by_user_id'] : null;
            $assignedToUserId = isset($data['assigned_to_user_id']) ? $data['assigned_to_user_id'] : null;

            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {

                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show orders'];
                $this->setQuery(Order::query()->when(!request()->has('_sort'), fn($query) => $query->latest()));

            }else if($storeId) {

                $store = Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreTeamMember($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show orders'];
                    $this->setQuery($store->orders()->when(!request()->has('_sort'), fn($query) => $query->latest()));
                }else{
                    return ['message' => 'This store does not exist'];
                }

            }else if($customerId) {
                $customer = Customer::with(['store'])->find($customerId);
                if($customer) {
                    $store = $customer->store;
                    if($store) {
                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreTeamMember($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show orders'];
                        $this->setQuery($customer->orders()->when(!request()->has('_sort'), fn($query) => $query->latest()));
                    }else{
                        return ['message' => 'This store does not exist'];
                    }
                }else{
                    return ['message' => 'This customer does not exist'];
                }
            }else{

                $specifiedUserId = $userId ?? $placedByUserId ?? $createdByUserId ?? $assignedToUserId;
                $user = in_array($specifiedUserId, [request()->current_user->id, null]) ? request()->current_user : User::find($specifiedUserId);

                if($user) {
                    $isAuthourized = $this->isAuthourized() || $user->id == request()->auth_user->id;
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show orders'];
                }else{
                    return ['message' => 'This user does not exist'];
                }

                if($association == Association::TEAM_MEMBER) {
                    $this->setQuery(Order::whereHas('store.teamMembersWhoJoined', function ($query) use ($user) {
                        $query->where('user_store_association.user_id', $user->id);
                    })->when(!request()->has('_sort'), fn($query) => $query->latest()));
                }else if($createdByUserId) {
                    $this->setQuery($user->createdOrders()->when(!request()->has('_sort'), fn($query) => $query->latest()));
                }else if($assignedToUserId) {
                    $this->setQuery($user->assignedOrders()->when(!request()->has('_sort'), fn($query) => $query->latest()));
                }else{
                    $this->setQuery($user->placedOrders()->when(!request()->has('_sort'), fn($query) => $query->latest()));
                }

            }

        }

        return $this->getOutput();
    }

    /**
     * Create order.
     *
     * @param array $data
     * @return Order|array
     */
    public function createOrder(array $data): Order|array
    {
        $storeId = $data['store_id'];
        $store = Store::find($storeId);

        $shoppingCartInstance = $this->getShoppingCartService()->startInspection($store);
        $inspectedShoppingCart = $shoppingCartInstance->getShoppingCart();

        $totalOrderProducts = $inspectedShoppingCart['totals_summary']['order_products']['total_products'];
        if($totalOrderProducts == 0) return ['created' => false, 'message' => 'The shopping cart does not have products to place an order'];

        $customer = isset($data['customer']) ? $this->updateOrCreateCustomer($store, $data['customer']) : null;
        $uncreatedOrder = (new Order)->setRelations(['store' => $store, 'customer' => $customer]);
        $orderPayload = $this->prepareOrderPayload($uncreatedOrder, $data, $inspectedShoppingCart);

        $order = Order::create($orderPayload);
        $orderProducts = $this->syncOrderProducts($order, $inspectedShoppingCart);
        $orderPromotions = $this->syncOrderPromotions($order, $inspectedShoppingCart);
        $this->addOrderHistoryComment($order, 'Order created on '.Carbon::parse($order->created_at)->format('d M Y @ H:i'));
        $order->setRelations(['store' => $store, 'customer' => $customer, 'orderProducts' => $orderProducts, 'orderPromotions' => $orderPromotions]);

        $this->updateCustomerStatistics($order);
        $deliveryAddress = $this->addDeliveryAddress($order, $data);
        if($customer && $deliveryAddress) $this->createCustomerAddress($customer, $deliveryAddress);

        $this->generateOrderSummary($order);
        //  $this->sendOrderCreatedNotifications($order);
        $shoppingCartInstance->forgetCache($store);

        if(!$this->checkIfHasRelationOnRequest('customer')) $order->unsetRelation('customer');
        if(!$this->checkIfHasRelationOnRequest('occasion')) $order->unsetRelation('occasion');
        if(!$this->checkIfHasRelationOnRequest('store')) $order->unsetRelation('store');

        return $this->showCreatedResource($order);
    }

    /**
     * Update orders.
     *
     * @param array $data
     * @return array
     */
    public function updateOrders(array $data): array
    {
        $storeId = $data['store_id'] ?? null;

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update orders'];
            $this->setQuery(Order::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update orders'];
                $this->setQuery($store->orders());
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

        }

        $orderIds = $data['order_ids'];
        $totalOrders = count($orderIds);
        $fillableFields = (new Order())->getFillable();
        $fillableData = array_intersect_key($data, array_flip($fillableFields));

        $this->queryOrdersByIds($orderIds)->update($fillableData);
        return ['updated' => true, 'message' => $totalOrders . ($totalOrders == 1 ? ' order': ' orders') . ' updated'];
    }

    /**
     * Delete orders.
     *
     * @param array $data
     * @return array
     */
    public function deleteOrders(array $data): array
    {
        $storeId = $data['store_id'] ?? null;

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete orders'];
            $this->setQuery(Order::query());
        }else{

            $store = Store::find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete orders'];
                $this->setQuery($store->orders());
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

        }

        $orderIds = $data['order_ids'];
        $orders = $this->getOrdersByIds($orderIds);

        if($totalOrders = $orders->count()) {

            foreach($orders as $order) {
                $order->delete();
            }

            return ['deleted' => true, 'message' => $totalOrders . ($totalOrders == 1 ? ' order': ' orders') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No orders deleted'];
        }
    }

    /**
     * Downlaod orders.
     *
     * @param array $data
     * @return array|StreamedResponse
     */
    public function downloadOrders(array $data): array|StreamedResponse
    {
        $storeId = $data['store_id'] ?? null;

        if(is_null($storeId)) {
            if(!$this->isAuthourized()) return ['downloaded' => false, 'message' => 'You do not have permission to download orders'];
            $this->setQuery(Order::query());
        }else{

            $store = Store::with(['logo'])->find($storeId);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['downloaded' => false, 'message' => 'You do not have permission to download orders'];
                $this->setQuery($store->orders());
            }else{
                return ['downloaded' => false, 'message' => 'This store does not exist'];
            }

        }

        $orderIds = $data['order_ids'];
        $orders = $this->queryOrdersByIds($orderIds)->with(['orderProducts', 'orderPromotions'])->get();

        if($totalOrders = $orders->count()) {

            // Convert objects to arrays
            $store = json_decode(json_encode($store), true);
            $orders = $orders->map(function ($order) {
                return json_decode(json_encode($order), true);
            })->toArray();

            // Generate the PDF
            $pdf = Pdf::loadView('pdfs.order.invoice', compact('store', 'orders'));

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->stream();
            }, 'name.pdf');

        }else{
            return ['downloaded' => false, 'message' => 'No orders to download'];
        }
    }

    /**
     * Show order status counts.
     *
     * @param array $data
     * @return array
     */
    public function showOrderStatusCounts(array $data): array
    {
        $storeId = $data['store_id'];
        $placedByUserId = $data['placed_by_user_id'];
        $store = Store::find($storeId);

        if($storeId) {
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order resource totals'];
            }else{
                if(!$this->isAuthourized()) return ['message' => 'This store does not exist'];
            }
        }else{
            if(!$this->isAuthourized()) return ['You do not have permission to show order resource totals'];
        }

        $query = DB::table('orders')->selectRaw('
            COUNT(*) as total_orders,
            CAST(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as waiting_count,
            CAST(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as cancelled_count,
            CAST(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as completed_count,
            CAST(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as on_its_way_count,
            CAST(SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as ready_for_pickup_count,
            CAST(SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as paid_count,
            CAST(SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as unpaid_count,
            CAST(SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as pending_count,
            CAST(SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) AS UNSIGNED) as partially_paid_count
        ', [
            OrderStatus::WAITING->value,
            OrderStatus::CANCELLED->value,
            OrderStatus::COMPLETED->value,
            OrderStatus::ON_ITS_WAY->value,
            OrderStatus::READY_FOR_PICKUP->value,
            OrderPaymentStatus::PAID->value,
            OrderPaymentStatus::UNPAID->value,
            OrderPaymentStatus::PENDING_PAYMENT->value,
            OrderPaymentStatus::PARTIALLY_PAID->value,
        ]);

        if($store) $query->where('store_id', $storeId);
        if($placedByUserId) $query->where('placed_by_user_id', $placedByUserId);

        $result = $query->first();

        return [
            'total_orders' => $result->total_orders,
            'status_counts' => [
                'waiting' => $result->waiting_count,
                'completed' => $result->completed_count,
                'on_its_way' => $result->on_its_way_count,
                'ready_for_pickup' => $result->ready_for_pickup_count,
                'cancelled' => $result->cancelled_count,
            ],
            'payment_status_counts' => [
                'paid' => $result->paid_count,
                'unpaid' => $result->unpaid_count,
                'pending' => $result->pending_count,
                'partially_paid' => $result->partially_paid_count,
            ]
        ];

    }

    /**
     * Show order.
     *
     * @param string $orderId
     * @return Order|array|null
     */
    public function showOrder(string $orderId): Order|array|null
    {
        $order = $this->setQuery(Order::whereId($orderId))->applyEagerLoadingOnQuery()->getQuery()->first();

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order'];
                if(!$this->checkIfHasRelationOnRequest('store')) $order->unsetRelation('store');
            }else{
                return ['message' => 'This store does not exist'];
            }
        }

        return $this->showResourceExistence($order);
    }

    /**
     * Update order.
     *
     * @param string $orderId
     * @param array $data
     * @return Order|array
     */
    public function updateOrder(string $orderId, array $data): Order|array
    {
        $oldOrder = Order::with(['store', 'cart', 'customer', 'deliveryAddress'])->find($orderId);

        if($oldOrder) {
            $store = $oldOrder->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update order'];
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            if($this->checkIfOrderCannotBeUpdated($oldOrder)) {
                //  return ['updated' => false, 'message' => $this->orderCannotBeUpdatedReason()];
            }

            $cart = $oldOrder->cart;

            if(isset($data['cart_products']) || isset($data['cart_promotion_code'])) {
                $inspectedShoppingCart = $this->getShoppingCartService()->startInspection($store);
                if($inspectedShoppingCart->total_products == 0) return ['updated' => false, 'message' => 'The shopping cart does not have products to update this order'];

                $cart = $this->updateOrderCart($oldOrder, $inspectedShoppingCart);
            }

            $customer = isset($data['customer']) ? $this->updateOrCreateCustomer($store, $data['customer']) : $oldOrder->customer;

            $order = (new Order)->setRelations(['store' => $store, 'cart' => $cart, 'customer' => $customer]);
            $orderPayload = $this->prepareOrderPayload($order, $data, $inspectedShoppingCart);
            $order = tap(clone $oldOrder)->update($orderPayload);

            $deliveryAddress = $this->addOrUpdateDeliveryAddress($order, $data);

            $order->setRelations(['customer' => $customer]);
            $this->updateCustomerStatistics($order, $oldOrder);

            if($customer && isset($deliveryAddress)) $this->createCustomerAddress($customer, $deliveryAddress);
            $order->setRelations(['store' => $store, 'cart' => $cart->load(['orderProducts', 'orderPromotions'])]);

            $this->generateOrderSummary($order);
            //  $this->sendOrderCreatedNotifications($order);
            if(isset($inspectedShoppingCart)) $this->getShoppingCartService()->forgetCache();

            if(!$this->checkIfHasRelationOnRequest('customer')) $order->unsetRelation('customer');
            if(!$this->checkIfHasRelationOnRequest('occasion')) $order->unsetRelation('occasion');
            if(!$this->checkIfHasRelationOnRequest('store')) $order->unsetRelation('store');
            if(!$this->checkIfHasRelationOnRequest('cart')) $order->unsetRelation('cart');

            return $this->showUpdatedResource($order);
        }else{
            return ['updated' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Delete order.
     *
     * @param string $orderId
     * @return array
     */
    public function deleteOrder(string $orderId): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['deleted' => false, 'message' => 'You do not have permission to delete order'];
            }else{
                return ['deleted' => false, 'message' => 'This store does not exist'];
            }

            $deleted = $order->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Order deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Order deleted unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Show order cancellation reasons.
     *
     * @return array
     */
    public function showOrderCancellationReason(): array
    {
        return collect(Order::CANCELLATION_REASONS())->map(fn($cancellationReason) => ucfirst(strtolower($cancellationReason)))->toArray();
    }

    /**
     * Generate order collection code.
     *
     * @param string $orderId
     * @return array
     */
    public function generateOrderCollectionCode(string $orderId): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['revoked' => false, 'message' => 'You do not have permission to generate order collection code'];
            }else{
                return ['generated' => false, 'message' => 'This store does not exist'];
            }

            if($order->collection_verified) return ['generated' => false, 'Order collection already verified'];

            if($order->collection_code_expires_at && Carbon::parse($order->collection_code_expires_at)->isFuture()) {

                $data = ['collection_code_expires_at' => now()->addMinutes(5)];
                $generated = $order->update(['collection_code_expires_at' => now()->addMinutes(5)]);

            }else{

                $updateOrderStatusEndpoint = route('update.order.status', ['orderId' => $order->id]);
                $collectionCode = CodeGeneratorService::generateRandomSixDigitNumber();

                if(!empty($order->collection_qr_code) && AWSService::exists($order->collection_qr_code)) AWSService::delete($order->collection_qr_code);
                $qrCodeImageUrl = QrCodeService::generate($updateOrderStatusEndpoint.'|'.$collectionCode);

                $data = [
                    'collection_code' => $collectionCode,
                    'collection_qr_code' => $qrCodeImageUrl,
                    'collection_code_expires_at' => now()->addMinutes(5)
                ];

                $generated = $order->update($data);

                if($order->customer_mobile_number) {
                    $smsMessage = $this->craftOrderCollectionCodeMessage($order);
                    SendSms::dispatch($smsMessage, $order->customer_mobile_number->formatE164(), $store);
                }

            }

            if ($generated) {

                return [
                    'generated' => true,
                    'message' => 'Order collection code created',
                    'data' => $data['collection_code_expires_at']
                ];

            }else{
                return ['generated' => false, 'message' => 'Order collection code generation unsuccessful'];
            }
        }else{
            return ['generated' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Revoke order collection code.
     *
     * @param string $orderId
     * @return array
     */
    public function revokeOrderCollectionCode(string $orderId): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['revoked' => false, 'message' => 'You do not have permission to revoke order collection code'];
            }else{
                return ['revoked' => false, 'message' => 'This store does not exist'];
            }

            if($order->collection_verified) return ['revoked' => false, 'Order collection already verified'];
            if(!empty($order->collection_qr_code) && AWSService::exists($order->collection_qr_code)) AWSService::delete($order->collection_qr_code);

            $revoked = $order->update([
                'collection_code' => null,
                'collection_qr_code' => null,
                'collection_code_expires_at' => null
            ]);

            if ($revoked) {

                return [
                    'revoked' => true,
                    'message' => 'Order collection code revoked'
                ];

            }else{
                return ['revoked' => false, 'message' => 'Order collection code generation unsuccessful'];
            }
        }else{
            return ['revoked' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Verify order collection.
     *
     * @param Order|string $orderId
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function verifyOrderCollection(Order|string $orderId, array $data): array
    {
        $order = $orderId instanceof Order ? $orderId : Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['verified' => false, 'message' => 'You do not have permission to verify order collection'];
            }else{
                return ['verified' => false, 'message' => 'This store does not exist'];
            }

            $completed = $data['completed'] ?? false;
            $collectionCode = $data['collection_code'];
            $manuallyVerifiedByUser = $this->getAuthUser();
            $collectionNote = $data['collection_note'] ?? null;
            $customerMobileNumber = $order->customer_mobile_number;

            if($order->collection_verified) return ['verified' => false, 'message' => 'Order collection already verified'];
            if(!$customerMobileNumber) return ['verified' => false, 'message' => 'The customer mobile number is required to verify order collection'];

            $mobileVerification = MobileVerification::where('mobile_number', $customerMobileNumber->formatE164())->where('code', $collectionCode)->first();

            if($mobileVerification) {
                AuthRepository::revokeMobileVerificationCode($customerMobileNumber->formatE164());
            }else{

                if($collectionCode != $order->collection_code) {
                    throw ValidationException::withMessages(['collection_code' => 'The collection code is incorrect']);
                }else if($order->collection_code_expires_at && Carbon::parse($order->collection_code_expires_at)->isPast()) {
                    throw ValidationException::withMessages(['collection_code' => 'This collection code has expired']);
                }

            }

            if(!empty($order->collection_qr_code) && AWSService::exists($order->collection_qr_code)) AWSService::delete($order->collection_qr_code);

            $data = [
                'collection_code' => null,
                'collection_qr_code' => null,
                'collection_verified' => true,
                'collection_verified_at' => now(),
                'collection_code_expires_at' => null,
                'collection_note' => $collectionNote,
                'collection_verified_by_user_id' => $manuallyVerifiedByUser->id
            ];

            if($completed) {
                $data = array_merge($data, [
                    'cancelled_at' => null,
                    'cancellation_reason' => null,
                    'other_cancellation_reason' => null,
                    'status' => OrderStatus::COMPLETED->value,
                ]);
            }

            $order->update($data);

            return ['verified' => true, 'message' => 'Order collection verified'];

        }else{
            return ['verified' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Update order status.
     *
     * @param string $orderId
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    public function updateOrderStatus(string $orderId, array $data): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update order status'];
            }else{
                return ['updated' => false, 'message' => 'This store does not exist'];
            }

            $manuallyVerifiedByUser = $this->getAuthUser();
            $status = OrderStatus::tryFrom($data['status']);
            $hasCollectionCode = isset($data['collection_code']);
            $updateAsCompleted = $status == OrderStatus::COMPLETED;
            $updateAsCancelled = $status == OrderStatus::CANCELLED;

            if($updateAsCompleted && $hasCollectionCode) {

                $data['completed'] = true;

                $response = $this->authourize()->verifyOrderCollection($order, $data);
                $updated = $response['verified'];

                if(!$updated) {
                    $response['updated'] = $updated;
                    unset($response['verified']);
                    return $response;
                }

            } else {

                if($updateAsCancelled) {

                    if($order->is_cancelled) return ['updated' => false, 'message' => 'Order already cancelled'];
                    $cancellationReason = isset($data['cancellation_reason']) ? $this->separateWordsThenLowercase($data['cancellation_reason']) : null;
                    $otherCancellationReason = $cancellationReason == null ? null : OrderCancellationReason::tryFrom($cancellationReason);

                    $data = [
                        'cancelled_at' => now(),
                        'status' => $status->value,
                        'cancellation_reason' => $cancellationReason,
                        'other_cancellation_reason' => $otherCancellationReason?->value
                    ];

                }else{

                    $data = array_merge($data, [
                        'cancelled_at' => null,
                        'status' => $status->value,
                        'cancellation_reason' => null,
                        'other_cancellation_reason' => null
                    ]);

                }

                $updated = $order->update($data);
            }

            if ($updated) {

                $teamMembers = $store->teamMembers()->joinedTeam()->get();
                Notification::send($teamMembers, new OrderStatusUpdated($order, $this->getAuthUser()));

                if($order->customer_mobile_number) {

                    $smsMessage = $updateAsCompleted
                        ? $this->craftOrderCollectedMessage($order, $manuallyVerifiedByUser)
                        : $this->craftOrderStatusUpdatedMessage($order, $this->getAuthUser());

                    SendSms::dispatch($smsMessage, $order->customer_mobile_number->formatE164(), $store);

                }

                return $this->showUpdatedResource($order);

            }else{
                return ['updated' => false, 'message' => 'Order status update unsuccessful'];
            }
        }else{
            return ['updated' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Request order payment.
     *
     * @param string $orderId
     * @param array $data
     * @return array
     */
    public function requestOrderPayment(string $orderId, array $data): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['requested' => false, 'message' => 'You do not have permission to request order payment'];
            }else{
                return ['requested' => false, 'message' => 'This store does not exist'];
            }

            if($order->grand_total->amount == 0) return ['requested' => false, 'message' => 'This order does not have a payable amount'];
            if($order->outstanding_percentage == 0) return ['requested' => false, 'message' => 'This order does not have any outstanding amount'];
            if($order->pending_percentage == $order->outstanding_percentage) return ['requested' => false, 'message' => 'Settle pending transactions amounting to '.$order->pending_total->amountWithCurrency];

            $paymentMethodId = $data['payment_method_id'] ?? null;
            $paymentMethodType = $data['payment_method_type'] ?? null;

            if($paymentMethodId) {
                /** @var PaymentMethod|null $paymentMethod */
                $paymentMethod = $store->paymentMethods()->whereId($paymentMethodId)->first();
                if(!$paymentMethod) return ['requested' => false, 'message' => 'The specified payment method does not exist'];
            }else if($paymentMethodType) {
                /** @var PaymentMethod|null $paymentMethod */
                $paymentMethod = $store->paymentMethods()->whereType($paymentMethodType)->first();
                if(!$paymentMethod) return ['requested' => false, 'message' => 'The specified payment method does not exist'];
            }

            if(!$paymentMethod->isAutomated()) return ['requested' => false, 'message' => 'The '.$paymentMethod->name.' payment method is not an automated method of payment'];
            if(!$paymentMethod->active) return ['requested' => false, 'message' => 'The '.$paymentMethod->name.' payment method has been deactivated'];

            $transactionPayload = $this->prepareTransactionPayload($order, TransactionPaymentStatus::PENDING_PAYMENT, $paymentMethod, $data);
            $transaction = $this->getTransactionRepository()->authourize()->shouldReturnModel()->createTransaction($transactionPayload);
            $transaction->setRelation('paymentMethod', $paymentMethod);
            $transaction->setRelation('owner', $order);

            try {

                if($paymentMethod->isDpo()) {

                    $companyToken = $paymentMethod->metadata['company_token'];
                    $dpoPaymentLinkPayload = $this->prepareDpoPaymentLinkPayload($transaction);
                    $response = DirectPayOnlineService::createPaymentLink($companyToken, $dpoPaymentLinkPayload);

                    if($response['created']) {
                        $metadata = $response['data'];
                    }else{
                        return ['requested' => false, 'message' => $response['message']];
                    }

                }else if($paymentMethod->isOrangeMoney()) {
                    $transaction = OrangeMoneyService::createOrderPaymentLink($transaction);
                }

                $transaction->update(['metadata' => $metadata]);

                $this->updateOrderAmountBalance($order);

                if($order->customer_mobile_number) {

                    $user = User::searchMobileNumber($order->customer_mobile_number->formatE164())->first();
                    if($user) Notification::send($user, new OrderPaymentRequest($order, $store, $transaction));

                    $transaction->loadMissing(['requestedByUser']);
                    $smsMessage = $this->craftOrderPaymentRequestMessage($order, $transaction);
                    SendSms::dispatch($smsMessage, $order->customer_mobile_number->formatE164(), $store);

                }

                if(!$this->checkIfHasRelationOnRequest('requestedByUser')) $transaction->unsetRelation('requestedByUser');
                if(!$this->checkIfHasRelationOnRequest('paymentMethod')) $transaction->unsetRelation('paymentMethod');
                if(!$this->checkIfHasRelationOnRequest('owner')) $transaction->unsetRelation('owner');

                return $this->getTransactionRepository()->showSavedResource($transaction, 'requested');

            }catch(\Exception $e) {

                $transaction->update([
                    'failure_reason' => $e->getMessage(),
                    'payment_status' => TransactionPaymentStatus::FAILED_PAYMENT->value,
                    'failure_type' => TransactionFailureType::PAYMENT_REQUEST_FAILED->value
                ]);

                return ['requested' => false, 'message' => $e->getMessage()];
            }

        }else{
            return ['requested' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Verify order payment.
     *
     * @param string $orderId
     * @param string $transactionId
     * @return View|array
     */
    public function verifyOrderPayment(string $orderId, string $transactionId): View|array
    {
        $order = Order::find($orderId);

        if($order) {

            /** @var Transaction|null $transaction */
            $transaction = $order->transactions()->with(['paymentMethod'])->find($transactionId);
            if(!$transaction) return ['verified' => false, 'message' => 'The order transaction does not exist'];

            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = $transaction->paymentMethod;
            if(!$paymentMethod) return ['verified' => false, 'message' => 'The transaction payment method does not exist'];

            try{

                if(!$transaction->isPaid()) {

                    if($paymentMethod->isDpo()) {

                        $companyToken = $paymentMethod->metadata['company_token'];
                        $transactionToken = $transaction->metadata['dpo_transaction_token'];
                        $metadata = DirectPayOnlineService::verifyPayment($companyToken, $transactionToken);

                        $transaction->update([
                            'failure_type' => null,
                            'failure_reason' => null,
                            'payment_status' => TransactionPaymentStatus::PAID->value,
                            'metadata' => array_merge($transaction->metadata, $metadata)
                        ]);

                        $this->updateOrderAmountBalance($order);

                    }else{
                        return ['verified' => false, 'message' => 'The "'.$paymentMethod->name.'" payment method cannot be used to verify transaction payment'];
                    }

                }

                if(request()->wantsJson()) {
                    return $this->showSavedResource($transaction, 'verified');
                }else{
                    return view('payment-success', ['transaction' => $transaction]);
                }

            }catch(\Exception $e) {

                $transaction->update([
                    'failure_reason' => $e->getMessage(),
                    'payment_status' => TransactionPaymentStatus::FAILED_PAYMENT->value,
                    'failure_type' => TransactionFailureType::PAYMENT_VERIFICATION_FAILED->value
                ]);

                if(request()->wantsJson()) {
                    return ['verified' => false, 'message' => $e->getMessage()];
                }else{
                    return view('payment-failure', ['failureReason' =>  $e->getMessage(), 'transaction' => $transaction]);
                }

            }

        }else{
            return ['verified' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Show payment methods for requesting order payment.
     *
     * @param string $orderId
     * @return PaymentMethodResources|array
     */
    public function showPaymentMethodsForRequestingOrderPayment(string $orderId): PaymentMethodResources|array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {

            $store = $order->store;
            if($store) {
                $query = $store->paymentMethods()->where(['category' => PaymentMethodCategory::AUTOMATED])->latest();
                return $this->getPaymentMethodRepository()->setQuery($query)->showPaymentMethods();
            }else{
                return ['message' => 'This store does not exist'];
            }

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Mark order as paid.
     *
     * @param string $orderId
     * @param array $data
     * @return array
     */
    public function markOrderAsPaid(string $orderId, array $data): array
    {
        $order = Order::with(['store', 'cart'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['marked_as_paid' => false, 'message' => 'You do not have permission to mark order as paid'];
            }else{
                return ['marked_as_paid' => false, 'message' => 'This store does not exist'];
            }

            if($order->grand_total->amount == 0) return ['marked_as_paid' => false, 'message' => 'This order does not have a payable amount'];
            if($order->outstanding_percentage == 0) return ['marked_as_paid' => false, 'message' => 'This order does not have any outstanding amount'];
            if($order->pending_percentage == $order->outstanding_percentage) return ['requested' => false, 'message' => 'Settle pending transactions amounting to '.$order->pending_total->amountWithCurrency];

            $paymentMethodId = $data['payment_method_id'] ?? null;
            $paymentMethodType = $data['payment_method_type'] ?? null;

            if($paymentMethodId) {
                /** @var PaymentMethod|null $paymentMethod */
                $paymentMethod = PaymentMethod::whereId($paymentMethodId)->first();
                if(!$paymentMethod) return ['marked_as_paid' => false, 'message' => 'The specified payment method does not exist'];
            }else if($paymentMethodType) {
                /** @var PaymentMethod|null $paymentMethod */
                $paymentMethod = PaymentMethod::whereType($paymentMethodType)->first();
                if(!$paymentMethod) return ['marked_as_paid' => false, 'message' => 'The specified payment method does not exist'];
            }else{
                $paymentMethod = null;
            }

            if($paymentMethod && $paymentMethod->isAutomated()) return ['marked_as_paid' => false, 'message' => 'The '.$paymentMethod->name.' payment method is an automated method of payment. Select a manual payment method instead.'];
            if($paymentMethod && !$paymentMethod->active) return ['marked_as_paid' => false, 'message' => 'The '.$paymentMethod->name.' payment method has been deactivated'];

            $transactionPayload = $this->prepareTransactionPayload($order, TransactionPaymentStatus::PAID, $paymentMethod, $data);
            $transaction = $this->getTransactionRepository()->authourize()->shouldReturnModel()->createTransaction($transactionPayload);

            if($order->customer_mobile_number) {

                $teamMembers = $store->teamMembers()->joinedTeam()->get();
                if($paymentMethod) $transaction->setRelation('paymentMethod', $paymentMethod);
                $user = User::searchMobileNumber($order->customer_mobile_number->formatE164())->first();
                Notification::send(collect($teamMembers)->merge($user ? [$user] : []), new OrderMarkedAsPaid($order, $store, $transaction, $this->getAuthUser()));

                $smsMessage = $this->craftOrderMarkedAsPaidMessage($order, $transaction, $this->getAuthUser());
                SendSms::dispatch($smsMessage, $order->customer_mobile_number->formatE164(), $store);

            }

            $this->updateOrderAmountBalance($order);

            if(!$this->checkIfHasRelationOnRequest('store')) $order->unsetRelation('store');
            if(!$this->checkIfHasRelationOnRequest('cart')) $order->unsetRelation('cart');

            return $this->showSavedResource($order, 'marked as paid');

        }else{
            return ['marked_as_paid' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Mark order as unpaid.
     *
     * @param string $orderId
     * @return array
     */
    public function markOrderAsUnpaid(string $orderId): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['marked_as_unpaid' => false, 'message' => 'You do not have permission to mark order as unpaid'];
            }else{
                return ['marked_as_unpaid' => false, 'message' => 'This store does not exist'];
            }

            $order->transactions()->subjectToManualVerification()->delete();
            $this->updateOrderAmountBalance($order);

            if(!$this->checkIfHasRelationOnRequest('store')) $order->unsetRelation('store');

            return $this->showSavedResource($order, 'marked as '.$order->payment_status);

        }else{
            return ['marked_as_unpaid' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Show payment methods for marking as paid.
     *
     * @param string $orderId
     * @return PaymentMethodResources|array
     */
    public function showPaymentMethodsForMarkingAsPaid(string $orderId): PaymentMethodResources|array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {

            $store = $order->store;
            if($store) {
                $query = $store->paymentMethods()->whereIn('category', [PaymentMethodCategory::LOCAL, PaymentMethodCategory::MANUAL])->latest();
                return $this->getPaymentMethodRepository()->setQuery($query)->showPaymentMethods();
            }else{
                return ['message' => 'This store does not exist'];
            }

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order cart.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderCart(string $orderId): array
    {
        $order = Order::with(['store', 'cart'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order cart'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getCartRepository()->showResourceExistence($order->cart);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order store.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderStore(string $orderId): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order store'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getStoreRepository()->showResourceExistence($store);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order customer.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderCustomer(string $orderId): array
    {
        $order = Order::with(['store', 'customer'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order customer'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getCustomerRepository()->showResourceExistence($order->customer);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order occasion.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderOccasion(string $orderId): array
    {
        $order = Order::with(['store', 'occasion'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order occasion'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getOccasionRepository()->showResourceExistence($order->occasion);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order placed by user.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderPlacedByUser(string $orderId): array
    {
        $order = Order::with(['store', 'placedByUser'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order placed by user'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getUserRepository()->showResourceExistence($order->placedByUser);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order created by user.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderCreatedByUser(string $orderId): array
    {
        $order = Order::with(['store', 'createdByUser'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order created by user'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getUserRepository()->showResourceExistence($order->createdByUser);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order collection verified by user.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderCollectionVerifiedByUser(string $orderId): array
    {
        $order = Order::with(['store', 'collectionVerifiedByUser'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order collection verified by user'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getUserRepository()->showResourceExistence($order->collectionVerifiedByUser);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order delivery address.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderDeliveryAddress(string $orderId): array
    {
        $order = Order::with(['store', 'deliveryAddress'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order delivery address'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getDeliveryAddressRepository()->showResourceExistence($order->deliveryAddress);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order friend group.
     *
     * @param string $orderId
     * @return array
     */
    public function showOrderFriendGroup(string $orderId): array
    {
        $order = Order::with(['store', 'friendGroup'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || ($order->placed_by_user_id === $this->getAuthUser()->id) || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order friend group'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getFriendGroupRepository()->showResourceExistence($order->friendGroup);

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Add order friend group.
     *
     * @param string $orderId
     * @param string $friendGroupId
     * @return array
     */
    public function addOrderFriendGroup(string $orderId, string $friendGroupId): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || ($order->placed_by_user_id === $this->getAuthUser()->id) || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['added' => false, 'message' => 'You do not have permission to add order friend group'];
            }else{
                return ['added' => false, 'message' => 'This store does not exist'];
            }

            $friendGroup = $this->getAuthUser()->friendGroups()->where('friend_groups.id', $friendGroupId)->first();

            if($friendGroup) {
                $order->update(['friend_group_id' => $friendGroupId]);
                return $this->getFriendGroupRepository()->showSavedResource($friendGroup, 'added');
            }else{
                return ['added' => false, 'message' => 'This friend group does not exist'];
            }

        }else{
            return ['added' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Remove order friend group.
     *
     * @param string $orderId
     * @return array
     */
    public function removeOrderFriendGroup(string $orderId): array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || ($order->placed_by_user_id === $this->getAuthUser()->id) || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['added' => false, 'message' => 'You do not have permission to add order friend group'];
            }else{
                return ['removed' => false, 'message' => 'This store does not exist'];
            }

            $order->update(['friend_group_id' => null]);
            return $this->showSavedResource($order, 'removed', 'Friend group removed');

        }else{
            return ['removed' => false, 'message' => 'This order does not exist'];
        }
    }

    /**
     * Show order viewers.
     *
     * @param string $orderId
     * @return UserResources|array
     */
    public function showOrderViewers(string $orderId): UserResources|array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order viewers'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getUserRepository()->setQuery($order->viewers())->showUsers();

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /**
     * Show order transactions.
     *
     * @param string $orderId
     * @return TransactionRepository|array
     */
    public function showOrderTransactions(string $orderId): TransactionRepository|array
    {
        $order = Order::with(['store'])->find($orderId);

        if($order) {
            $store = $order->store;
            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['message' => 'You do not have permission to show order transactions'];
            }else{
                return ['message' => 'This store does not exist'];
            }

            return $this->getTransactionRepository()->setQuery($order->transactions())->showTransactions();

        }else{
            return ['message' => 'This order does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query order by ID.
     *
     * @param string $orderId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryOrderById(string $orderId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('orders.id', $orderId)->with($relationships);
    }

    /**
     * Get order by ID.
     *
     * @param string $orderId
     * @param array $relationships
     * @return Order|null
     */
    public function getOrderById(string $orderId, array $relationships = []): Order|null
    {
        return $this->queryOrderById($orderId, $relationships)->first();
    }

    /**
     * Query orders by IDs.
     *
     * @param array<string> $orderId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryOrdersByIds($orderIds): Builder|Relation
    {
        return $this->query->whereIn('orders.id', $orderIds);
    }

    /**
     * Get orders by IDs.
     *
     * @param array<string> $orderId
     * @param string $relationships
     * @return Collection
     */
    public function getOrdersByIds($orderIds): Collection
    {
        return $this->queryOrdersByIds($orderIds)->get();
    }

    /**
     * Prepare order payload.
     *
     * @param Order $order
     * @param array $data
     * @param array $inspectedShoppingCart
     * @return array
     */
    private function prepareOrderPayload(Order $order, array $data, array $inspectedShoppingCart): array
    {
        $store = $order->store;
        $customer = $order->customer;
        $isc = $inspectedShoppingCart;
        $uncreatedOrder = $order->id == null;
        $customerFirstName = $customerLastName = $customerMobileNumber = $customerEmail = null;

        if($customer) {

            $customerEmail = $customer?->email;
            $customerLastName = $customer?->last_name;
            $customerFirstName = $customer->first_name;
            $customerMobileNumber = $customer?->mobile_number?->formatE164();

        }else if(isset($data['customer'])) {

            $customerEmail = null;
            $customerMobileNumber = null;
            $customerLastName = $data['customer']['last_name'] ?? null;
            $customerFirstName = $data['customer']['first_name'] ?? null;

        }

        $createdByUserId = ($uncreatedOrder && isset($data['created_by_team']) && $this->isTruthy($data['created_by_team']) && $this->hasAuthUser() && $this->getStoreRepository()->checkIfAssociatedAsStoreTeamMember($store)) ? $this->getAuthUser()->id : $order->created_by_user_id;
        $placedByUserId = $createdByUserId ? $createdByUserId : ($order->placed_by_user_id ?? $this->getAuthUser()?->id);
        $occasionId = isset($data['occasion_id']) ? $data['occasion_id'] : $order?->occasion_id;

        return [
            'summary' => null,
            'currency' => $store->currency,
            'status' => OrderStatus::WAITING->value,
            'subtotal' => $isc['totals']['subtotal']->amount,
            'discount_total' => $isc['totals']['discount_total']->amount,
            'subtotal_after_discount' => $isc['totals']['subtotal_after_discount']->amount,
            'vat_method' => $isc['totals']['vat']['method'],
            'vat_rate' => $isc['totals']['vat']['rate']['value'],
            'vat_amount' => $isc['totals']['vat']['amount']->amount,
            'fee_total' => $isc['totals']['fee_total']->amount,
            'grand_total' => $isc['totals']['grand_total']->amount,

            'payment_status' => OrderPaymentStatus::UNPAID->value,
            'paid_total' => 0,
            'paid_percentage' => 0,
            'pending_total' => 0,
            'pending_percentage' => 0,
            'outstanding_total' => $isc['totals']['grand_total']->amount,
            'outstanding_percentage' => 100,

            'total_products' => $isc['totals_summary']['order_products']['total_products'],
            'total_cancelled_products' => $isc['totals_summary']['order_products']['total_cancelled_products'],
            'total_uncancelled_products' => $isc['totals_summary']['order_products']['total_uncancelled_products'],
            'total_product_quantities' => $isc['totals_summary']['order_products']['total_product_quantities'],
            'total_cancelled_product_quantities' => $isc['totals_summary']['order_products']['total_cancelled_product_quantities'],
            'total_uncancelled_product_quantities' => $isc['totals_summary']['order_products']['total_uncancelled_product_quantities'],

            'total_promotions' => $isc['totals_summary']['order_promotions']['total_promotions'],
            'total_cancelled_promotions' => $isc['totals_summary']['order_promotions']['total_cancelled_promotions'],
            'total_uncancelled_promotions' => $isc['totals_summary']['order_promotions']['total_uncancelled_promotions'],
            'applied_promotion_code' => isset($isc['promotion_code']['applied']) && $isc['promotion_code']['applied'] == true,

            'delivery_method_id' =>  is_null($isc['delivery']['method']) ? null : $isc['delivery']['method']['id'],
            'delivery_method_name' =>  is_null($isc['delivery']['method']) ? null : $isc['delivery']['method']['name'],

            'delivery_distance_value' => is_null($isc['delivery']['distance']) ? null : $isc['delivery']['distance']['value'],
            'delivery_distance_unit' => is_null($isc['delivery']['distance']) ? null : $isc['delivery']['distance']['unit'],
            'delivery_distance_text' => is_null($isc['delivery']['distance']) ? null : $isc['delivery']['distance']['text'],

            'delivery_duration_value' => is_null($isc['delivery']['duration']) ? null : $isc['delivery']['duration']['value'],
            'delivery_duration_text' => is_null($isc['delivery']['duration']) ? null : $isc['delivery']['duration']['text'],

            'delivery_weight_value' => is_null($isc['delivery']['weight']) ? null : $isc['delivery']['weight']['value'],
            'delivery_weight_unit' => is_null($isc['delivery']['weight']) ? null : $isc['delivery']['weight']['unit'],
            'delivery_weight_text' => is_null($isc['delivery']['weight']) ? null : $isc['delivery']['weight']['text'],

            'free_delivery' =>  $isc['delivery']['free_delivery'],

            'delivery_date' => $isc['delivery']['date'],
            'delivery_timeslot' => $isc['delivery']['timeslot'],

            'collection_code' => null,
            'collection_qr_code' => null,
            'collection_code_expires_at' => null,
            'collection_verified' => false,
            'collection_verified_at' => null,
            'collection_verified_by_user_id' => null,
            'collection_note' => null,

            'cancelled_at' => null,
            'cancellation_reason' => null,
            'other_cancellation_reason' => null,

            'customer_id' => $customer?->id,
            'customer_email' => $customerEmail,
            'customer_last_name' => $customerLastName,
            'customer_first_name' => $customerFirstName,
            'customer_mobile_number' => $customerMobileNumber,
            'customer_note' => $data['customer_note'] ?? null,

            'total_views_by_team' => 0,
            'first_viewed_by_team_at' => null,
            'last_viewed_by_team_at' => null,

            'placed_by_user_id' => $placedByUserId,
            'created_by_user_id' => $createdByUserId,

            'store_note' => $data['store_note'] ?? null,

            'store_id' => $store->id,
            'occasion_id' => $occasionId,
            'friend_group_id' => $this->getFriendGroupId($data),
        ];
    }

    /**
     * Sync order products.
     *
     * @param Order $order
     * @param array $inspectedShoppingCart
     * @return Collection
     */
    public function syncOrderProducts(Order $order, array $inspectedShoppingCart): Collection
    {
        $inserts = collect($inspectedShoppingCart['order_products'])->map(function($orderProduct) use ($order) {
            if(empty($orderProduct['detected_changes'])) $orderProduct['detected_changes'] = null;
            $orderProduct['store_id'] = $order->store_id;
            $orderProduct['order_id'] = $order->id;
            return $orderProduct;
        });

        return $order->orderProducts()->createMany($inserts);
    }

    /**
     * Sync order promotions.
     *
     * @param Order $order
     * @param array $inspectedShoppingCart
     * @return Collection
     */
    public function syncOrderPromotions(Order $order, array $inspectedShoppingCart): Collection
    {
        $inserts = collect($inspectedShoppingCart['order_promotions'])->map(function($orderPromotion) use ($order) {
            if(empty($orderPromotion['detected_changes'])) $orderPromotion['detected_changes'] = null;
            $orderPromotion['store_id'] = $order->store_id;
            $orderPromotion['order_id'] = $order->id;
            return $orderPromotion;
        });

        return $order->orderPromotions()->createMany($inserts);
    }

    /**
     * Add order history comment.
     *
     * @param Order $order
     * @param string $comment
     * @return void
     */
    public function addOrderHistoryComment(Order $order, string $comment): void
    {
        $order->orderHistory()->create([
            'comment' => $comment,
            'store_id' => $order->store_id
        ]);
    }

    /**
     * Update or create customer.
     *
     * @param Store $store
     * @param array $data
     * @return Customer|null
     */
    public function updateOrCreateCustomer(Store $store, array $data): Customer|null
    {
        $customer = $this->findCustomer($store, $data);

        if($customer) {
            if($this->shouldUpdateCustomer($customer, $data)) $customer = $this->updateCustomer($customer, $data);
        }else{
            $customer = $this->createCustomer($store, $data);
        }

        return $customer;
    }

    /**
     * Find customer.
     *
     * @param Store $store
     * @param array $data
     * @return Customer|null
     */
    public function findCustomer(Store $store, array $data): Customer|null
    {
        $hasEmail = isset($data['email']);
        $hasMobileNumber = isset($data['mobile_number']);
        if($hasMobileNumber) return $store->customers()->searchMobileNumber($data['mobile_number'])->first();
        if($hasEmail) return $store->customers()->searchEmail($data['email'])->first();
        return null;
    }

    /**
     * Should update customer.
     *
     * @param Customer $customer
     * @param array $data
     * @return bool
     */
    public function shouldUpdateCustomer(Customer $customer, array $data): bool
    {
        $fieldsToUpdate = array_intersect_key($data, array_flip($this->getCustomerUpdatableFields()));
        $currentData = $customer->only($this->getCustomerUpdatableFields());
        $diff = array_diff_assoc($fieldsToUpdate, $currentData);
        return !empty($diff);
    }


    /**
     * Update customer.
     *
     * @param Customer $customer
     * @param array $data
     * @return Customer
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        $fieldsToUpdate = array_intersect_key($data, array_flip($this->getCustomerUpdatableFields()));
        if (!empty($fieldsToUpdate)) tap($customer)->update($fieldsToUpdate);
        return $customer;
    }

    /**
     * Create customer.
     *
     * @param Store $store
     * @param array $data
     * @return Customer|null
     */
    public function createCustomer(Store $store, array $data): Customer|null
    {
        $hasEmail = isset($data['email']);
        $hasMobileNumber = isset($data['mobile_number']);


        if($hasEmail || $hasMobileNumber) {
            $data = array_merge($data, ['currency' => $store->currency]);
            return $store->customers()->create($data);
        }

        return null;
    }

    /**
     * Get customer updatable fields.
     *
     * @return array
     */
    public function getCustomerUpdatableFields(): array
    {
        return ['first_name', 'last_name', 'mobile_number', 'email', 'birthday'];
    }

    /**
     * Get friend group ID.
     *
     * @param array $data
     * @return string|null
     */
    private function getFriendGroupId(array $data): ?string
    {
        if($this->hasAuthUser() && isset($data['friend_group_id'])) return $this->getAuthUser()->friendGroups()->where('friend_groups.id', $data['friend_group_id'])->first()?->id;
        return null;
    }

    /**
     * Add delivery address.
     *
     * @return DeliveryAddress|null
     */
    private function addDeliveryAddress($order, array $data): DeliveryAddress|null
    {
        $deliveryAddressPayload = $this->prepareDeliveryAddressPayload($order, $data);
        return $deliveryAddressPayload ? DeliveryAddress::create($deliveryAddressPayload) : null;
    }

    /**
     * Add or update delivery address.
     *
     * @return DeliveryAddress|null
     */
    private function addOrUpdateDeliveryAddress($order, array $data): DeliveryAddress|null
    {
        $deliveryAddressPayload = $this->prepareDeliveryAddressPayload($order, $data);
        if($deliveryAddressPayload) {
            $oldDeliveryAddress = $order->deliveryAddress;
            if($oldDeliveryAddress) {
                $oldDeliveryAddress->update($deliveryAddressPayload);
                return $oldDeliveryAddress;
            }else{
                return DeliveryAddress::create($deliveryAddressPayload);
            }
        }else{
            return null;
        }
    }

    /**
     * Prepate delivery address payload.
     *
     * @param Order $order
     * @param array $data
     * @return array|null
     */
    private function prepareDeliveryAddressPayload($order, array $data): array|null
    {
        if(isset($data['address_id'])) {
            $address = Address::find($data['address_id']);
            return $address ? array_merge($address->getAttributes(), ['order_id' => $order->id]) : null;
        }else if(isset($data['delivery_address'])) {

            $data = $data['delivery_address'];

            if(isset($data['address_line'])) {

                return [
                    'order_id' => $order->id,
                    'type' => $data['type'] ?? null,
                    'city' => $data['city'] ?? null,
                    'state' => $data['state'] ?? null,
                    'country' => $data['country'] ?? null,
                    'address_line' => $data['address_line'],
                    'latitude' => $data['latitude'] ?? null,
                    'place_id' => $data['place_id'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'description' => $data['description'] ?? null,
                    'postal_code' => $data['postal_code'] ?? null,
                    'address_line2' => $data['address_line2'] ?? null
                ];
            }
        }

        return null;
    }

    /**
     * Update customer statistics.
     *
     * @param Order $order
     * @param Order|null $oldOrder
     * @return void
     */
    private function updateCustomerStatistics(Order $order, Order|null $oldOrder = null): void
    {
        $updateCustomerStatistics = function($order, $increment = true) {

            $customer = $order->customer;

            if($customer) {

                if(!$increment) $customer->refresh();
                $totalOrders = $customer->total_orders + ($increment ? 1 : -1);
                $totalSpend = $customer->total_spend->amount + ($increment ? $order->grand_total->amount : -$order->grand_total->amount);
                $totalAverageSpend = $totalOrders == 0 ? 0 : ($totalSpend / $totalOrders);

                $customer->update([
                    'last_order_at' => now(),
                    'total_spend' => $totalSpend,
                    'total_orders' => $totalOrders,
                    'total_average_spend' => $totalAverageSpend
                ]);

            }

        };

        $updateCustomerStatistics($order);
        if($oldOrder) $updateCustomerStatistics($oldOrder, false);
    }

    /**
     * Create customer address.
     *
     * @param Customer $customer
     * @param DeliveryAddress $deliveryAddress
     * @return void
     */
    private function createCustomerAddress(Customer $customer, DeliveryAddress $deliveryAddress): void
    {
        if(!$customer->addresses()->exists()) {
            Address::create(array_merge($deliveryAddress->getAttributes(), [
                'owner_id' => $customer->id,
                'owner_type' => $customer->getResourceName()
            ]));
        }
    }

    /**
     * Prepare transaction payload.
     *
     * @param Order $order
     * @param TransactionPaymentStatus $transactionPaymentStatus
     * @param PaymentMethod|null $paymentMethod
     * @param array $data
     * @return array
     * @throws ValidationException
     */
    private function prepareTransactionPayload(Order $order, TransactionPaymentStatus $transactionPaymentStatus, PaymentMethod|null $paymentMethod, array $data): array
    {
        $cart = $order->cart;
        $pendingAmount = $order->pending_total->amount;
        $outstandingAmount = $order->outstanding_total->amount;

        if(isset($data['amount'])) {

            $amount = $data['amount'];
            $payableAmountExceeded = $amount > ($outstandingAmountRemaining = $outstandingAmount - $pendingAmount);

            if($payableAmountExceeded) {
                $amountSpecified = $order->convertToMoneyFormat($amount, $cart->currency);
                $outstandingAmountRemaining = $order->convertToMoneyFormat($outstandingAmountRemaining, $order->currency);
                throw ValidationException::withMessages([
                    'amount' => 'The amount specified ('.$amountSpecified->amountWithCurrency.') is more than the remaining payable amount of '.$outstandingAmountRemaining->amountWithCurrency.' for this order'
                ]);
            }

            $isFullPayment = $amount == $outstandingAmount;
            $percentage = $isFullPayment ? $order->outstanding_percentage : ($amount / $cart->grand_total->amount * 100);

        }elseif(isset($data['percentage'])) {

            $percentage = $data['percentage'];
            $payablePercentageExceeded = $percentage > ($outstandingPercentageRemaining = $order->outstanding_percentage - $order->pending_percentage);

            if($payablePercentageExceeded) {
                throw ValidationException::withMessages([
                    'percentage' => 'The percentage specified ('.$percentage.'%) is more than the remaining payable percentage of '.$outstandingPercentageRemaining.'% for this order'
                ]);
            }

            $isFullPayment = $percentage == $order->outstanding_percentage;
            $amount = $isFullPayment ? $outstandingAmount : ($percentage / 100 * $cart->grand_total->amount);

        }

        $customerId = $order->customer_id;
        $requestedByUserId = $this->getAuthUser()->id;
        $description = ($isFullPayment ? 'Full' : 'Partial') . ' payment for order #'.$order->number;
        $manuallyVerifiedByUserId = $transactionPaymentStatus == TransactionPaymentStatus::PAID ? $this->getAuthUser()->id : null;
        $verificationType = $transactionPaymentStatus == TransactionPaymentStatus::PAID ? TransactionVerificationType::MANUAL->value : TransactionVerificationType::AUTOMATIC->value;

        return [
            'amount' => $amount,
            'owner_id' => $order->id,
            'percentage' => $percentage,
            'customer_id' => $customerId,
            'description' => $description,
            'currency' => $cart->currency,
            'store_id' => $order->store_id,
            'verification_type' => $verificationType,
            'payment_method_id' => $paymentMethod?->id,
            'owner_type' =>  $order->getResourceName(),
            'requested_by_user_id' => $requestedByUserId,
            'payment_status' => $transactionPaymentStatus->value,
            'manually_verified_by_user_id' => $manuallyVerifiedByUserId,
        ];
    }

    /**
     * Prepare DPO payment link payload.
     *
     * @param Transaction $transaction
     * @return array
     */
    public function prepareDpoPaymentLinkPayload(Transaction $transaction): array
    {
        $order = $transaction->owner;
        $customerPhone = $customerCountry = $customerAddress = $customerCity = $customerZip = $customerDialCode = null;

        if($order->customer_mobile_number) {
            $customerCountry = $customerDialCode = $order->customer_mobile_number->getCountry();
            $customerPhone = PhoneNumberService::getNationalPhoneNumberWithoutSpaces($order->customer_mobile_number);
        }

        if($deliveryAddress = $order->deliveryAddress) {
            $customerAddress = $deliveryAddress->address_line;
            if($deliveryAddress->city) $customerCity = $deliveryAddress->city;
            if($deliveryAddress->country) $customerCountry = $deliveryAddress->country;
            if($deliveryAddress->postal_code) $customerZip = $deliveryAddress->postal_code;
        }

        return [
            'ptl' => 24,
            'ptlType' => 'hours',
            'companyRefUnique' => 1,
            'customerZip' => $customerZip,
            'customerCity' => $customerCity,
            'companyRef' => $transaction->id,
            'customerPhone' => $customerPhone,
            'customerAddress' => $customerAddress,
            'paymentCurrency' => $order->currency,
            'customerDialCode' => $customerDialCode,
            'customerEmail' => $order->customer_email,
            'paymentAmount' => $order->grand_total->amount,
            'backURL' => 'https://www.videocopilot.net',
            'customerLastName' => $order->customer_last_name,
            'customerFirstName' => $order->customer_first_name,
            'companyAccRef' => 'Order #'.$order->number.' payment',
            'emailTransaction' => $transaction->paymentMethod->email_payment_request,
            'customerCountry' => $customerCountry ?? $transaction->paymentMethod->metadata['default_country'] ?? null,

            'redirectURL' => 'https://www.videocopilot.net' /* route('verify.order.payment', [
                'orderId' => $order->id,
                'storeId' => $order->store_id,
                'transactionId' => $transaction->id
            ])*/,

            'metadata' => [
                'Order ID' => $order->id,
                'Store ID' => $order->store_id,
                'Transaction ID' => $transaction->id
            ],
            'services' => [
                [
                    'serviceDescription' => $transaction->description,
                    'serviceDate' => now()->format('Y/m/d H:i')
                ]
            ]
        ];
    }

    /**
     * Generate order summary.
     *
     * @param Order $order
     * @return void
     */
    private function generateOrderSummary(Order $order): void
    {
        $summary = collect($order->orderProducts)->sortBy('position')->map(function(OrderProduct $orderProduct) {
            return $orderProduct->quantity >= 2 ? $orderProduct->quantity . 'x(' . $orderProduct->name . ')' : $orderProduct->name;
        })->join(', ', ' and ');

        $summary .= ' for ' . $order->grand_total->amountWithCurrency;

        if($order->discount_total->amount > 0) {
            $summary .= ' while saving ' . $order->discount_total->amountWithCurrency;
        }

        if($order->free_delivery) {
            $summary .= ' plus free delivery';
        }

        $order->update(['summary' => $summary]);
    }

    /**
     * Update order amount balance.
     *
     * @param Order $order
     * @return void
     */
    public function updateOrderAmountBalance(Order $order)
    {
        $transactions = $order->transactions()->get();
        $grandTotal = $order->cart->grand_total->amount;

        //  Calculate the order balance paid
        $paidTotal = collect($transactions)->filter(fn(Transaction $transaction) => $transaction->isPaid())->map(fn(Transaction $transaction) => $transaction->amount->amount)->sum();
        $paidPercentage = (int) ($grandTotal > 0 ? ($paidTotal / $grandTotal * 100) : 0);

        //  Calculate the order balance pending payment
        $pendingTotal = collect($transactions)->filter(fn(Transaction $transaction) => $transaction->isPendingPayment())->map(fn(Transaction $transaction) => $transaction->amount->amount)->sum();
        $pendingPercentage = (int) ($grandTotal > 0 ? ($pendingTotal / $grandTotal * 100) : 0);

        //  Calculate the order balance outstanding payment
        $outstandingTotal = $grandTotal - $paidTotal;
        $outstandingPercentage = (int) ($grandTotal > 0 ? ($outstandingTotal / $grandTotal * 100) : 0);

        if( $pendingPercentage != 0 ) {
            $paymentStatus = OrderPaymentStatus::PENDING_PAYMENT;
        }elseif( $paidPercentage == 0 ) {
            $paymentStatus = OrderPaymentStatus::UNPAID;
        }elseif( $paidPercentage == 100 ) {
            $paymentStatus = OrderPaymentStatus::PAID;
        }else {
            $paymentStatus = OrderPaymentStatus::PARTIALLY_PAID;
        }

        $order->update([
            'grand_total' => $grandTotal,
            'payment_status' => $paymentStatus->value,

            'paid_total' => $paidTotal,
            'paid_percentage' => $paidPercentage,

            'pending_total' => $pendingTotal,
            'pending_percentage' => $pendingPercentage,

            'outstanding_total' => $outstandingTotal,
            'outstanding_percentage' => $outstandingPercentage,
        ]);
    }

    /**
     * Send order created notifications.
     *
     * @param Order $order
     * @return void
     */
    private function sendOrderCreatedNotifications(Order $order): void
    {
        $store = $order->store;
        $teamMembers = $store->teamMembers()->joinedTeam()->get();

        Notification::send($teamMembers, new OrderCreated($order));

        foreach ($teamMembers as $teamMember) {
            SendSms::dispatch($this->craftNewOrderForSellerMessage($order), $teamMember->mobile_number->formatE164(), $store);
        }

        if($order->customer_mobile_number) {
            SendSms::dispatch($this->craftNewOrderForCustomerMessage($order), $order->customer_mobile_number->formatE164(), $store);
        }
    }

    /**
     * Send order updated notifications.
     *
     * @param Order $order
     * @return void
     */
    private function sendOrderUpdatedNotifications(Order $order): void
    {
        $store = $order->store;
        $teamMembers = $store->teamMembers()->joinedTeam()->get();
        Notification::send($teamMembers, new OrderUpdated($order, $this->getAuthUser()));
    }

    /**
     * Check if order cannot be updated.
     *
     * @param Order $order
     * @return bool
     */
    private function checkIfOrderCannotBeUpdated(Order $order): bool
    {
        return $order->isPaid() || $order->isPendingPayment() || $order->isPartiallyPaid() || $order->isCompleted();
    }

    /**
     * Order cannot be updated reason.
     *
     * @param Order $order
     * @return string
     */
    private function orderCannotBeUpdatedReason(Order $order): string
    {
        if($order->isPaid()) return 'This order cannot be updated because it has been paid';
        if($order->isPendingPayment()) return 'This order cannot be updated because it has a pending payment';
        if($order->isPartiallyPaid()) return 'This order cannot be updated because it has been partially paid';
        if($order->isCompleted()) return 'This order cannot be updated because it has been collected by customer';
    }
}

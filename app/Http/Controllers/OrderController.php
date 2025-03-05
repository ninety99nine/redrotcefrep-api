<?php

namespace App\Http\Controllers;

use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use App\Repositories\OrderRepository;
use App\Http\Controllers\Base\BaseController;
use App\Http\Requests\Models\Order\ShowOrdersRequest;
use App\Http\Requests\Models\Order\MarkAsPaidRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Http\Requests\Models\Order\UpdateOrderRequest;
use App\Http\Requests\Models\Order\CreateOrderRequest;
use App\Http\Requests\Models\Order\UpdateOrdersRequest;
use App\Http\Requests\Models\Order\DeleteOrdersRequest;
use App\Http\Requests\Models\Order\UpdateStatusRequest;
use App\Http\Requests\Models\Order\DownloadOrdersRequest;
use App\Http\Requests\Models\Order\RequestPaymentRequest;
use \Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Requests\Models\Order\AddOrderFriendGroupRequest;
use App\Http\Requests\Models\Order\ShowOrderStatusCountsRequest;
use App\Http\Requests\Models\Order\VerifyOrderCollectionRequest;

class OrderController extends BaseController
{
    protected OrderRepository $repository;

    /**
     * OrderController constructor.
     *
     * @param OrderRepository $repository
     */
    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Show orders.
     *
     * @param ShowOrdersRequest $request
     * @return JsonResponse|BinaryFileResponse
     */
    public function showOrders(ShowOrdersRequest $request): JsonResponse|BinaryFileResponse
    {
        if($request->userId) {
            $request->merge(['user_id' => $request->userId]);
        }else if($request->storeId) {
            $request->merge(['store_id' => $request->storeId]);
        }else if($request->customerId) {
            $request->merge(['customer_id' => $request->customerId]);
        }

        return $this->prepareOutput($this->repository->showOrders($request->all()));
    }

    /**
     * Create order.
     *
     * @param CreateOrderRequest $request
     * @return JsonResponse
     */
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->createOrder($request->all()));
    }

    /**
     * Update orders.
     *
     * @param UpdateOrdersRequest $request
     * @return JsonResponse
     */
    public function updateOrders(UpdateOrdersRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateOrders($request->all()));
    }

    /**
     * Delete orders.
     *
     * @param DeleteOrdersRequest $request
     * @return JsonResponse
     */
    public function deleteOrders(DeleteOrdersRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteOrders($request->all()));
    }

    /**
     * Download orders.
     *
     * @param DownloadOrdersRequest $request
     * @return array|StreamedResponse
     */
    public function downloadOrders(DownloadOrdersRequest $request): array|StreamedResponse
    {
        return $this->prepareOutput($this->repository->downloadOrders($request->all()));
    }

    /**
     * Show order status counts.
     *
     * @param ShowOrderStatusCountsRequest $request
     * @return JsonResponse
     */
    public function showOrderStatusCounts(ShowOrderStatusCountsRequest $request): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderStatusCounts($request->all()));
    }

    /**
     * Show order.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrder(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrder($orderId));
    }

    /**
     * Update order.
     *
     * @param UpdateOrderRequest $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function updateOrder(UpdateOrderRequest $request, string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateOrder($orderId, $request->all()));
    }

    /**
     * Delete order.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function deleteOrder(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->deleteOrder($orderId));
    }

    /**
     * Show order cancellation reasons.
     *
     * @return JsonResponse
     */
    public function showOrderCancellationReason(): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderCancellationReason());
    }

    /**
     * Generate order collection code.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function generateOrderCollectionCode(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->generateOrderCollectionCode($orderId));
    }

    /**
     * Revoke order collection code.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function revokeOrderCollectionCode(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->revokeOrderCollectionCode($orderId));
    }

    /**
     * Verify order collection.
     *
     * @param UpdateStatusRequest $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function verifyOrderCollection(VerifyOrderCollectionRequest $request, string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->verifyOrderCollection($orderId, $request->all()));
    }

    /**
     * Update order status.
     *
     * @param UpdateStatusRequest $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function updateOrderStatus(UpdateStatusRequest $request, string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->updateOrderStatus($orderId, $request->all()));
    }

    /**
     * Request order payment.
     *
     * @param RequestPaymentRequest $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function requestOrderPayment(RequestPaymentRequest $request, string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->requestOrderPayment($orderId, $request->all()));
    }

    /**
     * Verify order payment.
     *
     * @param string $orderId
     * @param string $transactionId
     * @return JsonResponse|View
     */
    public function verifyOrderPayment(string $orderId, string $transactionId): JsonResponse|View
    {
        return $this->prepareOutput($this->repository->verifyOrderPayment($orderId, $transactionId));
    }

    /**
     * Show payment methods for requesting order payment.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showPaymentMethodsForRequestingOrderPayment(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPaymentMethodsForRequestingOrderPayment($orderId));
    }

    /**
     * Mark order as paid.
     *
     * @param MarkAsPaidRequest $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function markOrderAsPaid(MarkAsPaidRequest $request, string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->markOrderAsPaid($orderId, $request->all()));
    }

    /**
     * Mark order as unpaid.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function markOrderAsUnpaid(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->markOrderAsUnpaid($orderId));
    }

    /**
     * Show payment methods for marking as paid.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showPaymentMethodsForMarkingAsPaid(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showPaymentMethodsForMarkingAsPaid($orderId));
    }

    /**
     * Show order store.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderStore(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderStore($orderId));
    }

    /**
     * Show order customer.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderCustomer(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderCustomer($orderId));
    }

    /**
     * Show order occasion.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderOccasion(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderOccasion($orderId));
    }

    /**
     * Show order placed by user.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderPlacedByUser(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderPlacedByUser($orderId));
    }

    /**
     * Show order created by user.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderCreatedByUser(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderCreatedByUser($orderId));
    }

    /**
     * Show order collection verified by user.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderCollectionVerifiedByUser(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderCollectionVerifiedByUser($orderId));
    }

    /**
     * Show order delivery address.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderDeliveryAddress(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderDeliveryAddress($orderId));
    }

    /**
     * Show order friend group.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderFriendGroup(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderFriendGroup($orderId));
    }

    /**
     * Add order friend group.
     *
     * @param AddOrderFriendGroupRequest $request
     * @param string $orderId
     * @return JsonResponse
     */
    public function addOrderFriendGroup(AddOrderFriendGroupRequest $request, string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->addOrderFriendGroup($orderId, $request->input('friend_group_id')));
    }

    /**
     * Remove order friend group.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function removeOrderFriendGroup(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->removeOrderFriendGroup($orderId));
    }

    /**
     * Show order viewers.
     *
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderViewers(string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderViewers($orderId));
    }

    /**
     * Show order transactions.
     *
     * @param string $storeId
     * @param string $orderId
     * @return JsonResponse
     */
    public function showOrderTransactions(string $storeId, string $orderId): JsonResponse
    {
        return $this->prepareOutput($this->repository->showOrderTransactions($storeId, $orderId));
    }
}

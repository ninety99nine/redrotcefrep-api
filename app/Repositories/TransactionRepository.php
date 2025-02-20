<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\Store;
use App\Models\MediaFile;
use App\Models\PricingPlan;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use App\Enums\RequestFileName;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\TransactionResources;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Services\Billing\DirectPayOnline\DirectPayOnlineService;
use Carbon\Carbon;

class TransactionRepository extends BaseRepository
{
    /**
     * Show transactions.
     *
     * @param array $data
     * @return TransactionResources|array
     */
    public function showTransactions(array $data = []): TransactionResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;
            $orderId = isset($data['order_id']) ? $data['order_id'] : null;
            $pricingPlanId = isset($data['pricing_plan_id']) ? $data['pricing_plan_id'] : null;

            if($storeId) {
                $store = Store::find($storeId);
                if($store) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show transactions'];
                    $this->setQuery($store->transactions()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }else if($orderId) {
                $order = Order::with(['store'])->find($orderId);
                $store = $order->store;
                if($order) {
                    if($store) {
                        $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                        if(!$isAuthourized) return ['message' => 'You do not have permission to show transactions'];
                        $this->setQuery($order->transactions()->latest());
                    }else{
                        return ['message' => 'This store does not exist'];
                    }
                }else{
                    return ['message' => 'This order does not exist'];
                }
            }else if($pricingPlanId) {
                $pricingPlan = PricingPlan::find($pricingPlanId);
                if($pricingPlan) {
                    $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($pricingPlan);
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show transactions'];
                    $this->setQuery($pricingPlan->transactions()->latest());
                }else{
                    return ['message' => 'This pricing plan does not exist'];
                }
            }else {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show transactions'];
                $this->setQuery(Transaction::latest());
            }
        }

        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /**
     * Create transaction.
     *
     * @param array $data
     * @return Transaction|array
     */
    public function createTransaction(array $data): Transaction|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create transactions'];

        $transaction = Transaction::create($data);
        $this->getMediaFileRepository()->authourize()->createMediaFile(RequestFileName::TRANSACTION_PROOF_OF_PAYMENT_PHOTO, $transaction);
        return $this->showCreatedResource($transaction);
    }

    /**
     * Delete transactions.
     *
     * @param array $transactionIds
     * @return array
     */
    public function deleteTransactions(array $transactionIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete transactions'];
        $transactions = $this->setQuery(Transaction::with(['owner']))->getTransactionsByIds($transactionIds);

        if($totalTransactions = $transactions->count()) {

            foreach($transactions as $transaction) {
                $transaction->delete();
                if($transaction->owner_type == (new Order())->getResourceName()) {
                    $this->getOrderRepository()->updateOrderAmountBalance($transaction->owner);
                }
            }

            return ['deleted' => true, 'message' => $totalTransactions  .($totalTransactions  == 1 ? ' transaction': ' transactions') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No transactions deleted'];
        }
    }

    /**
     * Show transaction.
     *
     * @param Transaction|string|null $transactionId
     * @return Transaction|array|null
     */
    public function showTransaction(Transaction|string|null $transactionId = null): Transaction|array|null
    {
        if(($transaction = $transactionId) instanceof Transaction) {
            $transaction = $this->applyEagerLoadingOnModel($transaction);
        }else {
            $query = $this->getQuery() ?? Transaction::query();
            if($transactionId) $query = $query->where('transactions.id', $transactionId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $transaction = $this->query->first();
        }

        return $this->showResourceExistence($transaction);
    }

    /**
     * Delete transaction.
     *
     * @param string $transactionId
     * @return array
     */
    public function deleteTransaction(string $transactionId): array
    {
        $transaction = Transaction::with(['store', 'owner'])->find($transactionId);

        if($transaction) {

            $store = $transaction->store;
            $isAuthourized = $this->isAuthourized() || ($store && $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store));

            if($isAuthourized) {

                /** @var PaymentMethod|null $paymentMethod */
                $paymentMethod = $transaction->paymentMethod;
                if($paymentMethod && $paymentMethod->isDPO() && !$transaction->isPaid()) $this->cancelTransactionPaymentLink($transaction);

                $deleted = $transaction->delete();

                if($deleted) {

                    if($transaction->owner_type == (new Order())->getResourceName()) {
                        $this->getOrderRepository()->updateOrderAmountBalance($transaction->owner);
                    }

                    return ['deleted' => true, 'message' => 'Transaction deleted'];
                }else{
                    return ['deleted' => false, 'message' => 'Transaction delete unsuccessful'];
                }

            }else{
                return ['deleted' => false, 'message' => 'You do not have permission to delete transaction'];
            }

        }else{
            return ['deleted' => false, 'message' => 'This transaction does not exist'];
        }
    }

    /**
     *  Renew transaction payment link.
     *
     * @param string $transactionId
     * @return array
     */
    public function renewPaymentLink(string $transactionId): array
    {
        $transaction = Transaction::with(['store', 'owner', 'paymentMethod'])->find($transactionId);

        if($transaction) {

            $store = $transaction->store;
            $isAuthourized = $this->isAuthourized() || ($store && $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store));

            if($isAuthourized) {

                if($transaction->isSubjectToManualVerification()) return ['renewed' => false, 'message' => 'Transaction has been manually verified and cannot be renewed'];
                if($transaction->isPaid()) return ['renewed' => false, 'message' => 'Transaction has been paid and cannot be renewed'];

                /** @var PaymentMethod|null $paymentMethod */
                $paymentMethod = $transaction->paymentMethod;
                if(!$paymentMethod) ['renewed' => false, 'message' => 'The transaction payment method does not exist'];

                if($paymentMethod->isDPO()) {

                    if(Carbon::parse($transaction->metadata['dpo_payment_url_expires_at'])->isFuture()) return ['renewed' => false, 'message' => 'Transaction has not yet expired therefore cannot be renewed'];

                    $this->cancelTransactionPaymentLink($transaction);
                    $response = $this->createTransactionPaymentLink($transaction);

                    if($response['created']) {
                        $metadata = $response['data'];
                    }else{
                        return ['requested' => false, 'message' => $response['message']];
                    }

                    $transaction->update(['metadata' => $metadata]);

                    return [
                        'successful' => true,
                        'message' => 'DPO payment link created',
                        'transaction' => new TransactionResource($this->getTransactionRepository()->applyEagerLoadingOnModel($transaction))
                    ];

                }else{
                    return ['renewed' => false, 'message' => 'The "'.$paymentMethod->name.'" payment method cannot be used to renew transaction'];
                }

            }else{
                return ['renewed' => false, 'message' => 'You do not have permission to renew transaction payment link'];
            }

        }else{
            return ['renewed' => false, 'message' => 'This transaction does not exist'];
        }
    }

    /**
     * Show transaction proof of payment photo.
     *
     * @param string $transactionId
     * @return array
     */
    public function showTransactionProofOfPaymentPhoto(string $transactionId): array
    {
        $transaction = Transaction::with(['proofOfPayment', 'store'])->find($transactionId);

        if($transaction) {

            $store = $transaction->store;
            $isAuthourized = $this->isAuthourized() || ($store && $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store));

            if($isAuthourized) {

                if($transaction) {
                    return $this->getMediaFileRepository()->setQuery($transaction->proofOfPayment())->showMediaFile();
                }else{
                    return ['message' => 'This transaction does not exist'];
                }

            }else{
                return ['uploaded' => false, 'message' => 'You do not have permission to update this transaction proof of payment photo'];
            }

        }else{
            return ['uploaded' => false, 'message' => 'This transaction does not exist'];
        }
    }

    /**
     * Upload transaction proof of payment photo.
     *
     * @param string $transactionId
     * @return MediaFile|array
     */
    public function uploadTransactionProofOfPaymentPhoto(string $transactionId): MediaFile|array
    {
        $transaction = Transaction::with(['proofOfPayment', 'store'])->find($transactionId);

        if($transaction) {

            $store = $transaction->store;
            $isAuthourized = $this->isAuthourized() || ($store && $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store));

            if($isAuthourized) {

                if($transaction->proofOfPayment) {
                    $result = $this->getMediaFileRepository()->authourize()->updateMediaFile($transaction->proofOfPayment);
                }else{
                    $result = $this->getMediaFileRepository()->authourize()->createMediaFile(RequestFileName::TRANSACTION_PROOF_OF_PAYMENT_PHOTO, $transaction);
                }

                $uploaded = (isset($result['created']) && $result['created'] == true) || (isset($result['updated']) && $result['updated'] == true);

                if($uploaded) {

                    $mediaFile = isset($result['media_file']) ? $result['media_file'] : $result['media_files'][0];
                    return $this->showSavedResource($mediaFile, 'uploaded', 'Transaction proof of payment uploaded');

                }else{
                    return ['uploaded' => false, 'message' => $result['message']];
                }

            }else{
                return ['uploaded' => false, 'message' => 'You do not have permission to update this transaction proof of payment photo'];
            }

        }else{
            return ['uploaded' => false, 'message' => 'This transaction does not exist'];
        }
    }

    /**
     * Delete transaction proof of payment photo.
     *
     * @param string $transactionId
     * @return array
     */
    public function deleteTransactionProofOfPaymentPhoto(string $transactionId): array
    {
        $transaction = Transaction::with(['proofOfPayment', 'store'])->find($transactionId);

        if($transaction) {

            $store = $transaction->store;
            $isAuthourized = $this->isAuthourized() || ($store && $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store));

            if($isAuthourized) {
                if($transaction->proofOfPayment) {
                    return $this->getMediaFileRepository()->deleteMediaFile($transaction->proofOfPayment);
                }else{
                    return ['deleted' => false, 'message' => 'This transaction proof of payment photo does not exist'];
                }
            }else{
                return ['deleted' => false, 'message' => 'You do not have permission to delete this transaction proof of payment photo'];
            }

        }else{
            return ['deleted' => false, 'message' => 'This transaction does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query transaction by ID.
     *
     * @param Transaction|string $transactionId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryTransactionById(Transaction|string $transactionId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('transactions.id', $transactionId)->with($relationships);
    }

    /**
     * Get transaction by ID.
     *
     * @param Transaction|string $transactionId
     * @param array $relationships
     * @return Transaction|null
     */
    public function getTransactionById(Transaction|string $transactionId, array $relationships = []): Transaction|null
    {
        return $this->queryTransactionById($transactionId, $relationships)->first();
    }

    /**
     * Query transactions by IDs.
     *
     * @param array<string> $transactionId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryTransactionsByIds($transactionIds): Builder|Relation
    {
        return $this->query->whereIn('transactions.id', $transactionIds);
    }

    /**
     * Get transactions by IDs.
     *
     * @param array<string> $transactionId
     * @param string $relationships
     * @return Collection
     */
    public function getTransactionsByIds($transactionIds): Collection
    {
        return $this->queryTransactionsByIds($transactionIds)->get();
    }

    /**
     * Create transaction payment link
     *
     * @param Transaction $transaction
     * @return array
     */
    private function createTransactionPaymentLink(Transaction $transaction): array
    {
        $paymentMethod = $transaction->paymentMethod;

        if($transaction->owner_type == (new Order())->getResourceName()) {

            $companyToken = null;   //  update to capture the store company token
            $dpoPaymentLinkPayload = $this->getOrderRepository()->prepareDpoPaymentLinkPayload($transaction);

        }else if($transaction->owner_type == (new PricingPlan())->getResourceName()) {

            $companyToken = config('app.DPO_COMPANY_TOKEN');
            $dpoPaymentLinkPayload = $this->getPricingPlanRepository()->prepareDpoPaymentLinkPayload($transaction);

        }

        return DirectPayOnlineService::createPaymentLink($companyToken, $dpoPaymentLinkPayload);
    }

    /**
     * Cancel transaction payment link
     *
     * @param Transaction $transaction
     * @return void
     */
    private function cancelTransactionPaymentLink(Transaction $transaction): void
    {
        $paymentMethod = $transaction->paymentMethod;

        if($transaction->owner_type == (new Order())->getResourceName()) {

            $companyToken = null;   //  update to capture the store company token

        }else if($transaction->owner_type == (new PricingPlan())->getResourceName()) {

            $companyToken = config('app.DPO_COMPANY_TOKEN');

        }

        if($transaction->metadata) {
            $transactionToken = $transaction->metadata['dpo_transaction_token'];
            DirectPayOnlineService::cancelPaymentLink($companyToken, $transactionToken);
        }
    }
}

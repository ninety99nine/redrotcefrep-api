<?php

namespace App\Repositories;

use Exception;
use App\Models\Store;
use App\Jobs\SendSms;
use App\Traits\AuthTrait;
use Illuminate\View\View;
use App\Models\Transaction;
use App\Models\PricingPlan;
use App\Models\AiAssistant;
use App\Models\PaymentMethod;
use App\Traits\Base\BaseTrait;
use App\Enums\PaymentMethodType;
use Illuminate\Support\Collection;
use App\Traits\MessageCrafterTrait;
use Illuminate\Http\RedirectResponse;
use App\Enums\TransactionFailureType;
use App\Enums\TransactionPaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TransactionVerificationType;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\PricingPlanResources;
use App\Http\Resources\PaymentMethodResources;
use Illuminate\Validation\ValidationException;
use App\Services\PhoneNumber\PhoneNumberService;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Services\Billing\Airtime\OrangeAirtimeService;
use App\Services\Billing\DirectPayOnline\DirectPayOnlineService;

class PricingPlanRepository extends BaseRepository
{
    use AuthTrait, BaseTrait, MessageCrafterTrait;

    /**
     * Show pricing plans.
     *
     * @return PricingPlanResources|array
     */
    public function showPricingPlans(): PricingPlanResources|array
    {
        $this->setQuery(PricingPlan::query()->latest());
        return $this->applyFiltersOnQuery()->getOrCountResources();
    }

    /**
     * Create pricing plan.
     *
     * @param array $data
     * @return PricingPlan|array
     */
    public function createPricingPlan(array $data): PricingPlan|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create pricing plans'];

        $pricingPlan = PricingPlan::create($data);
        return $this->showCreatedResource($pricingPlan);
    }

    /**
     * Delete pricing plans.
     *
     * @param array $pricingPlanIds
     * @return array
     */
    public function deletePricingPlans(array $pricingPlanIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete pricing plans'];
        $pricingPlans = $this->setQuery(PricingPlan::query())->getPricingPlansByIds($pricingPlanIds);

        if($totalPricingPlans = $pricingPlans->count()) {

            foreach($pricingPlans as $pricingPlan) {
                $pricingPlan->delete();
            }

            return ['deleted' => true, 'message' => $totalPricingPlans  .($totalPricingPlans  == 1 ? ' pricing plan': ' pricing plans') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No pricing plans deleted'];
        }
    }

    /**
     * Show pricing plan.
     *
     * @param PricingPlan|string|null $pricingPlanId
     * @return PricingPlan|array|null
     */
    public function showPricingPlan(PricingPlan|string|null $pricingPlanId = null): PricingPlan|array|null
    {
        if(($pricingPlan = $pricingPlanId) instanceof PricingPlan) {
            $pricingPlan = $this->applyEagerLoadingOnModel($pricingPlan);
        }else {
            $query = $this->getQuery() ?? PricingPlan::query();
            if($pricingPlanId) $query = $query->where('pricing_plans.id', $pricingPlanId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $pricingPlan = $this->query->first();
        }

        return $this->showResourceExistence($pricingPlan);
    }

    /**
     * Update pricing plan.
     *
     * @param string $pricingPlanId
     * @param array $data
     * @return PricingPlan|array
     */
    public function updatePricingPlan(string $pricingPlanId, array $data): PricingPlan|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update pricing plan'];
        $pricingPlan = PricingPlan::find($pricingPlanId);

        if($pricingPlan) {

            $pricingPlan->update($data);
            return $this->showUpdatedResource($pricingPlan);

        }else{
            return ['updated' => false, 'message' => 'This pricing plan does not exist'];
        }
    }

    /**
     * Delete pricing plan.
     *
     * @param string $pricingPlanId
     * @return array
     */
    public function deletePricingPlan(string $pricingPlanId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete pricing plan'];
        $pricingPlan = PricingPlan::find($pricingPlanId);

        if($pricingPlan) {
            $deleted = $pricingPlan->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'Pricing plan deleted'];
            }else{
                return ['deleted' => false, 'message' => 'Pricing plan delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This pricing plan does not exist'];
        }
    }

    /**
     * Show pricing plan payment methods.
     *
     * @param string $pricingPlanId
     * @return PaymentMethodResources|array
     */
    public function showPricingPlanPaymentMethods(string $pricingPlanId): PaymentMethodResources|array
    {
        $pricingPlan = PricingPlan::find($pricingPlanId);

        if($pricingPlan) {
            $query = PaymentMethod::whereIn('type', [PaymentMethodType::ORANGE_AIRTIME, PaymentMethodType::DPO]);
            return $this->getPaymentMethodRepository()->setQuery($query)->showPaymentMethods();
        }else{
            return ['message' => 'This pricing plan does not exist'];
        }
    }

    /**
     * Pay pricing plan.
     *
     * @param string $pricingPlanId
     * @param array $data
     * @return array
     */
    public function payPricingPlan(string $pricingPlanId, array $data): array
    {
        $store = $aiAssistant = null;
        $pricingPlan = PricingPlan::find($pricingPlanId);
        if(!$pricingPlan) return ['successful' => false, 'message' => 'This pricing plan does not exist'];

        if( $this->offersStoreSubscription($pricingPlan) ||
            $this->offersWhatsappCredits($pricingPlan) ||
            $this->offersEmailCredits($pricingPlan) ||
            $this->offersSmsCredits($pricingPlan)
        ) {
            if(!isset($data['store_id'])) throw ValidationException::withMessages(['store_id' => 'The store id field is required']);
            $store = Store::find($data['store_id']);

            if($store) {
                $isAuthourized = $this->isAuthourized() || $this->getStoreRepository()->checkIfAssociatedAsStoreCreatorOrAdmin($store);
                if(!$isAuthourized) return ['successful' => false, 'message' => 'You do not have permission to pay'];
            }else{
                return ['successful' => false, 'message' => 'This store does not exist'];
            }

        }

        if( $this->offersAiAssistantSubscription($pricingPlan) ||
            $this->offersAiAssistantTopUpCredits($pricingPlan)) {

            $aiAssistant = request()->current_user->aiAssistant()->first();
            if(!$aiAssistant) return ['successful' => false, 'message' => 'This AI Assistant does not exist'];

        }

        $paymentMethodId = $data['payment_method_id'] ?? null;
        $paymentMethodType = $data['payment_method_type'] ?? null;

        if($paymentMethodId) {
            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = PaymentMethod::find($paymentMethodId);
            if(!$paymentMethod) return ['successful' => false, 'message' => 'The specified payment method does not exist'];
        }else if($paymentMethodType) {
            /** @var PaymentMethod|null $paymentMethod */
            $paymentMethod = PaymentMethod::whereType($paymentMethodType)->first();
            if(!$paymentMethod) return ['successful' => false, 'message' => 'The specified payment method does not exist'];
        }

        if(!$paymentMethod->automated_verification) return ['successful' => false, 'message' => 'The '.$paymentMethod->name.' payment method is not an automated method of payment'];

        if(!$paymentMethod->active) return ['successful' => false, 'message' => 'The '.$paymentMethod->name.' payment method has been deactivated'];

        $acceptablePaymentMethodTypes = [PaymentMethodType::DPO->value, PaymentMethodType::ORANGE_AIRTIME->value];

        if(in_array($paymentMethod->type, $acceptablePaymentMethodTypes)) {

            $transactionPayload = $this->prepareTransactionPayload($store, $aiAssistant, $pricingPlan, $paymentMethod);
            $transaction = $this->getTransactionRepository()->authourize()->shouldReturnModel()->createTransaction($transactionPayload);

            $transaction->setRelation('owner', $pricingPlan);
            if($store) $transaction->setRelation('store', $store);
            if($aiAssistant) $transaction->setRelation('aiAssistant', $aiAssistant);

            if($paymentMethod->isDPO()) {

                $companyToken = config('app.DPO_COMPANY_TOKEN');
                $dpoPaymentLinkPayload = $this->prepareDpoPaymentLinkPayload($transaction);
                $response = DirectPayOnlineService::createPaymentLink($companyToken, $dpoPaymentLinkPayload);

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

            }else if($paymentMethod->isOrangeAirtime()) {

                $mobileNetworkProductId = $pricingPlan->type;
                $msisdn = $this->getAuthUser()->mobile_number->formatE164();
                $transaction = OrangeAirtimeService::billUsingAirtime($msisdn, $mobileNetworkProductId, $transaction);

                if($transaction->payment_status == TransactionPaymentStatus::FAILED_PAYMENT->value) {
                    return [
                        'successful' => false,
                        'message' => $transaction->failure_reason ?? $transaction->failure_type,
                        'transaction' => new TransactionResource($this->getTransactionRepository()->applyEagerLoadingOnModel($transaction))
                    ];
                }

            }

            return $this->offerPricingPlan($store, $aiAssistant, $pricingPlan, $transaction);

        }else{
            return ['successful' => false, 'message' => 'The specified payment method cannot be used for this payment'];
        }
    }

    /**
     * Verify pricing plan payment.
     *
     * @param string $pricingPlanId
     * @param string $transactionId
     * @return RedirectResponse|array
     */
    public function verifyPricingPlanPayment(string $pricingPlanId, string $transactionId): RedirectResponse|array
    {
        try{

            $store = $aiAssistant = null;
            $pricingPlan = PricingPlan::find($pricingPlanId);
            if(!$pricingPlan) return ['verified' => false, 'message' => 'This pricing plan does not exist'];

            /** @var Transaction|null $transaction */
            $transaction = Transaction::with(['owner', 'store', 'aiAssistant', 'paymentMethod'])->find($transactionId);
            if(!$transaction) return ['verified' => false, 'message' => 'The transaction does not exist'];

            if(!$transaction->isPaid()) {

                /** @var PaymentMethod|null $paymentMethod */
                $paymentMethod = $transaction->paymentMethod;
                if(!$paymentMethod) ['verified' => false, 'message' => 'The transaction payment method does not exist'];

                if($this->offersStoreSubscription($pricingPlan)) {

                    /** @var Store|null $store */
                    $store = $transaction->store;
                    if(!$store) ['verified' => false, 'message' => 'The transaction store does not exist'];

                }

                if($this->offersAiAssistantSubscription($pricingPlan)) {

                    /** @var AiAssistant|null $aiAssistant */
                    $aiAssistant = $transaction->aiAssistant;
                    if(!$aiAssistant) ['verified' => false, 'message' => 'The transaction AI Assistant does not exist'];

                }

                if($paymentMethod->isDpo()) {

                    $companyToken = config('app.DPO_COMPANY_TOKEN');
                    $transactionToken = $transaction->metadata['dpo_transaction_token'];
                    $metadata = DirectPayOnlineService::verifyPayment($companyToken, $transactionToken);


                    $this->offerPricingPlan($store, $aiAssistant, $pricingPlan, $transaction);

                    $transaction->update([
                        'failure_type' => null,
                        'failure_reason' => null,
                        'payment_status' => TransactionPaymentStatus::PAID->value,
                        'metadata' => array_merge($transaction->metadata, $metadata)
                    ]);

                }else{
                    return ['verified' => false, 'message' => 'The "'.$paymentMethod->name.'" payment method cannot be used to verify transaction payment'];
                }

            }

            if(request()->wantsJson()) {

                return $this->showSavedResource($transaction, 'verified');

            }else{

                $storeHref = ltrim(parse_url(route('show.store', ['storeId' => $store->id]), PHP_URL_PATH), '/');
                return redirect(config('app.FRONTEND_URI') . '/success');

            }

        }catch(Exception $e) {

            $transaction->update([
                'failure_reason' => $e->getMessage(),
                'payment_status' => TransactionPaymentStatus::FAILED_PAYMENT->value,
                'failure_type' => TransactionFailureType::PAYMENT_VERIFICATION_FAILED->value
            ]);

            if(request()->wantsJson()) {
                return ['verified' => false, 'message' => $e->getMessage()];
            }else{
                $storeHref = ltrim(parse_url(route('show.store', ['storeId' => $store->id]), PHP_URL_PATH), '/');
                return redirect(config('app.FRONTEND_URI') . '/fail');
            }

        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query pricing plan by ID.
     *
     * @param string $pricingPlanId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryPricingPlanById(string $pricingPlanId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('pricing_plans.id', $pricingPlanId)->with($relationships);
    }

    /**
     * Get pricing plan by ID.
     *
     * @param string $pricingPlanId
     * @param array $relationships
     * @return PricingPlan|null
     */
    public function getPricingPlanById(string $pricingPlanId, array $relationships = []): PricingPlan|null
    {
        return $this->queryPricingPlanById($pricingPlanId, $relationships)->first();
    }

    /**
     * Query pricing plans by IDs.
     *
     * @param array<string> $pricingPlanId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryPricingPlansByIds($pricingPlanIds): Builder|Relation
    {
        return $this->query->whereIn('pricing_plans.id', $pricingPlanIds);
    }

    /**
     * Get pricing plans by IDs.
     *
     * @param array<string> $pricingPlanId
     * @param string $relationships
     * @return Collection
     */
    public function getPricingPlansByIds($pricingPlanIds): Collection
    {
        return $this->queryPricingPlansByIds($pricingPlanIds)->get();
    }

    /**
     * Offers subscription.
     *
     * @param PricingPlan $pricingPlan
     * @return bool
     */
    private function offersSubscription(PricingPlan $pricingPlan): bool
    {
        return $this->offersStoreSubscription($pricingPlan) ||
               $this->offersAiAssistantSubscription($pricingPlan);
    }

    /**
     * Offers store subscription.
     *
     * @param PricingPlan $pricingPlan
     * @return bool
     */
    private function offersStoreSubscription(PricingPlan $pricingPlan): bool
    {
        return isset($pricingPlan->metadata['store_subscription']);
    }

    /**
     * Offers store AI Assistant subscription.
     *
     * @param PricingPlan $pricingPlan
     * @return bool
     */
    private function offersAiAssistantSubscription(PricingPlan $pricingPlan): bool
    {
        return isset($pricingPlan->metadata['ai_assistant_subscription']);
    }

    /**
     * Offers AI Assistant top up credits.
     *
     * @param PricingPlan $pricingPlan
     * @return bool
     */
    private function offersAiAssistantTopUpCredits(PricingPlan $pricingPlan): bool
    {
        return isset($pricingPlan->metadata['ai_assistant_top_up_credits']);
    }

    /**
     * Offers SMS credits.
     *
     * @param PricingPlan $pricingPlan
     * @return bool
     */
    private function offersSmsCredits(PricingPlan $pricingPlan): bool
    {
        return isset($pricingPlan->metadata['sms_credits']);
    }

    /**
     * Offers email credits.
     *
     * @param PricingPlan $pricingPlan
     * @return bool
     */
    private function offersEmailCredits(PricingPlan $pricingPlan): bool
    {
        return isset($pricingPlan->metadata['email_credits']);
    }

    /**
     * Offers Whatsapp credits.
     *
     * @param PricingPlan $pricingPlan
     * @return bool
     */
    private function offersWhatsappCredits(PricingPlan $pricingPlan): bool
    {
        return isset($pricingPlan->metadata['whatsapp_credits']);
    }

    /**
     * Give offer.
     *
     * @param Store|null $store
     * @param AiAssistant|null $aiAssistant
     * @param PricingPlan $pricingPlan
     * @param Transaction $transaction
     * @return array
     */
    private function offerPricingPlan(Store|null $store, AiAssistant|null $aiAssistant, PricingPlan $pricingPlan, Transaction $transaction): array
    {
        if($this->offersSubscription($pricingPlan)) {

            $message = 'Subscription created';

            /** @var PaymentMethod $paymentMethod */
            $paymentMethod = $transaction->paymentMethod;

            $offersStoreSubscription = $this->offersStoreSubscription($pricingPlan);
            $offersAiAssistantSubscription = $this->offersAiAssistantSubscription($pricingPlan);

            if($offersStoreSubscription) {
                $storeSubscriptionPayload = $this->prepareStoreSubscriptionPayload($pricingPlan, $transaction);
                $subscription = $this->getSubscriptionRepository()->shouldReturnModel()->createSubscription($storeSubscriptionPayload, $store);

                if($paymentMethod->isOrangeAirtime()) {
                    $smsMessage = $this->craftStoreSubscriptionPaidMessage($store, $transaction, $subscription);
                    SendSms::dispatch($smsMessage, $transaction->requestedByUser->mobile_number->formatE164());
                }
            }

            if($offersAiAssistantSubscription) {
                $aiAssistantSubscriptionPayload = $this->prepareAiAssistantSubscriptionPayload($pricingPlan, $transaction);
                $subscription = $this->getSubscriptionRepository()->shouldReturnModel()->createSubscription($aiAssistantSubscriptionPayload, $aiAssistant);

                if($paymentMethod->isOrangeAirtime()) {
                    $smsMessage = $this->craftAIAssistantSubscriptionPaidMessage($transaction, $subscription);
                    SendSms::dispatch($smsMessage, $transaction->requestedByUser->mobile_number->formatE164());
                }
            }

        }

        if($this->offersSmsCredits($pricingPlan) || $this->offersEmailCredits($pricingPlan) || $this->offersWhatsappCredits($pricingPlan)) {

            if(!isset($message)) $message = 'Credits added';
            $prepareStoreQuotaPayload = $this->prepareStoreQuotaPayload($store, $pricingPlan);
            $this->getStoreRepository()->authourize()->shouldReturnModel()->updateStoreQuota($store, $prepareStoreQuotaPayload);

        }

        if($this->offersAiAssistantTopUpCredits($pricingPlan)) {

            if(!isset($message)) $message = 'Credits added';
            $aiAssistant->update(['remaining_paid_top_up_tokens' => $aiAssistant->ai_assistant_top_up_credits + $pricingPlan->metadata['ai_assistant_top_up_credits']]);

        }

        return [
            'successful' => true,
            'message' => $message
        ];
    }

    /**
     * Prepare store subscription payload.
     *
     * @param PricingPlan $pricingPlan
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    private function prepareStoreSubscriptionPayload(PricingPlan $pricingPlan, Transaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'pricing_plan_id' => $pricingPlan->id,
            'duration' => $pricingPlan->metadata['store_subscription']['duration'],
            'frequency' => $pricingPlan->metadata['store_subscription']['frequency']
        ];
    }

    /**
     * Prepare AI Assistant subscription payload.
     *
     * @param PricingPlan $pricingPlan
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    private function prepareAiAssistantSubscriptionPayload(PricingPlan $pricingPlan, Transaction $transaction): array
    {
        return [
            'transaction_id' => $transaction->id,
            'pricing_plan_id' => $pricingPlan->id,
            'credits' => $pricingPlan->metadata['ai_assistant_subscription']['credits'],
            'duration' => $pricingPlan->metadata['ai_assistant_subscription']['duration'],
            'frequency' => $pricingPlan->metadata['ai_assistant_subscription']['frequency']
        ];
    }

    /**
     * Prepare transaction payload.
     *
     * @param Store|null $store
     * @param AiAssistant|null $aiAssistant
     * @param PricingPlan $pricingPlan
     * @param PaymentMethod $paymentMethod
     * @return array
     */
    private function prepareTransactionPayload(Store|null $store, AiAssistant|null $aiAssistant, PricingPlan $pricingPlan, PaymentMethod $paymentMethod): array
    {
        return [
            'percentage' => 100,
            'store_id' => $store?->id,
            'owner_id' => $pricingPlan->id,
            'currency' => $pricingPlan->currency,
            'ai_assistant_id' => $aiAssistant?->id,
            'amount' => $pricingPlan->price->amount,
            'payment_method_id' => $paymentMethod->id,
            'description' => $pricingPlan->description,
            'owner_type' => $pricingPlan->getResourceName(),
            'requested_by_user_id' => $this->getAuthUser()->id,
            'payment_status' => TransactionPaymentStatus::PENDING_PAYMENT->value,
            'verification_type' => TransactionVerificationType::AUTOMATIC->value,
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
        $user = $this->getAuthUser();
        $pricingPlan = $transaction->owner;
        $paymentMethod = $transaction->paymentMethod;
        $companyAccRef = ucwords($pricingPlan->type);
        $metadata = ['Transaction ID' => $transaction->id];
        $customerPhone = $customerCountry = $customerDialCode = null;

        if($user->mobile_number) {
            $customerCountry = $customerDialCode = $user->mobile_number->getCountry();
            $customerPhone = PhoneNumberService::getNationalPhoneNumberWithoutSpaces($user->mobile_number);
        }

        if($store = $transaction->store) $metadata['Store ID'] = $store->id;
        if($aiAssistant = $transaction->aiAssistant) $metadata['AI Assistant ID'] = $aiAssistant->id;

        return [
            'ptl' => 24,
            'ptlType' => 'hours',
            'companyRefUnique' => 1,
            'metadata' => $metadata,
            'emailTransaction' => true,
            'customerEmail' => $user->email,
            'companyRef' => $transaction->id,
            'companyAccRef' => $companyAccRef,
            'customerPhone' => $customerPhone,
            'customerCountry' => $customerCountry,
            'customerLastName' => $user->last_name,
            'customerDialCode' => $customerDialCode,
            'customerFirstName' => $user->first_name,
            'emailTransaction' => !empty($user->email),
            'paymentCurrency' => $pricingPlan->currency,
            'paymentAmount' => $pricingPlan->price->amount,
            'backURL' => url()->previous(),
            'redirectURL' => route('verify.pricing.plan.payment', [
                'pricingPlanId' => $pricingPlan->id,
                'transactionId' => $transaction->id
            ]),
            'services' => [
                [
                    'serviceDescription' => $pricingPlan->name,
                    'serviceDate' => now()->format('Y/m/d H:i')
                ]
            ]
        ];
    }

    /**
     * Prepare store quota payload.
     *
     * @param Store|null $store
     * @param PricingPlan $pricingPlan
     * @return array
     */
    private function prepareStoreQuotaPayload($store, PricingPlan $pricingPlan): array
    {
        $storeQuota = $store->storeQuota()->first();
        $data = [];

        if($this->offersSmsCredits($pricingPlan)) {
            $data['sms_credits'] = $storeQuota->sms_credits + $pricingPlan->metadata['sms_credits'];
        }

        if($this->offersEmailCredits($pricingPlan)) {
            $data['email_credits'] = $storeQuota->email_credits + $pricingPlan->metadata['email_credits'];
        }

        if($this->offersWhatsappCredits($pricingPlan)) {
            $data['whatsapp_credits'] = $storeQuota->whatsapp_credits + $pricingPlan->metadata['whatsapp_credits'];
        }

        return $data;
    }
}

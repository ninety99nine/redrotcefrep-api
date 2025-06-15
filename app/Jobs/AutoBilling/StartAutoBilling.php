<?php

namespace App\Jobs\AutoBilling;

use Carbon\Carbon;
use App\Jobs\SendSms;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\AutoBillingSchedule;
use App\Traits\MessageCrafterTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Repositories\PricingPlanRepository;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class StartAutoBilling implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MessageCrafterTrait;

    /**
     *  AutoBillingSchedule instance.
     *
     *  @var \App\Models\AutoBillingSchedule
     */
    protected $autoBillingSchedule;

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId()
    {
        return $this->autoBillingSchedule->id;
    }

    /**
     * Create a new job instance.
     *
     * @param App\Models\AutoBillingSchedule $autoBillingSchedule
     *
     * @return void
     */
    public function __construct(AutoBillingSchedule $autoBillingSchedule)
    {
        $this->autoBillingSchedule = $autoBillingSchedule;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{

            $qualifiedActive = $this->autoBillingSchedule->active;
            $qualifiedNextAttemptDate = Carbon::parse($this->autoBillingSchedule->next_attempt_date)->lessThanOrEqualTo(now()) &&
                                        Carbon::parse($this->autoBillingSchedule->next_attempt_date)->greaterThanOrEqualTo(now()->subDays(2));

            if($qualifiedActive && $qualifiedNextAttemptDate) {

                $userId = $this->autoBillingSchedule->user_id;
                $storeId = $this->autoBillingSchedule->store_id;
                $aiAssistantId = $this->autoBillingSchedule->ai_assistant_id;
                $pricingPlanId = $this->autoBillingSchedule->pricing_plan_id;
                $paymentMethodId = $this->autoBillingSchedule->payment_method_id;

                $data = [
                    'auto_bill' => true,
                    'user_id' => $userId,
                    'store_id' => $storeId,
                    'ai_assistant_id' => $aiAssistantId,
                    'payment_method_id' => $paymentMethodId
                ];

                $result = (new PricingPlanRepository)->authourize()->payPricingPlan($pricingPlanId, $data);
                $successful = $result['successful'];

                if(!$successful) {
                    $this->updateAutoBillingScheduleOnUnsuccessfulAttempt();
                }

            }

        } catch (\Throwable $th) {

            Log::error('StartAutoBilling Job Failed (Stage 1): '. $th->getMessage());

        }
    }

    /**
     *  Update the auto billing schedule on a unsuccessful attempt
     *
     *  @return void
     */
    private function updateAutoBillingScheduleOnUnsuccessfulAttempt()
    {
        try{

            $pricingPlan = $this->autoBillingSchedule->pricingPlan;
            $attempts = ((int) $this->autoBillingSchedule->attempts) + 1;

            /**
             *  @var $active - Whether the auto billing is active for future attempts
             */
            $active = $attempts < $pricingPlan->max_auto_billing_attempts;

            if($active) {

                $totalFailedAttempts = $this->autoBillingSchedule->total_failed_attempts + 1;

                //  Update the existing auto billing schedule
                $this->autoBillingSchedule->update([
                    'active' => $active,
                    'attempts' => $attempts,
                    'next_attempt_date' => now()->addDay(),
                    'total_failed_attempts' => $totalFailedAttempts
                ]);

            }else{

                //  Deactivate the auto billing schedule since the maximum billing attempts have been reached
                StopAutoBilling::dispatch($this->autoBillingSchedule);

            }

        } catch (\Throwable $th) {

            Log::error('StartAutoBilling Job Failed (Stage 2): '. $th->getMessage());

        }
    }
}

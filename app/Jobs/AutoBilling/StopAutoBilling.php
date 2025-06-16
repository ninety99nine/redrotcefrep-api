<?php

namespace App\Jobs\AutoBilling;

use App\Jobs\SendSms;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use App\Models\AutoBillingSchedule;
use App\Traits\MessageCrafterTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class StopAutoBilling implements ShouldQueue, ShouldBeUnique
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
        $this->autoBillingSchedule = $autoBillingSchedule->load(['user', 'store', 'pricingPlan']);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{

            $this->autoBillingSchedule->update([
                'active' => 0,
                'attempts' => 0,
                'next_attempt_date' => null
            ]);

            $user = $this->autoBillingSchedule->user;
            $pricingPlan = $this->autoBillingSchedule->pricingPlan;

            if(!empty($pricingPlan->auto_billing_disabled_sms_message)) {

                $smsMessage = $this->craftAutoBillingDisabledMessage($this->autoBillingSchedule);
                SendSms::dispatch($smsMessage, $user->mobile_number->formatE164());

            }

        } catch (\Throwable $th) {

            Log::error('StopAutoBilling Job Failed: '. $th->getMessage());

        }
    }
}

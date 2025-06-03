<?php

namespace App\Jobs;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use App\Services\Sms\SmsService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Log;

class SendSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $store;
    public $content;
    public $recipientMobileNumber;

    /**
     * Create a new job instance.
     *
     *  @param string $content - The message content to send
     *  @param string $recipientMobileNumber - The number of the recipient to receive the message e.g 26772000001
     *  @param Store|null $store - The store sending the message
     *
     * @return void
     */
    public function __construct($content, $recipientMobileNumber, $store = null)
    {
        Log::info('SendSms __construct()');

        $this->store = $store;
        $this->content = $content;
        $this->recipientMobileNumber = $recipientMobileNumber;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('SendSms handle()');

        $smsEnabled = config('app.SMS_ENABLED');

        if($smsEnabled) {
            SmsService::sendOrangeSms(
                $this->content,
                $this->recipientMobileNumber,
                $this->store
            );
        }
    }
}

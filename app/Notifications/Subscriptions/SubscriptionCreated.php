<?php

namespace App\Notifications\Subscriptions;

use App\Models\Base\BaseModel;
use App\Models\User;
use App\Models\Store;
use App\Models\AiAssistant;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use App\Traits\Base\BaseTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class SubscriptionCreated extends Notification implements ShouldQueue
{
    use Queueable, BaseTrait;

    public User $subscriptionByUser;
    public Transaction $transaction;
    public User $subscriptionForUser;
    public Subscription $subscription;
    public BaseModel $subscriptionFor;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Subscription $subscription, Transaction $transaction, BaseModel $subscriptionFor, User $subscriptionByUser, User $subscriptionForUser)
    {
        $this->transaction = $transaction;
        $this->subscription = $subscription;
        $this->subscriptionByUser = $subscriptionByUser;
        $this->subscriptionForUser = $subscriptionForUser;

        /**
         *  Get the owning resource that this subscription is for i.e Store, Order, e.t.c
         *
         *  @var Model $owner
         */
        $this->subscriptionFor = $subscriptionFor;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $transaction = $this->transaction;
        $subscription = $this->subscription;
        $subscriptionFor = $this->subscriptionFor;
        $subscriptionByUser = $this->subscriptionByUser;
        $subscriptionForUser = $this->subscriptionForUser;

        if($subscriptionFor instanceof Store) {

            //  Get the store name
            $name = $subscriptionFor->name;

        }else if($subscriptionFor instanceof AiAssistant) {

            $name = 'AI Assistant';

        }

        return [
            'subscriptionFor' => [
                'id' => $subscriptionFor->id,
                'name' => $name,
                'type' => $subscriptionFor->getResourceName()
            ],
            'subscription' => [
                'id' => $subscription->id,
                'endAt' => $subscription->end_at,
                'startAt' => $subscription->start_at,
            ],
            'transaction' => [
                'id' => $transaction->id,
                'description' => $transaction->description
            ],
            'subscriptionByUser' => [
                'id' => $subscriptionByUser->id,
                'name' => $subscriptionByUser->name,
                'firstName' => $subscriptionByUser->first_name
            ],
            'subscriptionForUser' => [
                'id' => $subscriptionForUser->id,
                'name' => $subscriptionForUser->name,
                'firstName' => $subscriptionForUser->first_name
            ],
        ];
    }
}

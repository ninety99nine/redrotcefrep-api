<?php

namespace App\Notifications\Users;

use App\Models\User;
use App\Models\Store;
use Illuminate\Bus\Queueable;
use App\Traits\Base\BaseTrait;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class InvitationToJoinStoreTeamDeclined extends Notification implements ShouldQueue
{
    use Queueable, BaseTrait;

    public Store $store;
    public User $declinedByUser;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Store $store, User $declinedByUser)
    {
        $this->store = $store;
        $this->declinedByUser = $declinedByUser;
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
        $store = $this->store;
        $declinedByUser = $this->declinedByUser;

        return [
            'store' => [
                'id' => $store->id,
                'name' => $store->name
            ],
            'declinedByUser' => [
                'id' => $declinedByUser->id,
                'name' => $declinedByUser->name,
                'firstName' => $declinedByUser->first_name
            ],
        ];
    }
}

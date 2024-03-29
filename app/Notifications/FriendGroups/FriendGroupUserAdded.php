<?php

namespace App\Notifications\FriendGroups;

use App\Models\User;
use App\Models\FriendGroup;
use Illuminate\Bus\Queueable;
use App\Traits\Base\BaseTrait;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use NotificationChannels\OneSignal\OneSignalChannel;
use NotificationChannels\OneSignal\OneSignalMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class FriendGroupUserAdded extends Notification implements ShouldQueue
{
    use Queueable, BaseTrait;

    public User $addedUser;
    public User $addedByUser;
    public FriendGroup $friendGroup;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(FriendGroup $friendGroup, User $addedUser, User $addedByUser)
    {
        $this->addedUser = $addedUser;
        $this->addedByUser = $addedByUser;
        $this->friendGroup = $friendGroup;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', OneSignalChannel::class];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $addedUser = $this->addedUser;
        $addedByUser = $this->addedByUser;
        $friendGroup = $this->friendGroup;

        return [
            'friendGroup' => [
                'id' => $friendGroup->id,
                'name' => $friendGroup->name
            ],
            'addedByUser' => [
                'id' => $addedByUser->id,
                'name' => $addedByUser->name,
                'firstName' => $addedByUser->first_name
            ],
            'addedUser' => [
                'id' => $addedUser->id,
                'name' => $addedUser->name,
                'firstName' => $addedUser->first_name
            ],
        ];
    }

    public function toOneSignal(object $notifiable): OneSignalMessage
    {
        $addedUser = $this->addedUser;
        $addedByUser = $this->addedByUser;
        $friendGroup = $this->friendGroup;
        $subject = 'Friend added to group';
        $body = $addedByUser->first_name.' added '.$addedUser->first_name.' to '.$friendGroup->name_with_emoji;

        /**
         *  Image from: https://giphy.com
         */
        return OneSignalMessage::create()
            ->setAndroidBigPicture('https://media.giphy.com/media/jErnybNlfE1lm/giphy.gif')
            ->setSubject($subject)
            ->setBody($body);
    }
}

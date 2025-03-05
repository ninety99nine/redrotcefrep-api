<?php

namespace App\Repositories;

use App\Traits\AuthTrait;
use App\Enums\Association;
use App\Traits\Base\BaseTrait;
use App\Repositories\BaseRepository;
use App\Http\Resources\NotificationResources;
use Illuminate\Notifications\DatabaseNotification;

class NotificationRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show notifications.
     *
     * @param array $data
     * @return NotificationResources|array
     */
    public function showNotifications(array $data = []): NotificationResources|array
    {
        if($this->getQuery() == null) {

            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {
                if(!$this->isAuthourized()); return ['message' => 'You do not have permission to show notifications'];
                $this->setQuery(DatabaseNotification::query()->latest());
            }else {
                $this->setQuery(request()->current_user->notifications()->latest());
            }

        }

        return $this->getOutput();
    }

    /**
     * Mark notifications as read.
     *
     * @return array
     */
    public function markNotificationsAsRead(): array
    {
        request()->current_user->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        return ['marked_as_read' => true, 'message' => 'Marked as read'];
    }

    /**
     * Show notification.
     *
     * @param DatabaseNotification|string|null $notificationId
     * @return DatabaseNotification|array|null
     */
    public function showNotification(DatabaseNotification|string|null $notificationId = null): DatabaseNotification|array|null
    {
        if(($notification = $notificationId) instanceof DatabaseNotification) {
            $notification = $this->applyEagerLoadingOnModel($notification);
        }else {
            $query = $this->getQuery() ?? DatabaseNotification::query();
            if($notificationId) $query = $query->where('notifications.id', $notificationId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $notification = $this->query->first();
        }

        return $this->showResourceExistence($notification);
    }

    /**
     * Mark notification as read.
     *
     * @param string $notificationId
     * @return array
     */
    public function markNotificationAsRead(string $notificationId): array
    {
        $notification = DatabaseNotification::find($notificationId);
        if($notification) {
            if(is_null($notification->read_at)) $notification->update(['read_at' => now()]);
            return ['marked_as_read' => true, 'message' => 'Marked as read'];
        }else{
            return ['marked_as_read' => false, 'message' => 'Notification does not exist'];
        }
    }

    /***********************************************
     *            MISCELLANEOUS METHODS           *
     **********************************************/
}

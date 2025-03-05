<?php

namespace App\Events;

use App\Models\Store;
use App\Http\Resources\StoreResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StoreUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $store;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        //  Count the team members who have joined this store
        $teamMembers = ['teamMembers' => function (Builder $query) {
            $query->joinedTeam();
        }];

        //  Count the followers who are currently following this store
        $followers = ['followers' => function (Builder $query) {
            $query->following();
        }];

        //  Countable relationships
        $countableRelationships = array_merge([$teamMembers, $followers], [
            'orders', 'products', 'promotions', 'reviews'
        ]);

        $products = ['products' => function ($query) {
            $query->isNotVariation()->visible()->orderBy('position', 'asc');
        }];

        $store = $this->store
                    ->with($products)
                    ->withCount($countableRelationships)
                    ->withAvg('reviews as rating', 'rating');

        return (new StoreResource($store))->resolve();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('update.store.'.$this->store->id);
    }
}

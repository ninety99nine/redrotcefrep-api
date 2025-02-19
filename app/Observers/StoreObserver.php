<?php

namespace App\Observers;

use App\Models\Store;
use App\Enums\CacheName;
use App\Models\StoreQuota;
use Illuminate\Support\Str;
use App\Helpers\CacheManager;
use App\Services\QrCode\QrCodeService;
use App\Notifications\Stores\StoreDeleted;
use Illuminate\Support\Facades\Notification;

class StoreObserver
{
    public $teamMembers = [];

    public function saving(Store $store)
    {
        $store = $this->setStoreAlias($store);
        $store = $this->setStoreSocialLinks($store);
    }

    public function creating(Store $store)
    {
        $idBasedWebLink = config('app.FRONTEND_URI').'/'.$store->id;
        $store->qr_code_file_path = QrCodeService::generate($idBasedWebLink);
    }

    public function updating(Store $store)
    {
    }

    public function created(Store $store)
    {
        StoreQuota::create(['store_id' => $store->id]);

        if(!is_null($store->whatsapp_mobile_number)) {
            $store->storeRollingNumbers()->create([
                'mobile_number' => $store->whatsapp_mobile_number
            ]);
        }
    }

    public function updated(Store $store)
    {
    }

    public function deleting(Store $store)
    {
        /**
         *  We need to capture the store team members before the store is deleted.
         *  This is because once the store is deleted, the user and store associations
         *  are automatically deleted based on the cascadeOnDelete relationship that is
         *  set on the user_store_association table schema. This means that while trying
         *  to access the team members on the deleted() event using $store->teamMembers(),
         *  we should expect no results since the relationship would have already been
         *  destroyed. We can capture the team members before deleting the store then
         *  access these same team members after deleting the store. This can be done
         *  by temporarily caching the team members.
         */
        $teamMembers = $store->teamMembers()->joinedTeam()->get();

        //  Cache the team members for one minute before the store is deleted
        (new CacheManager(CacheName::STORE_TEAM_MEMBERS))->append($store->id)->put($teamMembers, now()->addMinute());
    }

    public function deleted(Store $store)
    {
        /**
         *  Delete subscriptions (This is a polymorphic relationship that
         *  we cannot delete at database level using cascadeOnDelete()),
         *  therefore must be deleted at the application level.
         */
        $store->subscriptions()->delete();

        //  Set the cache manager
        $cacheManager = (new CacheManager(CacheName::STORE_TEAM_MEMBERS))->append($store->id);

        //  Retrieve the cached team members
        $teamMembers = $cacheManager->get();

        //  Notify the team members on this store deletion
        Notification::send($teamMembers, new StoreDeleted($store->id, $store->name_with_emoji, request()->auth_user));

        // Forget the cached team members
        $cacheManager->forget();
    }

    public function restored(Store $store)
    {
    }

    public function forceDeleted(Store $store)
    {
    }

    /**
     * Set store alias.
     *
     * @param Store $store
     * @return Store
     */
    private function setStoreAlias(Store $store): Store
    {
        if (empty($store->alias)) {
            $baseAlias = Str::slug($store->name, '-');
            $similarAliases = Store::where('alias', 'like', "{$baseAlias}%")->pluck('alias')->toArray();

            if (!in_array($baseAlias, $similarAliases)) {
                $store->alias = $baseAlias;
            } else {
                $maxSuffix = $this->getMaxSuffix($baseAlias, $similarAliases);
                $store->alias = "{$baseAlias}-" . ($maxSuffix + 1);
            }
        } else {
            $store->alias = Str::slug($store->alias, '-');
        }

        return $store;
    }

    /**
     * Set store social links.
     *
     * @param Store $store
     * @return Store
     */
    private function setStoreSocialLinks(Store $store): Store
    {
        if (!empty($store->social_links)) {
            $store->social_links = collect($store->social_links)->filter(fn($socialLink) => !empty($socialLink['name']) && !empty($socialLink['link']))->toArray();
        }

        return $store;
    }

    /**
     * Get the highest numeric suffix for the base alias.
     *
     * @param string $baseAlias
     * @param array $similarAliases
     * @return int
     */
    private function getMaxSuffix(string $baseAlias, array $similarAliases): int
    {
        $maxSuffix = 0;

        foreach ($similarAliases as $alias) {

            if (preg_match('/^' . preg_quote($baseAlias, '/') . '-(\d+)$/', $alias, $matches)) {
                $suffix = intval($matches[1]);
                $maxSuffix = max($maxSuffix, $suffix);
            }
        }

        return $maxSuffix;
    }
}

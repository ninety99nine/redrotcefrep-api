<?php

namespace App\Repositories;

use App\Models\Store;
use App\Traits\AuthTrait;
use App\Models\SmsMessage;
use App\Traits\Base\BaseTrait;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\SmsMessageResources;
use App\Jobs\SendSms;
use Illuminate\Database\Eloquent\Relations\Relation;

class SmsMessageRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show SMS messages.
     *
     * @return SmsMessageResources|array
     */
    public function showSmsMessages(array $data = []): SmsMessageResources|array
    {
        if($this->getQuery() == null) {

            $storeId = isset($data['store_id']) ? $data['store_id'] : null;

            if($storeId) {
                $store = Store::find($storeId);
                if($store) {
                    $this->setQuery($store->smsMessages()->latest());
                }else{
                    return ['message' => 'This store does not exist'];
                }
            }else {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show SMS messages'];
                $this->setQuery(SmsMessage::query()->latest());
            }
        }

        return $this->getOutput();
    }

    /**
     * Create SMS message.
     *
     * @param array $data
     * @return SmsMessage|array
     */
    public function createSmsMessage(array $data): SmsMessage|array
    {
        if(!$this->isAuthourized()) return ['created' => false, 'message' => 'You do not have permission to create SMS messages'];

        $store = null;

        if(isset($data['store_id'])) {
            $store = Store::find($data['store_id']);
            if(!$store) return ['created' => false, 'message' => 'This store does not exist'];
        }

        $smsMessage = SmsMessage::create($data);

        if($this->isTruthy($data['send_message'])) SendSms::dispatch($smsMessage, $data['recipient_mobile_number'], $store);

        return $this->showCreatedResource($smsMessage);
    }

    /**
     * Delete SMS messages.
     *
     * @param array $smsMessageIds
     * @return array
     */
    public function deleteSmsMessages(array $smsMessageIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete SMS messages'];

        $smsMessages = $this->setQuery(SmsMessage::query())->getSmsMessagesByIds($smsMessageIds);

        if($totalSmsMessages = $smsMessages->count()) {

            foreach($smsMessages as $smsMessage) {
                $smsMessage->delete();
            }

            return ['deleted' => true, 'message' => $totalSmsMessages  .($totalSmsMessages  == 1 ? ' SMS message': ' SMS messages') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No SMS messages deleted'];
        }
    }

    /**
     * Show SMS message.
     *
     * @param SmsMessage|string|null $smsMessageId
     * @return SmsMessage|array|null
     */
    public function showSmsMessage(SmsMessage|string|null $smsMessageId = null): SmsMessage|array|null
    {
        if(($smsMessage = $smsMessageId) instanceof SmsMessage) {
            $smsMessage = $this->applyEagerLoadingOnModel($smsMessage);
        }else {
            $query = $this->getQuery() ?? SmsMessage::query();
            if($smsMessageId) $query = $query->where('sms_messages.id', $smsMessageId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $smsMessage = $this->query->first();
        }

        return $this->showResourceExistence($smsMessage);
    }

    /**
     * Update SMS message.
     *
     * @param SmsMessage|string $smsMessageId
     * @param array $data
     * @return SmsMessage|array
     */
    public function updateSmsMessage(SmsMessage|string $smsMessageId, array $data): SmsMessage|array
    {
        if(!$this->isAuthourized()) return ['updated' => false, 'message' => 'You do not have permission to update SMS message'];

        $smsMessage = $smsMessageId instanceof SmsMessage ? $smsMessageId : SmsMessage::find($smsMessageId);

        if($smsMessage) {

            $smsMessage->update($data);
            return $this->showUpdatedResource($smsMessage);

        }else{
            return ['updated' => false, 'message' => 'This SMS message does not exist'];
        }
    }

    /**
     * Delete SMS message.
     *
     * @param SmsMessage|string $smsMessageId
     * @return array
     */
    public function deleteSmsMessage(SmsMessage|string $smsMessageId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete SMS message'];

        $smsMessage = $smsMessageId instanceof SmsMessage ? $smsMessageId : SmsMessage::find($smsMessageId);

        if($smsMessage) {
            $deleted = $smsMessage->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'SMS message deleted'];
            }else{
                return ['deleted' => false, 'message' => 'SMS message delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This SMS message does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query SMS message by ID.
     *
     * @param string $smsMessageId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function querySmsMessageById(string $smsMessageId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('sms_messages.id', $smsMessageId)->with($relationships);
    }

    /**
     * Get SMS message by ID.
     *
     * @param string $smsMessageId
     * @param array $relationships
     * @return SmsMessage|null
     */
    public function getSmsMessageById(string $smsMessageId, array $relationships = []): SmsMessage|null
    {
        return $this->querySmsMessageById($smsMessageId, $relationships)->first();
    }

    /**
     * Query SMS messages by IDs.
     *
     * @param array<string> $smsMessageId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function querySmsMessagesByIds($smsMessageIds): Builder|Relation
    {
        return $this->query->whereIn('sms_messages.id', $smsMessageIds);
    }

    /**
     * Get SMS messages by IDs.
     *
     * @param array<string> $smsMessageId
     * @param string $relationships
     * @return Collection
     */
    public function getSmsMessagesByIds($smsMessageIds): Collection
    {
        return $this->querySmsMessagesByIds($smsMessageIds)->get();
    }
}

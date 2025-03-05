<?php

namespace App\Repositories;

use stdClass;
use Carbon\Carbon;
use App\Models\User;
use App\Jobs\SendSms;
use App\Traits\AuthTrait;
use App\Models\AiMessage;
use App\Enums\Association;
use App\Models\AiAssistant;
use App\Models\Subscription;
use App\Traits\Base\BaseTrait;
use App\Helpers\PlatformManager;
use App\Models\AiMessageCategory;
use Illuminate\Support\Collection;
use App\Models\AiAssistantTokenUsage;
use App\Services\OpenAi\OpenAiService;
use Illuminate\Database\Eloquent\Builder;
use App\Http\Resources\AiMessageResources;
use Illuminate\Database\Eloquent\Relations\Relation;

class AiMessageRepository extends BaseRepository
{
    use AuthTrait, BaseTrait;

    /**
     * Show AI messages.
     *
     * @return AiMessageResources|array
     */
    public function showAiMessages(array $data = []): AiMessageResources|array
    {
        if($this->getQuery() == null) {

            $userId = isset($data['user_id']) ? $data['user_id'] : null;
            $aiAssistantId = isset($data['ai_assistant_id']) ? $data['ai_assistant_id'] : null;
            $association = isset($data['association']) ? Association::tryFrom($data['association']) : null;

            if($association == Association::SUPER_ADMIN) {
                if(!$this->isAuthourized()) return ['message' => 'You do not have permission to show AI messages'];
                $this->setQuery(AiMessage::latest());
            }else if($userId) {
                $user = User::find($userId);
                if($user) {
                    $isAuthourized = $this->isAuthourized() || $user->id == request()->auth_user->id;
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show AI messages'];

                    $aiAssistant = $user->aiAssistant;
                    if($aiAssistant) {
                        $this->setQuery($aiAssistant->aiMessages()->latest());
                    }else{
                        return ['message' => 'This AI Assistant does not exist'];
                    }
                }else{
                    return ['message' => 'This user does not exist'];
                }
            }else if($aiAssistantId) {
                $aiAssistant = AiAssistant::find($aiAssistantId);
                if($aiAssistant) {
                    $isAuthourized = $this->isAuthourized() || $aiAssistant->user_id == request()->auth_user->id;
                    if(!$isAuthourized) return ['message' => 'You do not have permission to show AI messages'];
                    $this->setQuery($aiAssistant->aiMessages()->latest());
                }else{
                    return ['message' => 'This AI Assistant does not exist'];
                }
            }else{
                $this->setQuery(request()->auth_user->aiMessages()->latest());
            }
        }

        return $this->getOutput();
    }

    /**
     * Create AI message.
     *
     * @param array $data
     * @return AiMessage|array
     */
    public function createAiMessage(array $data): AiMessage|array
    {
        $aiAssistant = AiAssistant::with(['activeSubscription'])->withCount(['subscriptions'])->whereUserId($this->getAuthUser()->id)->first();
        if(!$aiAssistant) $aiAssistant = $this->getAuthUser()->aiAssistant()->create();
        $usageEligibility = $this->assessUsageEligibility($aiAssistant);

        if(!$usageEligibility->proceed) return [
            'created' => false,
            'message' => $usageEligibility->message,
            'can_top_up' => $usageEligibility->can_top_up,
            'can_subscribe' => $usageEligibility->can_subscribe
        ];

        try {

            $aiMessage = $this->promptAssistant($data, $aiAssistant);

        } catch (\OpenAI\Exceptions\ErrorException $th) {

            if($th->getErrorType() == 'insufficient_quota') {
                return ['created' => false, 'message' => 'We’re currently unable to provide a response due to quota limitations on our service. We are working to resolve this soon'];
            }

        }

        if((new PlatformManager)->isSms()) {
            SendSms::dispatch(
                $aiMessage->assistant_content,
                $this->getAuthUser()->mobile_number->formatE164()
            );
        }

        $quota = $this->deductTokensAndUpdateUsage($aiAssistant, $aiMessage, $usageEligibility);

        return [
            'created' => true,
            'quota' => $quota,
            'ai_message' => $aiMessage,
            'message' => $usageEligibility->message,
            'can_top_up' => $usageEligibility->can_top_up,
            'can_subscribe' => $usageEligibility->can_subscribe,
            'subscription' => [
                'subscription_end_at' => $aiAssistant->activeSubscription?->end_at
            ]
        ];
    }

    /**
     * Delete AI messages.
     *
     * @param array $aiMessageIds
     * @return array
     */
    public function deleteAiMessages(array $aiMessageIds): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete AI messages'];
        $aiMessages = $this->setQuery(AiMessage::query())->getAiMessagesByIds($aiMessageIds);

        if($totalAiMessages = $aiMessages->count()) {

            foreach($aiMessages as $aiMessage) {
                $aiMessage->delete();
            }

            return ['deleted' => true, 'message' => $totalAiMessages  .($totalAiMessages  == 1 ? ' AI message': ' AI messages') . ' deleted'];

        }else{
            return ['deleted' => false, 'message' => 'No AI messages deleted'];
        }
    }

    /**
     * Show AI message.
     *
     * @param AiMessage|string|null $aiMessageId
     * @return AiMessage|array|null
     */
    public function showAiMessage(AiMessage|string|null $aiMessageId = null): AiMessage|array|null
    {
        if(($aiMessage = $aiMessageId) instanceof AiMessage) {
            $aiMessage = $this->applyEagerLoadingOnModel($aiMessage);
        }else {
            $query = $this->getQuery() ?? AiMessage::query();
            if($aiMessageId) $query = $query->where('ai_messages.id', $aiMessageId);
            $this->setQuery($query)->applyEagerLoadingOnQuery();
            $aiMessage = $this->query->first();
        }

        return $this->showResourceExistence($aiMessage);
    }

    /**
     * Update AI message.
     *
     * @param string $aiMessageId
     * @param array $data
     * @return AiMessage|array
     */
    public function updateAiMessage(string $aiMessageId, array $data): AiMessage|array
    {
        $aiMessage = AiMessage::with(['aiAssistant' => fn ($aiAssistant) => $aiAssistant->with(['activeSubscription'])->withCount(['subscriptions'])])->find($aiMessageId);

        if($aiMessage) {
            $user = $this->getAuthUser();
            $isAuthourized = $this->isAuthourized() || $aiMessage->ai_assistant_id == $user->aiAssistant?->id;
            if(!$isAuthourized) return ['updated' => false, 'message' => 'You do not have permission to update AI message'];

            $aiAssistant = $aiMessage->aiAssistant;
            $usageEligibility = $this->assessUsageEligibility($aiAssistant);

            if(!$usageEligibility->proceed) return [
                'updated' => false,
                'message' => $usageEligibility->message,
                'can_top_up' => $usageEligibility->can_top_up,
                'can_subscribe' => $usageEligibility->can_subscribe
            ];

            try {

                $aiMessage = $this->promptAssistant($data, $aiAssistant, $aiMessage);

            } catch (\OpenAI\Exceptions\ErrorException $th) {

                if($th->getErrorType() == 'insufficient_quota') {
                    return ['updated' => false, 'message' => 'We’re currently unable to provide a response due to quota limitations on our service. We are working to resolve this soon'];
                }

            }

            if((new PlatformManager)->isSms()) {
                SendSms::dispatch(
                    $aiMessage->assistant_content,
                    $this->getAuthUser()->mobile_number->formatE164()
                );
            }

            $quota = $this->deductTokensAndUpdateUsage($aiAssistant, $aiMessage, $usageEligibility);

            if(!$this->checkIfHasRelationOnRequest('aiAssistant')) $aiMessage->unsetRelation('aiAssistant');

            return [
                'updated' => true,
                'quota' => $quota,
                'ai_message' => $aiMessage,
                'message' => $usageEligibility->message,
                'can_top_up' => $usageEligibility->can_top_up,
                'can_subscribe' => $usageEligibility->can_subscribe,
                'subscription' => [
                    'subscription_end_at' => $aiAssistant->activeSubscription?->end_at
                ]
            ];

        }else{
            return ['updated' => false, 'message' => 'This AI message does not exist'];
        }
    }

    /**
     * Delete AI message.
     *
     * @param string $aiMessageId
     * @return array
     */
    public function deleteAiMessage(string $aiMessageId): array
    {
        if(!$this->isAuthourized()) return ['deleted' => false, 'message' => 'You do not have permission to delete AI message'];
        $aiMessage = AiMessage::find($aiMessageId);

        if($aiMessage) {
            $deleted = $aiMessage->delete();

            if ($deleted) {
                return ['deleted' => true, 'message' => 'AI message deleted'];
            }else{
                return ['deleted' => false, 'message' => 'AI message delete unsuccessful'];
            }
        }else{
            return ['deleted' => false, 'message' => 'This AI message does not exist'];
        }
    }

    /***********************************************
     *             MISCELLANEOUS METHODS           *
     **********************************************/

    /**
     * Query AI message by ID.
     *
     * @param string $aiMessageId
     * @param array $relationships
     * @return Builder|Relation
     */
    public function queryAiMessageById(string $aiMessageId, array $relationships = []): Builder|Relation
    {
        return $this->query->where('ai_messages.id', $aiMessageId)->with($relationships);
    }

    /**
     * Get AI message by ID.
     *
     * @param string $aiMessageId
     * @param array $relationships
     * @return AiMessage|null
     */
    public function getAiMessageById(string $aiMessageId, array $relationships = []): AiMessage|null
    {
        return $this->queryAiMessageById($aiMessageId, $relationships)->first();
    }

    /**
     * Query AI messages by IDs.
     *
     * @param array<string> $aiMessageId
     * @param string $relationships
     * @return Builder|Relation
     */
    public function queryAiMessagesByIds($aiMessageIds): Builder|Relation
    {
        return $this->query->whereIn('ai_messages.id', $aiMessageIds);
    }

    /**
     * Get AI messages by IDs.
     *
     * @param array<string> $aiMessageId
     * @param string $relationships
     * @return Collection
     */
    public function getAiMessagesByIds($aiMessageIds): Collection
    {
        return $this->queryAiMessagesByIds($aiMessageIds)->get();
    }

    /**
     * Assess usage eligibility.
     *
     * @param AiAssistant $aiAssistant
     * @return stdClass
     */
    public function assessUsageEligibility(AiAssistant $aiAssistant): stdClass
    {
        $response = new stdClass;
        $response->message = null;
        $response->proceed = false;
        $response->can_top_up = false;
        $response->can_subscribe = false;
        $response->use_free_tokens = false;
        $response->use_paid_tokens = false;
        $response->use_paid_top_up_tokens = false;

        $activeSubscription = $aiAssistant->activeSubscription;
        $hasRemainingFreeTokens = $aiAssistant->remaining_free_tokens > 0;

        if(!$hasRemainingFreeTokens) {
            if($activeSubscription) {

                $hasRemainingTokenUsageToDate = $this->hasRemainingTokenUsageToDate($aiAssistant);

                if(!$hasRemainingTokenUsageToDate) {

                    $hasRemainingPaidTopUpTokens = $aiAssistant->remaining_paid_top_up_tokens > 0;

                    if(!$hasRemainingPaidTopUpTokens) {

                        $response->can_top_up = true;

                        if($this->hasRemainingSubscribedDays($aiAssistant)) {
                            $date = now()->copy()->addDay()->startOfDay();
                            $response->message = 'You have reached your daily limit. Please top up to continue or come back in ' . $this->timeLeftToDate($date);
                        }else{
                            $date = $activeSubscription->end_at->copy();
                            $response->message = 'You have reached your daily limit. Please top up to continue or subscribe again in ' . $this->timeLeftToDate($date);
                        }

                    }else{
                        $response->proceed = true;
                        $response->use_paid_top_up_tokens = true;
                    }

                }else{
                    $response->proceed = true;
                    $response->use_paid_tokens = true;
                }

            }else{

                $response->can_subscribe = true;
                $hasNeverSubscribed = $aiAssistant->subscriptions_count == 0;

                if($hasNeverSubscribed) {
                    $response->message = 'You do not have a subscription, please subscribe.';
                }else{
                    $response->message = 'Your subscription has ended. Please subscribe to continue.';
                }

            }
        }else{
            $response->proceed = true;
            $response->use_free_tokens = true;
        }

        return $response;
    }

    /**
     * Prompt assistant.
     *
     * @param array $data
     * @param AiAssistant $aiAssistant
     * @param AiMessage|null $aiMessage
     * @return AiMessage
     * @throws \OpenAI\Exceptions\ErrorException
     */
    private function promptAssistant(array $data, AiAssistant $aiAssistant, AiMessage|null $aiMessage = null): AiMessage
    {
        $userContent = $data['user_content'];
        $aiMessageCategory = isset($data['ai_message_category_id'])? AiMessageCategory::find($data['ai_message_category_id']): null;
        $messages = [$this->getSystemMessage($aiMessageCategory), ...$this->getPreviousMessages()];

        return (new OpenAiService)->prompt($userContent, $messages, $aiAssistant->id, $aiMessageCategory?->id, $aiMessage);
    }

    /**
     * Deduct tokens and update usage.
     *
     * @param AiAssistant $aiAssistant
     * @param AiMessage $aiMessage
     * @param stdClass $usageEligibility
     * @return array
     */
    private function deductTokensAndUpdateUsage(AiAssistant $aiAssistant, AiMessage $aiMessage, stdClass $usageEligibility): array
    {
        $totalTokensUsed = $aiMessage->total_tokens;
        $remainingFreeTokensBefore = $remainingFreeTokensAfter = $aiAssistant->remaining_free_tokens;
        $remainingPaidTokensBefore = $remainingPaidTokensAfter = $aiAssistant->remaining_paid_tokens;
        $remainingPaidTopUpTokensBefore = $remainingPaidTopUpTokensAfter = $aiAssistant->remaining_paid_top_up_tokens;

        // Deduct tokens based on usage
        if ($usageEligibility->use_free_tokens) {
            $remainingFreeTokensAfter -= $totalTokensUsed;
            if ($remainingFreeTokensAfter < 0) {
                // Handle overflow to paid top_up tokens
                $remainingPaidTopUpTokensAfter += $remainingFreeTokensAfter;
                $remainingFreeTokensAfter = 0;
                if ($remainingPaidTopUpTokensAfter < 0) {
                    // Handle overflow to paid tokens
                    $remainingPaidTokensAfter += $remainingPaidTopUpTokensAfter;
                    $remainingPaidTopUpTokensAfter = 0;
                }
            }
        } elseif ($usageEligibility->use_paid_top_up_tokens) {
            $remainingPaidTopUpTokensAfter -= $totalTokensUsed;
            if ($remainingPaidTopUpTokensAfter < 0) {
                // Handle overflow to paid tokens
                $remainingPaidTokensAfter += $remainingPaidTopUpTokensAfter;
                $remainingPaidTopUpTokensAfter = 0;
            }
        } elseif ($usageEligibility->use_paid_tokens) {
            $remainingPaidTokensAfter -= $totalTokensUsed;
        }

        // Update the AI Assistant's token balances
        $aiAssistant->update([
            'remaining_free_tokens' => $remainingFreeTokensAfter,
            'remaining_paid_tokens' => $remainingPaidTokensAfter,
            'remaining_paid_top_up_tokens' => $remainingPaidTopUpTokensAfter
        ]);

        // Record daily usage
        AiAssistantTokenUsage::create([
            'ai_assistant_id' => $aiAssistant->id,
            'request_tokens_used' => $aiMessage->prompt_tokens,
            'response_tokens_used' => $aiMessage->completion_tokens,
            'free_tokens_used' => $remainingFreeTokensBefore - $remainingFreeTokensAfter,
            'paid_tokens_used' => $remainingPaidTokensBefore - $remainingPaidTokensAfter,
            'paid_top_up_tokens_used' => $remainingPaidTopUpTokensBefore - $remainingPaidTopUpTokensAfter
        ]);

        return [
            'total_paid_tokens' => $aiAssistant->total_paid_tokens,
            'remaining_free_tokens' => $remainingFreeTokensAfter,
            'remaining_paid_tokens' => $remainingPaidTokensAfter,
            'remaining_paid_top_up_tokens' => $remainingPaidTopUpTokensAfter,
        ];
    }

    /**
     * Total subscribed days.
     *
     * @param Subscription $activeSubscription
     * @return int
     */
    private function totalSubscribedDays(Subscription $activeSubscription): int
    {
        return Carbon::parse($activeSubscription->start_at)->diffInDays($activeSubscription->end_at) ?? 1;
    }

    /**
     * Total used days.
     *
     * @param Subscription $activeSubscription
     * @return int
     */
    private function totalUsedDays(Subscription $activeSubscription): int
    {
        return Carbon::parse($activeSubscription->start_at->startOfDay())->diffInDays(now()->startOfDay()->addDays(1));
    }

    /**
     * Has remaining subscribed days.
     *
     * @param AiAssistant $aiAssistant
     * @return bool
     */
    private function hasRemainingSubscribedDays(AiAssistant $aiAssistant): bool
    {
        $activeSubscription = $aiAssistant->activeSubscription;

        $totalRemainingDays = $this->totalSubscribedDays($activeSubscription) - $this->totalUsedDays($activeSubscription);
        return $totalRemainingDays > 0;
    }

    /**
     * Has remaining token usage to date.
     *
     * @param AiAssistant $aiAssistant
     * @return bool
     */
    private function hasRemainingTokenUsageToDate(AiAssistant $aiAssistant): bool
    {
        $activeSubscription = $aiAssistant->activeSubscription;

        $tokensPerDay = $aiAssistant->total_paid_tokens / $this->totalSubscribedDays($activeSubscription);
        $maxAllowedTokenUsageToDate = $tokensPerDay * $this->totalUsedDays($activeSubscription);

        $dailyUsage = AiAssistantTokenUsage::where('ai_assistant_id', $aiAssistant->id)
                                            ->where('created_at', '>=', $activeSubscription->start_at)
                                            ->where('created_at', '<=', now())->get();

        $totalUsedTokensToDate = collect($dailyUsage)->sum(function ($record) {
            return $record['paid_tokens_used'];
        });

        $remainingTokenUsageToDate = $maxAllowedTokenUsageToDate - $totalUsedTokensToDate;

        return $remainingTokenUsageToDate > 0;
    }

    /**
     * Get system message.
     *
     * @param AiMessageCategory|null $aiMessageCategory
     * @return array
     */
    private function getSystemMessage(AiMessageCategory|null $aiMessageCategory): array
    {
        $content = $aiMessageCategory?->system_prompt ?? 'Your name is Perfect Assistant and you are an expert consultant assisting businesses in Botswana.';

        return [
            'role' => 'system',
            'content' => $content
        ];
    }

    /**
     * Get previous messages.
     *
     * @return array
     */
    private function getPreviousMessages(): array
    {
        $messages = [];

        $previousAiMessages = $this->getAuthUser()->aiMessages()->latest()->take(5)->get();

        foreach($previousAiMessages->reverse() as $previousAiMessage) {

            $userContent = $previousAiMessage->user_content;
            $assistantContent = $previousAiMessage->assistant_content;

            if (strlen($userContent) > 200) $userContent = substr($userContent, 0, 200);
            if (strlen($assistantContent) > 200) $assistantContent = substr($assistantContent, 0, 200);

            array_push($messages, [
                'role' => 'user',
                'content' => $userContent
            ]);

            array_push($messages, [
                'role' => 'assistant',
                'content' => $assistantContent
            ]);

        }

        return $messages;
    }

    /**
     * Time left to date.
     *
     * @param Carbon $date
     * @return string
     */
    private function timeLeftToDate(Carbon $date): string
    {
        $hoursUntilLater = now()->diffInHours($date);
        $minutesUntilLater = now()->diffInMinutes($date);
        $secondsUntilLater = now()->diffInSeconds($date);

        if($hoursUntilLater > 0) {
            return $hoursUntilLater . ($hoursUntilLater == 1 ? ' hour' : ' hours');
        }else if($minutesUntilLater > 0) {
            return $minutesUntilLater . ($minutesUntilLater == 1 ? ' minute' : ' minutes');
        }else if($secondsUntilLater > 0) {
            return $secondsUntilLater . ($secondsUntilLater == 1 ? ' seconds' : ' seconds');
        }
    }
}

<?php

namespace App\Services\OpenAi;

use OpenAI;
use App\Models\AiMessage;

class OpenAiService
{
    protected $openai;

    public function __construct()
    {
        $this->openai = OpenAI::factory()->withApiKey(config('app.OPENAI_API_KEY'))->make();
    }

    /**
     * Prompt.
     *
     * @param string $userContent
     * @param array $messages
     * @param string $aiAssistantId
     * @param string|null $aiMessageCategoryId
     * @param AiMessage|null $aiMessage
     * @return AiMessage
     * @throws \OpenAI\Exceptions\ErrorException
     */
    public function prompt(string $userContent, array $messages = [], string $aiAssistantId, string|null $aiMessageCategoryId = null, AiMessage|null $aiMessage = null): AiMessage
    {
        $requestAt = now();
        $messages = count($messages) ? $messages : [
            [
                'role' => 'system',
                'content' => 'You are an assistant.',
            ]
        ];

        $response = $this->openai->chat()->create([
            'model' => config('app.OPENAI_API_MODEL'),
            'messages' => [
                ...$messages,
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
        ]);

        $totalTokens = $response->usage->totalTokens;
        $promptTokens = $response->usage->promptTokens;
        $content = $response->choices[0]->message->content;
        $completionTokens = $response->usage->completionTokens;

        $data = [
            'response_at' => now(),
            'request_at' => $requestAt,
            'user_content' => $userContent,
            'total_tokens' => $totalTokens,
            'assistant_content' => $content,
            'prompt_tokens' => $promptTokens,
            'ai_assistant_id' => $aiAssistantId,
            'completion_tokens' => $completionTokens,
            'ai_message_category_id' => $aiMessageCategoryId,
        ];

        if($aiMessage) {
            $aiMessage->update($data);
        }else{
            $aiMessage = AiMessage::create($data);
        }

        return $aiMessage;
    }
}

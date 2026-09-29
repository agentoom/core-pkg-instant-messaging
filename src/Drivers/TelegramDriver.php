<?php

namespace Agentoom\InstantMessaging\Drivers;

use Agentoom\InstantMessaging\Contracts\InstantMessagingDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class TelegramDriver implements InstantMessagingDriver
{
    protected ?string $token = null;

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getServiceName(): string
    {
        return 'telegram';
    }

    public function handleWebhook(Request $request): mixed
    {
        $payload = $request->all();

        if (! isset($payload['message'])) {
            return null;
        }

        $message = $payload['message'];
        $externalChatId = (string) $message['chat']['id'];
        $text = $message['text'] ?? '';
        $from = $message['from'] ?? [];
        $name = trim(($from['first_name'] ?? '').' '.($from['last_name'] ?? ''));

        return [
            'external_chat_id' => $externalChatId,
            // Telegram retries deliveries; update_id is what makes a retry
            // recognisable as the same message.
            'external_message_id' => isset($payload['update_id']) ? (string) $payload['update_id'] : null,
            'text' => $text,
            'user_data' => [
                'name' => $name ?: "Telegram User {$externalChatId}",
                'username' => $from['username'] ?? null,
            ],
        ];
    }

    public function sendMessage(string $externalChatId, string $message, array $attachments = []): bool
    {
        $token = $this->token ?: config('agentoom-instant-messaging.telegram.default_token');

        if (! $token) {
            Log::error('Telegram token not configured');

            return false;
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $externalChatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Telegram API error: '.$e->getMessage());

            return false;
        }
    }
}

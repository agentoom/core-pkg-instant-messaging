<?php

namespace Agentoom\InstantMessaging\Drivers;

use Agentoom\Core\Models\Configuration;
use Agentoom\InstantMessaging\Contracts\InstantMessagingDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppDriver implements InstantMessagingDriver
{
    public function getServiceName(): string
    {
        return 'whatsapp';
    }

    /**
     * Whether an administrator has switched this channel on.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->credentials()['enabled'] ?? false);
    }

    public function handleWebhook(Request $request): mixed
    {
        $payload = $request->all();

        // Simplified view of the WhatsApp Business API payload.
        if (! isset($payload['entry'][0]['changes'][0]['value']['messages'][0])) {
            return null;
        }

        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0];
        $externalChatId = (string) $message['from']; // Phone number
        $text = $message['text']['body'] ?? '';
        $name = $payload['entry'][0]['changes'][0]['value']['contacts'][0]['profile']['name'] ?? "WhatsApp User {$externalChatId}";

        return [
            'external_chat_id' => $externalChatId,
            'external_message_id' => isset($message['id']) ? (string) $message['id'] : null,
            'text' => $text,
            'user_data' => [
                'name' => $name,
            ],
        ];
    }

    /**
     * Validate Meta's X-Hub-Signature-256 header against the app secret.
     * Without it anyone who knows an agent id can inject messages.
     */
    public function verifySignature(Request $request): bool
    {
        $secret = $this->appSecret();

        if (! $secret) {
            Log::error('WhatsApp app secret not configured; rejecting inbound webhook');

            return false;
        }

        $signature = $request->header('X-Hub-Signature-256', '');

        if (! is_string($signature) || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Token Meta must echo back during the webhook verification handshake.
     */
    public function verifyToken(): ?string
    {
        return $this->credentials()['verify_token']
            ?? config('agentoom-instant-messaging.whatsapp.verify_token');
    }

    /**
     * Meta app secret used to sign inbound webhook payloads.
     */
    public function appSecret(): ?string
    {
        return $this->credentials()['app_secret']
            ?? config('agentoom-instant-messaging.whatsapp.app_secret');
    }

    public function sendMessage(string $externalChatId, string $message, array $attachments = []): bool
    {
        $credentials = $this->credentials();
        $token = $credentials['api_key'] ?? null;
        $phoneId = $credentials['phone_number_id'] ?? null;

        if (! $token || ! $phoneId) {
            Log::error('WhatsApp not configured');

            return false;
        }

        try {
            $response = Http::withToken($token)
                ->post("https://graph.facebook.com/v17.0/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $externalChatId,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('WhatsApp API error: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function credentials(): array
    {
        return Configuration::getValue('instant_messaging_whatsapp', []) ?? [];
    }
}

<?php

namespace Agentoom\InstantMessaging\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\Core\Tests\TestCase;
use Agentoom\InstantMessaging\Messenger;
use Agentoom\InstantMessaging\Models\TelegramBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

/**
 * Inbound channel webhooks are public endpoints: anyone who guesses a bot or
 * agent id can post to them. These tests pin the verification that stands
 * between the open internet and an agent run.
 */
class WebhookSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const WHATSAPP_SECRET = 'test-app-secret';

    public function test_telegram_webhook_without_a_secret_header_is_rejected(): void
    {
        $bot = $this->telegramBot();

        $this->postJson("/api/instant-messaging/webhook/telegram-bot/{$bot->id}", [
            'update_id' => 1,
            'message' => ['chat' => ['id' => 55], 'text' => 'hello', 'from' => ['first_name' => 'Ann']],
        ])->assertForbidden();
    }

    public function test_telegram_webhook_with_a_wrong_secret_is_rejected(): void
    {
        $bot = $this->telegramBot();

        $this->postJson(
            "/api/instant-messaging/webhook/telegram-bot/{$bot->id}",
            ['update_id' => 1, 'message' => ['chat' => ['id' => 55], 'text' => 'hello', 'from' => []]],
            ['X-Telegram-Bot-Api-Secret-Token' => 'not-the-secret']
        )->assertForbidden();
    }

    public function test_telegram_webhook_accepts_a_matching_secret(): void
    {
        $bot = $this->telegramBot();
        $this->fakeMessenger();

        $this->postJson(
            "/api/instant-messaging/webhook/telegram-bot/{$bot->id}",
            ['update_id' => 7, 'message' => ['chat' => ['id' => 55], 'text' => 'hello', 'from' => ['first_name' => 'Ann']]],
            ['X-Telegram-Bot-Api-Secret-Token' => $bot->webhook_secret]
        )->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_telegram_webhook_ignores_a_retried_delivery(): void
    {
        $bot = $this->telegramBot();

        // Telegram retries the same update_id when it does not see a fast 200.
        $messenger = $this->fakeMessenger(expects: 1);

        $payload = ['update_id' => 42, 'message' => ['chat' => ['id' => 55], 'text' => 'hello', 'from' => []]];
        $headers = ['X-Telegram-Bot-Api-Secret-Token' => $bot->webhook_secret];

        $this->postJson("/api/instant-messaging/webhook/telegram-bot/{$bot->id}", $payload, $headers)->assertOk();
        $this->postJson("/api/instant-messaging/webhook/telegram-bot/{$bot->id}", $payload, $headers)->assertOk();

        $this->assertTrue(true, (string) $messenger);
    }

    public function test_whatsapp_webhook_without_a_signature_is_rejected(): void
    {
        $agent = Agent::factory()->create();
        $this->enableWhatsApp();

        $this->postJson("/api/instant-messaging/webhook/whatsapp/{$agent->id}", $this->whatsAppPayload())
            ->assertForbidden();
    }

    public function test_whatsapp_webhook_with_a_bad_signature_is_rejected(): void
    {
        $agent = Agent::factory()->create();
        $this->enableWhatsApp();

        $this->call(
            'POST',
            "/api/instant-messaging/webhook/whatsapp/{$agent->id}",
            [],
            [],
            [],
            ['HTTP_X-Hub-Signature-256' => 'sha256=deadbeef', 'CONTENT_TYPE' => 'application/json'],
            json_encode($this->whatsAppPayload())
        )->assertForbidden();
    }

    public function test_whatsapp_webhook_accepts_a_valid_signature(): void
    {
        $agent = Agent::factory()->create();
        $this->enableWhatsApp();
        $this->fakeMessenger();

        $body = json_encode($this->whatsAppPayload());

        $this->call(
            'POST',
            "/api/instant-messaging/webhook/whatsapp/{$agent->id}",
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, self::WHATSAPP_SECRET),
                'CONTENT_TYPE' => 'application/json',
            ],
            $body
        )->assertOk()->assertJson(['status' => 'ok']);
    }

    public function test_whatsapp_webhook_is_ignored_while_the_channel_is_disabled(): void
    {
        $agent = Agent::factory()->create();
        $this->enableWhatsApp(enabled: false);
        $this->fakeMessenger(expects: 0);

        $body = json_encode($this->whatsAppPayload());

        $this->call(
            'POST',
            "/api/instant-messaging/webhook/whatsapp/{$agent->id}",
            [],
            [],
            [],
            [
                'HTTP_X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, self::WHATSAPP_SECRET),
                'CONTENT_TYPE' => 'application/json',
            ],
            $body
        )->assertOk()->assertJson(['status' => 'channel_disabled']);
    }

    public function test_whatsapp_verification_handshake_echoes_the_challenge(): void
    {
        $agent = Agent::factory()->create();
        $this->enableWhatsApp();

        $this->get("/api/instant-messaging/webhook/whatsapp/{$agent->id}?hub_verify_token=verify-me&hub_challenge=12345")
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_whatsapp_verification_handshake_rejects_a_wrong_token(): void
    {
        $agent = Agent::factory()->create();
        $this->enableWhatsApp();

        $this->get("/api/instant-messaging/webhook/whatsapp/{$agent->id}?hub_verify_token=wrong&hub_challenge=12345")
            ->assertForbidden();
    }

    private function telegramBot(): TelegramBot
    {
        return TelegramBot::create([
            'user_id' => User::factory()->create()->id,
            'agent_id' => Agent::factory()->create()->id,
            'name' => 'Security Bot',
            'token' => '123456:token',
            'webhook_secret' => 'a-very-secret-value',
            'is_active' => true,
        ]);
    }

    private function enableWhatsApp(bool $enabled = true): void
    {
        config(['agentoom-instant-messaging.whatsapp.app_secret' => self::WHATSAPP_SECRET]);

        Configuration::setValue('instant_messaging_whatsapp', [
            'enabled' => $enabled,
            'api_key' => 'test-token',
            'phone_number_id' => '123',
            'verify_token' => 'verify-me',
            'app_secret' => self::WHATSAPP_SECRET,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsAppPayload(): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'contacts' => [['profile' => ['name' => 'Ann']]],
                        'messages' => [['id' => 'wamid.TEST', 'from' => '447700900000', 'text' => ['body' => 'hello']]],
                    ],
                ]],
            ]],
        ];
    }

    private function fakeMessenger(int $expects = 1): Messenger
    {
        $mock = Mockery::mock(Messenger::class);
        $mock->shouldReceive('handleIncomingMessage')->times($expects);

        $this->app->instance(Messenger::class, $mock);

        return $mock;
    }
}

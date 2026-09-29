<?php

namespace Agentoom\InstantMessaging\Http\Controllers;

use Agentoom\Core\Models\Agent;
use Agentoom\InstantMessaging\Drivers\TelegramDriver;
use Agentoom\InstantMessaging\Drivers\WhatsAppDriver;
use Agentoom\InstantMessaging\Messenger;
use Agentoom\InstantMessaging\Models\TelegramBot;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Meta's webhook verification handshake: echo back hub.challenge when the
     * verify token matches.
     */
    public function verify(Request $request, string $service, int $agentId)
    {
        if ($service !== 'whatsapp') {
            abort(404);
        }

        $driver = app(WhatsAppDriver::class);
        $expected = $driver->verifyToken();
        $provided = (string) $request->query('hub_verify_token', '');
        $challenge = $request->query('hub_challenge');

        if (! $expected || $challenge === null || ! hash_equals((string) $expected, $provided)) {
            abort(403);
        }

        return response($challenge, 200)->header('Content-Type', 'text/plain');
    }

    public function handle(Request $request, string $service, int $agentId)
    {
        if ($service !== 'whatsapp') {
            abort(404);
        }

        $driver = app(WhatsAppDriver::class);

        if (! $driver->isEnabled()) {
            return response()->json(['status' => 'channel_disabled']);
        }

        if (! $driver->verifySignature($request)) {
            Log::warning('WhatsApp webhook rejected: signature verification failed');

            abort(403);
        }

        $agent = Agent::findOrFail($agentId);
        $data = $driver->handleWebhook($request);

        if ($data && ! $this->isDuplicate($service, $data['external_message_id'] ?? null)) {
            app(Messenger::class)->handleIncomingMessage($driver, $data, $agent);
        }

        return response()->json(['status' => 'ok']);
    }

    public function handleTelegram(Request $request, int $botId)
    {
        $bot = TelegramBot::findOrFail($botId);

        if (! $bot->is_active) {
            return response()->json(['status' => 'bot_inactive']);
        }

        if (! $this->hasValidTelegramSecret($request, $bot)) {
            Log::warning('Telegram webhook rejected: secret token mismatch', ['bot_id' => $bot->id]);

            abort(403);
        }

        $driver = app(TelegramDriver::class);
        $driver->setToken($bot->token);

        $data = $driver->handleWebhook($request);

        if ($data && ! $this->isDuplicate('telegram', $data['external_message_id'] ?? null)) {
            app(Messenger::class)->handleIncomingMessage($driver, $data, $bot->agent, $bot);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Telegram echoes back whatever secret_token was registered with the
     * webhook. A bot without one cannot be verified, so its webhook is refused
     * until "Set Webhook" is run from the settings page again.
     */
    protected function hasValidTelegramSecret(Request $request, TelegramBot $bot): bool
    {
        if (empty($bot->webhook_secret)) {
            return false;
        }

        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        return is_string($provided) && hash_equals($bot->webhook_secret, $provided);
    }

    /**
     * Both providers retry deliveries. Treat a repeat of a message we have
     * already handled as a no-op rather than a second AI reply.
     */
    protected function isDuplicate(string $service, ?string $externalMessageId): bool
    {
        if (empty($externalMessageId)) {
            return false;
        }

        $ttl = (int) config('agentoom-instant-messaging.dedupe_ttl_minutes', 60);

        // Cache::add only succeeds the first time a key is seen.
        return ! Cache::add("im:inbound:{$service}:{$externalMessageId}", true, now()->addMinutes($ttl));
    }
}

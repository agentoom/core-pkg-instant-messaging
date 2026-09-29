<?php

namespace Agentoom\InstantMessaging;

use Agentoom\Core\Ai\Agents\DynamicAgent;
use Agentoom\Core\Contracts\HumanIntervention;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\User;
use Agentoom\Core\Services\InjectCurrentAiConfigService;
use Agentoom\InstantMessaging\Contracts\InstantMessagingDriver;
use Agentoom\InstantMessaging\Models\TelegramBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Runs an inbound channel message through an agent and replies on the channel.
 *
 * The conversation is a first-class core conversation, so it shows up in the
 * dashboard chat and in the live-support console like any other.
 */
class Messenger
{
    public function __construct(
        protected HumanIntervention $humanIntervention
    ) {}

    public function handleIncomingMessage(InstantMessagingDriver $driver, array $data, Agent $agent, ?TelegramBot $bot = null): void
    {
        $externalChatId = $data['external_chat_id'];
        $text = $data['text'];
        $userData = $data['user_data'];

        // 1. Get or create user and conversation
        $user = $this->resolveUser($driver, $externalChatId, $userData, $bot);
        $conversationId = $this->resolveConversationId($user, $agent, $driver);

        // 2. A human may already own this conversation: log it and stop.
        if ($this->humanIntervention->isActive($conversationId)) {
            Log::debug("Human intervention active for conversation {$conversationId}. Skipping AI.");

            return;
        }

        // 3. Honour explicit handoff requests in the user's own words.
        if ($this->humanIntervention->shouldTrigger($text, $agent)) {
            if ($this->humanIntervention->startIfAvailable($conversationId)) {
                $driver->sendMessage($externalChatId, __('agentoom-core::admin.human_intervention.handoff_started'));

                return;
            }

            $driver->sendMessage($externalChatId, __('agentoom-core::admin.human_intervention.offline', [
                'hours' => $this->humanIntervention->getOperationalHoursString(),
            ]));
        }

        // 4. AI Response
        $this->processAiResponse($agent, $conversationId, $user, $text, $driver, $externalChatId, $bot);
    }

    protected function resolveUser(InstantMessagingDriver $driver, string $externalChatId, array $userData, ?TelegramBot $bot = null): User
    {
        $query = DB::table('user_integrations')
            ->where('service', $driver->getServiceName())
            ->where('data->external_id', $externalChatId);

        if ($bot) {
            $query->where('data->bot_id', $bot->id);
        }

        $integration = $query->first();

        if ($integration) {
            return User::find($integration->user_id);
        }

        $botSuffix = $bot ? "_{$bot->id}" : '';
        $email = "{$driver->getServiceName()}{$botSuffix}_{$externalChatId}@agentoom.internal";

        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $email,
                'password' => bcrypt(Str::random(16)),
            ]);
        }

        $integrationData = array_merge($userData, ['external_id' => $externalChatId]);
        if ($bot) {
            $integrationData['bot_id'] = $bot->id;
        }

        DB::table('user_integrations')->insert([
            'user_id' => $user->id,
            'service' => $driver->getServiceName(),
            'data' => json_encode($integrationData),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    protected function resolveConversationId(User $user, Agent $agent, InstantMessagingDriver $driver): string
    {
        $conversationId = DB::table('agent_conversations')
            ->where('user_id', $user->id)
            ->where('agent_id', $agent->id)
            ->orderBy('updated_at', 'desc')
            ->value('id');

        if ($conversationId) {
            return $conversationId;
        }

        $id = (string) Str::uuid7();
        DB::table('agent_conversations')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'title' => "Chat via {$driver->getServiceName()}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    protected function processAiResponse(Agent $agentModel, string $conversationId, User $user, string $text, InstantMessagingDriver $driver, string $externalChatId, ?TelegramBot $bot = null): void
    {
        InjectCurrentAiConfigService::inject($agentModel);
        app()->instance('current_agent_id', $agentModel->id);

        $extraInstructions = null;
        if ($bot && $bot->user) {
            $extraInstructions = "## Bot Owner Context
You are currently responding through a bot owned by {$bot->user->name} ({$bot->user->email}).
You have access to their information and should act as their representative when appropriate.";
        }

        $agent = new DynamicAgent($agentModel, extraInstructions: $extraInstructions);
        $agent->continue($conversationId, $user);

        // Telegram and WhatsApp prefer complete messages over streamed deltas.
        $response = $agent->prompt($text);

        // Handle the handoff tool result.
        foreach ($response->toolResults as $result) {
            $handoff = $this->humanIntervention->handoffFromToolResult($result->name, $result->result, $agentModel);

            if ($handoff === null) {
                continue;
            }

            if ($this->humanIntervention->startIfAvailable($conversationId, $handoff['reason'])) {
                $driver->sendMessage($externalChatId, $handoff['message'] ?? __('agentoom-core::admin.human_intervention.handoff_started'));

                return;
            }

            $driver->sendMessage($externalChatId, __('agentoom-core::admin.human_intervention.offline_after_request'));
        }

        $driver->sendMessage($externalChatId, $response->text);
    }
}

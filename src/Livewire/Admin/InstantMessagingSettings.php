<?php

namespace Agentoom\InstantMessaging\Livewire\Admin;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Configuration;
use Agentoom\InstantMessaging\Models\TelegramBot;
use Flux;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Channel configuration for Telegram bots and the WhatsApp Cloud API.
 *
 * Whether a conversation can be handed to a human is a live-support concern
 * and lives on its own settings page; this page only decides how conversations
 * arrive and how replies leave.
 */
class InstantMessagingSettings extends Component
{
    public array $whatsapp = [];

    public array $newBot = [
        'name' => '',
        'token' => '',
        'agent_id' => '',
        'user_id' => '',
    ];

    public ?int $editingBotId = null;

    public array $editingBot = [
        'name' => '',
        'token' => '',
        'agent_id' => '',
        'user_id' => '',
    ];

    public function mount(): void
    {
        $this->whatsapp = array_merge([
            'api_key' => '',
            'phone_number_id' => '',
            'verify_token' => '',
            'app_secret' => '',
            'enabled' => false,
            'agent_id' => '',
        ], Configuration::getValue('instant_messaging_whatsapp', []) ?? []);
    }

    protected function botRules(string $prefix): array
    {
        return [
            "{$prefix}.name" => 'required|string|max:255',
            "{$prefix}.token" => 'required|string|max:255',
            "{$prefix}.agent_id" => ['required', Rule::exists('agents', 'id')->where('system_agent', 0)],
            "{$prefix}.user_id" => 'required|exists:users,id',
        ];
    }

    public function addBot(): void
    {
        $this->validate($this->botRules('newBot'));

        TelegramBot::create($this->newBot);

        $this->newBot = ['name' => '', 'token' => '', 'agent_id' => '', 'user_id' => ''];

        Flux::toast(__('agentoom-instant-messaging::admin.settings.bot_added'));
    }

    public function deleteBot(int $id): void
    {
        TelegramBot::findOrFail($id)->delete();
        Flux::toast(__('agentoom-instant-messaging::admin.settings.bot_deleted'));
    }

    public function toggleBotStatus(int $id): void
    {
        $bot = TelegramBot::findOrFail($id);
        $bot->update(['is_active' => ! $bot->is_active]);
        Flux::toast(__('agentoom-instant-messaging::admin.settings.bot_status_updated'));
    }

    public function editBot(int $id): void
    {
        $bot = TelegramBot::findOrFail($id);
        $this->editingBotId = $id;
        $this->editingBot = [
            'name' => $bot->name,
            'token' => $bot->token,
            'agent_id' => $bot->agent_id,
            'user_id' => $bot->user_id,
        ];

        Flux::modal('edit-bot-modal-'.$this->getId())->show();
    }

    public function updateBot(): void
    {
        $this->validate($this->botRules('editingBot'));

        TelegramBot::findOrFail($this->editingBotId)->update($this->editingBot);

        $this->editingBotId = null;
        Flux::modal('edit-bot-modal-'.$this->getId())->hide();
        Flux::toast(__('agentoom-instant-messaging::admin.settings.bot_updated'));
    }

    /**
     * Register the webhook with Telegram, generating the secret this app will
     * require on every inbound delivery. A bot that never went through this
     * cannot be verified and its webhook is refused.
     */
    public function setBotWebhook(int $id): void
    {
        $bot = TelegramBot::findOrFail($id);

        if (empty($bot->token)) {
            Flux::toast(__('agentoom-instant-messaging::admin.settings.bot_token_required'), variant: 'danger');

            return;
        }

        $secret = $bot->webhook_secret ?: bin2hex(random_bytes(32));
        $url = url("/api/instant-messaging/webhook/telegram-bot/{$bot->id}");

        try {
            $response = Http::post("https://api.telegram.org/bot{$bot->token}/setWebhook", [
                'url' => $url,
                'secret_token' => $secret,
            ]);

            if ($response->successful()) {
                $bot->update(['webhook_secret' => $secret]);
                Flux::toast(__('agentoom-instant-messaging::admin.settings.webhook_set', ['name' => $bot->name]));
            } else {
                Flux::toast(
                    __('agentoom-instant-messaging::admin.settings.webhook_failed', [
                        'name' => $bot->name,
                        'error' => $response->json('description', __('agentoom-instant-messaging::admin.settings.unknown_error')),
                    ]),
                    variant: 'danger'
                );
            }
        } catch (\Exception $e) {
            Flux::toast(__('agentoom-instant-messaging::admin.settings.webhook_error', ['error' => $e->getMessage()]), variant: 'danger');
        }
    }

    public function updatedWhatsapp(): void
    {
        $this->validate([
            'whatsapp.api_key' => 'nullable|string',
            'whatsapp.phone_number_id' => 'nullable|string',
            'whatsapp.verify_token' => 'nullable|string',
            'whatsapp.app_secret' => 'nullable|string',
            'whatsapp.agent_id' => ['nullable', Rule::exists('agents', 'id')->where('system_agent', 0)],
            'whatsapp.enabled' => 'boolean',
        ]);

        Configuration::setValue('instant_messaging_whatsapp', $this->whatsapp);
    }

    public function save(): void
    {
        $this->updatedWhatsapp();

        Flux::toast(__('agentoom-instant-messaging::admin.settings.saved'));
    }

    public function render()
    {
        return view('agentoom-instant-messaging::livewire.admin.settings', [
            'agents' => Agent::where('system_agent', false)->orderBy('name')->get(),
        ])
            ->layout('agentoom-core::layouts.admin');
    }
}

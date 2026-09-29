<div class="space-y-6">
    <div>
        <flux:heading size="xl">{{ __('agentoom-instant-messaging::admin.settings.title') }}</flux:heading>
        <flux:text>{{ __('agentoom-instant-messaging::admin.settings.description') }}</flux:text>
    </div>

    @if(\Illuminate\Support\Facades\Route::has('admin.live-support.settings'))
        <flux:callout variant="secondary">
            <flux:text size="sm">{!! __('agentoom-instant-messaging::admin.settings.human_handoff_pointer', [
                'link' => '<a class="underline" href="'.route('admin.live-support.settings').'">'.__('agentoom-instant-messaging::admin.settings.human_handoff_link').'</a>',
            ]) !!}</flux:text>
        </flux:callout>
    @endif

    <!-- Telegram Integration -->
    <flux:card>
        <div class="space-y-4">
            <flux:heading level="2" size="lg">{{ __('agentoom-instant-messaging::admin.settings.telegram_title') }}</flux:heading>
            <flux:text>{{ __('agentoom-instant-messaging::admin.settings.telegram_help') }}</flux:text>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('agentoom-instant-messaging::admin.settings.name') }}</flux:table.column>
                    <flux:table.column>{{ __('agentoom-instant-messaging::admin.settings.owner') }}</flux:table.column>
                    <flux:table.column>{{ __('agentoom-instant-messaging::admin.settings.agent') }}</flux:table.column>
                    <flux:table.column>{{ __('agentoom-instant-messaging::admin.settings.status') }}</flux:table.column>
                    <flux:table.column>{{ __('agentoom-instant-messaging::admin.settings.webhook_url') }}</flux:table.column>
                    <flux:table.column>{{ __('agentoom-instant-messaging::admin.settings.actions') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach(\Agentoom\InstantMessaging\Models\TelegramBot::with(['user', 'agent'])->get() as $bot)
                        <flux:table.row :key="$bot->id">
                            <flux:table.cell>{{ $bot->name }}</flux:table.cell>
                            <flux:table.cell>{{ $bot->user->name }}</flux:table.cell>
                            <flux:table.cell>{{ $bot->agent->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$bot->is_active ? 'green' : 'red'" wire:click="toggleBotStatus({{ $bot->id }})" class="cursor-pointer">
                                    {{ $bot->is_active ? __('agentoom-instant-messaging::admin.settings.active') : __('agentoom-instant-messaging::admin.settings.inactive') }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <code class="text-xs text-zinc-500">{{ url("/api/instant-messaging/webhook/telegram-bot/{$bot->id}") }}</code>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex space-x-2">
                                    <flux:button wire:click="editBot({{ $bot->id }})" variant="subtle" size="xs">{{ __('agentoom-instant-messaging::admin.settings.edit') }}</flux:button>
                                    <flux:button wire:click="setBotWebhook({{ $bot->id }})" variant="subtle" size="xs">{{ __('agentoom-instant-messaging::admin.settings.set_webhook') }}</flux:button>
                                    <flux:button wire:click="deleteBot({{ $bot->id }})" variant="danger" size="xs" wire:confirm="{{ __('agentoom-instant-messaging::admin.settings.delete_bot_confirm') }}">{{ __('agentoom-instant-messaging::admin.settings.delete') }}</flux:button>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <flux:separator variant="subtle" />

            <flux:heading level="3" size="md">{{ __('agentoom-instant-messaging::admin.settings.add_bot') }}</flux:heading>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <!-- Dummy fields to trick browser autofill -->
                <div class="hidden" aria-hidden="true">
                    <input type="text" name="fake_username" tabindex="-1" autocomplete="username">
                    <input type="password" name="fake_password" tabindex="-1" autocomplete="current-password">
                </div>

                <flux:input wire:model="newBot.name" label="{{ __('agentoom-instant-messaging::admin.settings.bot_name') }}" placeholder="{{ __('agentoom-instant-messaging::admin.settings.bot_name_placeholder') }}" autocomplete="one-time-code" />
                <flux:input wire:model="newBot.token" label="{{ __('agentoom-instant-messaging::admin.settings.bot_token') }}" placeholder="123456:ABC..." type="password" autocomplete="new-password" />

                <flux:select wire:model="newBot.user_id" label="{{ __('agentoom-instant-messaging::admin.settings.owner') }}" placeholder="{{ __('agentoom-instant-messaging::admin.settings.select_user') }}">
                    @foreach(\Agentoom\Core\Models\User::all() as $user)
                        <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="newBot.agent_id" label="{{ __('agentoom-instant-messaging::admin.settings.linked_agent') }}" placeholder="{{ __('agentoom-instant-messaging::admin.settings.select_agent') }}">
                    @foreach($agents as $agent)
                        <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div class="flex justify-end">
                <flux:button wire:click="addBot" variant="primary" size="sm">{{ __('agentoom-instant-messaging::admin.settings.add_bot_button') }}</flux:button>
            </div>
        </div>
    </flux:card>

    <!-- WhatsApp Integration -->
    <flux:card>
        <div class="space-y-4">
            <flux:heading level="2" size="lg">{{ __('agentoom-instant-messaging::admin.settings.whatsapp_title') }}</flux:heading>
            <flux:text>{{ __('agentoom-instant-messaging::admin.settings.whatsapp_help') }}</flux:text>

            <flux:switch wire:model.live="whatsapp.enabled" label="{{ __('agentoom-instant-messaging::admin.settings.whatsapp_enabled') }}" />

            <div class="hidden" aria-hidden="true">
                <input type="text" name="fake_whatsapp_username" tabindex="-1" autocomplete="username">
                <input type="password" name="fake_whatsapp_password" tabindex="-1" autocomplete="current-password">
            </div>

            <flux:input wire:model.blur="whatsapp.phone_number_id" label="{{ __('agentoom-instant-messaging::admin.settings.phone_number_id') }}" placeholder="e.g., 105930..." />
            <flux:input wire:model.blur="whatsapp.api_key" label="{{ __('agentoom-instant-messaging::admin.settings.access_token') }}" placeholder="e.g., EAAG..." type="password" autocomplete="new-password" />
            <flux:input wire:model.blur="whatsapp.verify_token" label="{{ __('agentoom-instant-messaging::admin.settings.verify_token') }}" description="{{ __('agentoom-instant-messaging::admin.settings.verify_token_help') }}" autocomplete="new-password" />
            <flux:input wire:model.blur="whatsapp.app_secret" label="{{ __('agentoom-instant-messaging::admin.settings.app_secret') }}" description="{{ __('agentoom-instant-messaging::admin.settings.app_secret_help') }}" type="password" autocomplete="new-password" />

            <flux:select wire:model.live="whatsapp.agent_id" label="{{ __('agentoom-instant-messaging::admin.settings.linked_agent') }}" placeholder="{{ __('agentoom-instant-messaging::admin.settings.select_agent') }}">
                @foreach($agents as $agent)
                    <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="mt-4 p-3 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg space-y-2">
                <flux:text size="sm">
                    {{ __('agentoom-instant-messaging::admin.settings.webhook_url') }}: <code class="text-blue-600 dark:text-blue-400">{{ ($whatsapp['agent_id'] ?? null) ? url("/api/instant-messaging/webhook/whatsapp/{$whatsapp['agent_id']}") : __('agentoom-instant-messaging::admin.settings.select_agent_for_url') }}</code>
                </flux:text>
                @if(empty($whatsapp['verify_token']) || empty($whatsapp['app_secret']))
                    <flux:text size="sm" class="text-amber-600 dark:text-amber-400">{{ __('agentoom-instant-messaging::admin.settings.whatsapp_verification_missing') }}</flux:text>
                @endif
            </div>
        </div>
    </flux:card>

    <div class="flex justify-end mt-8">
        <flux:button wire:click="save" variant="primary">{{ __('agentoom-instant-messaging::admin.settings.save') }}</flux:button>
    </div>

    <flux:modal name="edit-bot-modal-{{ $this->getId() }}" class="md:w-[600px] space-y-6">
        <div>
            <flux:heading size="lg">{{ __('agentoom-instant-messaging::admin.settings.edit_bot') }}</flux:heading>
            <flux:text>{{ __('agentoom-instant-messaging::admin.settings.edit_bot_help') }}</flux:text>
        </div>

        <div class="space-y-4">
            <div class="hidden" aria-hidden="true">
                <input type="text" name="fake_edit_username" tabindex="-1" autocomplete="username">
                <input type="password" name="fake_edit_password" tabindex="-1" autocomplete="current-password">
            </div>

            <flux:input wire:model="editingBot.name" label="{{ __('agentoom-instant-messaging::admin.settings.bot_name') }}" placeholder="{{ __('agentoom-instant-messaging::admin.settings.bot_name_placeholder') }}" autocomplete="one-time-code" />
            <flux:input wire:model="editingBot.token" label="{{ __('agentoom-instant-messaging::admin.settings.bot_token') }}" placeholder="123456:ABC..." type="password" autocomplete="new-password" />

            <flux:select wire:model="editingBot.user_id" label="{{ __('agentoom-instant-messaging::admin.settings.owner') }}" placeholder="{{ __('agentoom-instant-messaging::admin.settings.select_user') }}">
                @foreach(\Agentoom\Core\Models\User::all() as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="editingBot.agent_id" label="{{ __('agentoom-instant-messaging::admin.settings.linked_agent') }}" placeholder="{{ __('agentoom-instant-messaging::admin.settings.select_agent') }}">
                @foreach($agents as $agent)
                    <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex">
            <flux:spacer />
            <flux:button x-on:click="$flux.modal('edit-bot-modal-{{ $this->getId() }}').hide()" variant="ghost">{{ __('agentoom-instant-messaging::admin.settings.cancel') }}</flux:button>
            <flux:button wire:click="updateBot" variant="primary">{{ __('agentoom-instant-messaging::admin.settings.update_bot') }}</flux:button>
        </div>
    </flux:modal>
</div>

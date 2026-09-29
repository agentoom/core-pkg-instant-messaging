<?php

namespace Agentoom\InstantMessaging\DataTransfer\Sections;

use Agentoom\Core\DataTransfer\AbstractImportExportSection;
use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\InstantMessaging\Models\TelegramBot;
use Illuminate\Support\Collection;
use RuntimeException;

class TelegramBotsSection extends AbstractImportExportSection
{
    public function key(): string
    {
        return 'telegram_bots';
    }

    public function group(): string
    {
        return ImportExportRegistry::GROUP_COMMUNICATION;
    }

    public function label(): string
    {
        return __('agentoom-core::admin.permissions.groups.instant_messaging');
    }

    public function permission(): ?string
    {
        return 'instant-messaging.view';
    }

    /**
     * Bots import after agents because they reference one.
     */
    public function importLevel(): int
    {
        return 3;
    }

    public function options(): Collection
    {
        return TelegramBot::all();
    }

    public function agentIds(array $ids): ?array
    {
        $ids = $this->selectedIds($ids) ?? TelegramBot::pluck('id')->toArray();

        return TelegramBot::whereIn('id', $ids)->pluck('agent_id')->filter()->unique()->values()->toArray();
    }

    public function export(array $ids): array
    {
        $query = TelegramBot::query();
        if (($ids = $this->selectedIds($ids)) !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(function ($bot) {
            return [
                'name' => $bot->name,
                'token' => $bot->token,
                'is_active' => $bot->is_active,
                'agent_slug' => $bot->agent?->slug,
                'agent_name' => $bot->agent?->name,
            ];
        })->toArray();
    }

    public function import(array $payload, array $options = []): void
    {
        foreach ($payload as $botData) {
            $agent = Agent::where('slug', $botData['agent_slug'] ?? '')
                ->orWhere('name', $botData['agent_name'] ?? '')
                ->first();

            if ($agent === null) {
                throw new RuntimeException("Telegram bot [{$botData['name']}] cannot be imported: its agent is missing.");
            }

            // Repair: the import previously omitted user_id and agent_id, so
            // restoring a bot failed on these NOT NULL columns.
            TelegramBot::updateOrCreate(
                ['name' => $botData['name']],
                [
                    'token' => $botData['token'],
                    'is_active' => $botData['is_active'] ?? true,
                    'user_id' => $this->ownerId(),
                    'agent_id' => $agent->id,
                ]
            );
        }
    }
}

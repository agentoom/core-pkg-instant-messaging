<?php

namespace Agentoom\InstantMessaging\Tests\Feature;

use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Models\Agent;
use Agentoom\Core\Services\ImportService;
use Agentoom\Core\Tests\TestCase;
use Agentoom\InstantMessaging\DataTransfer\Sections\TelegramBotsSection;
use Agentoom\InstantMessaging\Models\TelegramBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

class ImportExportSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_package_registers_its_import_export_section(): void
    {
        $registry = $this->app->make(ImportExportRegistry::class);

        $this->assertTrue($registry->has('telegram_bots'));

        $section = $registry->get('telegram_bots');

        $this->assertInstanceOf(TelegramBotsSection::class, $section);
        $this->assertSame(ImportExportRegistry::GROUP_COMMUNICATION, $section->group());
        $this->assertSame('instant-messaging.view', $section->permission());
    }

    public function test_a_bot_can_be_exported_deleted_and_imported_again(): void
    {
        $user = User::factory()->create();
        $agent = Agent::factory()->create();

        $this->actingAs($user);

        $bot = TelegramBot::create([
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'name' => 'Round Trip Bot',
            'token' => '123456:round-trip-token',
            'is_active' => true,
        ]);

        $section = $this->app->make(ImportExportRegistry::class)->get('telegram_bots');

        $payload = $section->export([$bot->id]);

        $this->assertSame('Round Trip Bot', $payload[0]['name']);
        $this->assertSame($agent->slug, $payload[0]['agent_slug']);

        $bot->delete();
        $this->assertDatabaseMissing('telegram_bots', ['name' => 'Round Trip Bot']);

        $section->import($payload);

        $this->assertDatabaseHas('telegram_bots', [
            'name' => 'Round Trip Bot',
            'token' => '123456:round-trip-token',
            'user_id' => $user->id,
            'agent_id' => $agent->id,
        ]);

        $section->import($payload);

        $this->assertSame(1, TelegramBot::where('name', 'Round Trip Bot')->count());
    }

    public function test_importing_a_bot_whose_agent_is_missing_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Telegram bot [Ghost Bot] cannot be imported: its agent is missing.');

        $this->app->make(ImportExportRegistry::class)->get('telegram_bots')->import([
            ['name' => 'Ghost Bot', 'token' => 'ghost-token', 'agent_name' => 'No Such Agent'],
        ]);
    }

    public function test_bots_are_imported_after_the_agents_they_reference(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $agent = Agent::factory()->create();
        $bot = TelegramBot::create([
            'user_id' => $user->id,
            'agent_id' => $agent->id,
            'name' => 'Ordered Bot',
            'token' => 'ordered-token',
            'is_active' => true,
        ]);

        $registry = $this->app->make(ImportExportRegistry::class);

        $data = [
            'agents' => $registry->get('agents')->export([$agent->id]),
            'telegram_bots' => $registry->get('telegram_bots')->export([$bot->id]),
        ];

        $bot->delete();
        $agent->delete();
        $this->assertDatabaseMissing('agents', ['id' => $agent->id]);

        $this->app->make(ImportService::class)->import($data);

        $restoredAgent = Agent::where('slug', $agent->slug)->firstOrFail();

        $this->assertDatabaseHas('telegram_bots', [
            'name' => 'Ordered Bot',
            'token' => 'ordered-token',
            'agent_id' => $restoredAgent->id,
        ]);
    }

    public function test_selecting_a_bot_also_selects_its_agent(): void
    {
        $agent = Agent::factory()->create();
        TelegramBot::create([
            'user_id' => User::factory()->create()->id,
            'agent_id' => $agent->id,
            'name' => 'Linked Bot',
            'token' => 'linked-token',
            'is_active' => true,
        ]);

        $section = $this->app->make(ImportExportRegistry::class)->get('telegram_bots');

        $this->assertSame([$agent->id], $section->agentIds(['all']));
    }
}

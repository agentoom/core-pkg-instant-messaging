<?php

namespace Agentoom\InstantMessaging\Tests\Feature;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\Language;
use Agentoom\Core\Tests\TestCase;
use Agentoom\InstantMessaging\Models\TelegramBot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'superadmin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(Permission::firstOrCreate(['name' => 'instant-messaging.view']));
    }

    public function test_the_page_renders_the_channel_configuration(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.instant-messaging.settings'));

        $response->assertOk();

        $response->assertSee('Telegram');
        $response->assertSee('WhatsApp');
    }

    /**
     * A raw "package::group.key" in the output means the package never
     * registered its translation namespace, which leaves every label on the
     * page untranslated. (Livewire's own component id also contains "::", so
     * match the language path specifically.)
     */
    public function test_the_page_renders_translated_copy(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.instant-messaging.settings'));

        $response->assertOk();
        $response->assertDontSee('agentoom-instant-messaging::admin.settings.', false);

        $response->assertSee('Instant Messaging Settings');
        $response->assertSee('Telegram Bots');
        $response->assertSee('WhatsApp Integration (Cloud API)');
        $response->assertSee('Save Settings');
    }

    public function test_labels_follow_the_active_locale(): void
    {
        Language::create(['name' => 'German', 'code' => 'de', 'is_active' => true]);
        session(['admin_locale' => 'de']);

        $response = $this->actingAs($this->admin())->get(route('admin.instant-messaging.settings'));

        $response->assertOk();
        $response->assertDontSee('agentoom-instant-messaging::admin.settings.', false);

        $expected = require base_path('packages/agentoom/core-instant-messaging/resources/lang/de/admin.php');

        $response->assertSee($expected['settings']['title']);
        $response->assertSee($expected['settings']['whatsapp_title']);
        $response->assertSee($expected['settings']['save']);
    }

    public function test_the_page_does_not_configure_human_handoff(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.instant-messaging.settings'));

        // Operational hours and support roles are a live-support concern; they
        // must not be configured from the channel page.
        $response->assertDontSee('Operational Hours');
        $response->assertDontSee('Enable Operational Hours');
    }

    public function test_the_page_only_points_at_live_support_settings_when_they_exist(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.instant-messaging.settings'));

        $response->assertOk();

        if (\Illuminate\Support\Facades\Route::has('admin.live-support.settings')) {
            $response->assertSee(route('admin.live-support.settings'), false);
        } else {
            // Never link an admin at a page this instance does not serve.
            $response->assertDontSee('live-support/settings', false);
        }
    }

    public function test_bots_are_listed_with_their_webhook_url(): void
    {
        $bot = TelegramBot::create([
            'user_id' => User::factory()->create()->id,
            'agent_id' => Agent::factory()->create()->id,
            'name' => 'Listed Bot',
            'token' => '123:abc',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.instant-messaging.settings'));

        $response->assertOk()
            ->assertSee('Listed Bot')
            ->assertSee("/api/instant-messaging/webhook/telegram-bot/{$bot->id}", false);
    }

    public function test_the_page_requires_the_channel_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->assignRole(Role::firstOrCreate(['name' => 'viewer']));

        $this->actingAs($outsider)
            ->get(route('admin.instant-messaging.settings'))
            ->assertForbidden();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }
}

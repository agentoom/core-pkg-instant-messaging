<?php

namespace Agentoom\InstantMessaging;

use Agentoom\Core\DataTransfer\ImportExportRegistry;
use Agentoom\Core\Services\PermissionRegistry;
use Agentoom\InstantMessaging\DataTransfer\Sections\TelegramBotsSection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class InstantMessagingServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/instant-messaging.php', 'agentoom-instant-messaging');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'agentoom-instant-messaging');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'agentoom-instant-messaging');

        $this->registerRoutes();

        Livewire::addNamespace('agentoom-instant-messaging', null, 'Agentoom\\InstantMessaging\\Livewire');

        $this->app->make(PermissionRegistry::class)->register('instant_messaging', ['instant-messaging.view', 'instant-messaging.edit']);

        $this->app->make(ImportExportRegistry::class)->register(new TelegramBotsSection);
    }

    protected function registerRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');

        Route::middleware('api')
            ->prefix('api/instant-messaging')
            ->group(__DIR__.'/../routes/api.php');
    }
}

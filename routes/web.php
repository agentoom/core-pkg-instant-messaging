<?php

use Agentoom\InstantMessaging\Livewire\Admin\InstantMessagingSettings;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('instant-messaging/settings', InstantMessagingSettings::class)
        ->middleware('permission:instant-messaging.view')
        ->name('instant-messaging.settings');
});

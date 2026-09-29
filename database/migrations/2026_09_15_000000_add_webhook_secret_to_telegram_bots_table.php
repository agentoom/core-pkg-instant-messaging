<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Telegram echoes the registered secret back in the
     * X-Telegram-Bot-Api-Secret-Token header, which is what lets the webhook
     * tell a real delivery from anyone who guessed a bot id.
     */
    public function up(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->string('webhook_secret')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_bots', function (Blueprint $table) {
            $table->dropColumn('webhook_secret');
        });
    }
};

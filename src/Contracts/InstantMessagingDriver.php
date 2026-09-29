<?php

namespace Agentoom\InstantMessaging\Contracts;

use Agentoom\Core\Models\User;
use Illuminate\Http\Request;

interface InstantMessagingDriver
{
    /**
     * Handle the incoming webhook request.
     */
    public function handleWebhook(Request $request): mixed;

    /**
     * Send a message to the user.
     */
    public function sendMessage(string $externalChatId, string $message, array $attachments = []): bool;

    /**
     * Get the service name (e.g., 'telegram', 'whatsapp').
     */
    public function getServiceName(): string;
}

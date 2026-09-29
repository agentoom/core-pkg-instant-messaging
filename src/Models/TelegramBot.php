<?php

namespace Agentoom\InstantMessaging\Models;

use Agentoom\Core\Models\Agent;
use Agentoom\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramBot extends Model
{
    protected $fillable = [
        'user_id',
        'agent_id',
        'name',
        'token',
        'webhook_secret',
        'is_active',
    ];

    /**
     * Credentials must never reach a serialised payload.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}

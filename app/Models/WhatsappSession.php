<?php

namespace App\Models;

use App\Enums\SessionState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappSession extends Model
{
    protected $fillable = ['phone', 'patient_id', 'state', 'context', 'last_interaction_at'];

    protected function casts(): array
    {
        return ['state' => SessionState::class, 'context' => 'array', 'last_interaction_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function isExpired(int $minutes): bool
    {
        return $this->last_interaction_at->diffInMinutes(now()) >= $minutes;
    }
}

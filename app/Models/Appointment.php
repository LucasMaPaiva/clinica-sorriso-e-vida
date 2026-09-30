<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    use HasFactory;

    protected $attributes = ['status' => 'scheduled', 'source' => 'admin'];

    protected $fillable = ['patient_id', 'dentist_id', 'procedure_id', 'starts_at', 'ends_at', 'status', 'source', 'notes', 'cancellation_reason', 'confirmed_at', 'reminder_24h_sent_at', 'reminder_2h_sent_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'status' => AppointmentStatus::class,
            'confirmed_at' => 'datetime', 'reminder_24h_sent_at' => 'datetime', 'reminder_2h_sent_at' => 'datetime'];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }
}

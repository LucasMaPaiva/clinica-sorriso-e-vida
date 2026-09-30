<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    protected $attributes = ['slot_interval_minutes' => 30, 'active' => true];

    protected $fillable = ['dentist_id', 'weekday', 'start_time', 'end_time', 'slot_interval_minutes', 'active'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'slot_interval_minutes' => 'integer', 'active' => 'boolean'];
    }

    public function dentist(): BelongsTo
    {
        return $this->belongsTo(Dentist::class);
    }
}

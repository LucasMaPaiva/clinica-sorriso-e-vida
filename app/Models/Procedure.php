<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Procedure extends Model
{
    use HasFactory;

    protected $attributes = ['duration_minutes' => 30, 'active' => true];

    protected $fillable = ['name', 'description', 'duration_minutes', 'price', 'active'];

    protected function casts(): array
    {
        return ['duration_minutes' => 'integer', 'price' => 'decimal:2', 'active' => 'boolean'];
    }

    public function dentists(): BelongsToMany
    {
        return $this->belongsToMany(Dentist::class)->withTimestamps();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

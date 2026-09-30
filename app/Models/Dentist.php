<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dentist extends Model
{
    use HasFactory;

    protected $attributes = ['active' => true];

    protected $fillable = ['name', 'cro', 'specialty', 'phone', 'email', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function procedures(): BelongsToMany
    {
        return $this->belongsToMany(Procedure::class)->withTimestamps();
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $attributes = ['active' => true];

    protected $fillable = ['name', 'phone', 'email', 'birth_date', 'notes', 'active'];

    protected function casts(): array
    {
        return ['birth_date' => 'date', 'active' => 'boolean'];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

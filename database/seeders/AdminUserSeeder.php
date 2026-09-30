<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('ADMIN_SEED_EMAIL', 'admin@sorrisoevida.com.br')],
            [
                'name' => 'Admin Clínica Sorriso e Vida',
                'password' => env('ADMIN_SEED_PASSWORD', 'password'),
            ],
        );
    }
}

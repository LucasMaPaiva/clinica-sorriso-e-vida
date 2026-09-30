<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the appointments page for an authenticated admin', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/appointments')
        ->assertOk();
});

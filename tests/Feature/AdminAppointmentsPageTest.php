<?php

use App\Enums\SessionState;
use App\Models\Patient;
use App\Models\User;
use App\Models\WhatsappSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the appointments page for an authenticated admin', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->get('/admin/appointments')
        ->assertOk();
});

it('renders reception handoffs in the whatsapp service page', function () {
    $admin = User::factory()->create();
    $patient = Patient::query()->create([
        'name' => 'Maria da Silva',
        'phone' => '5595984000005',
    ]);
    WhatsappSession::query()->create([
        'phone' => $patient->phone,
        'patient_id' => $patient->id,
        'state' => SessionState::HumanHandoff,
        'context' => [
            'handoff_reason' => 'manual',
            'last_patient_message' => 'Preciso falar com a recepção',
        ],
        'last_interaction_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get('/admin/whatsapp-sessions')
        ->assertOk()
        ->assertSee('Maria da Silva')
        ->assertSee('Aguardando recepção')
        ->assertSee('Preciso falar com a recepção');
});

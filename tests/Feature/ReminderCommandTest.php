<?php

use App\Enums\AppointmentStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Procedure;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

it('queues reminder once', function () {
    CarbonImmutable::setTestNow('2026-09-29 08:00');
    Queue::fake();
    $p = Patient::create(['name' => 'Maria', 'phone' => '5595984000000']);
    $d = Dentist::create(['name' => 'Dra. Ana', 'cro' => 'CRO-RR 1234']);
    $pr = Procedure::create(['name' => 'Limpeza']);
    $a = Appointment::create(['patient_id' => $p->id, 'dentist_id' => $d->id, 'procedure_id' => $pr->id, 'starts_at' => now()->addHours(23), 'ends_at' => now()->addHours(24), 'status' => AppointmentStatus::Scheduled]);
    $this->artisan('appointments:send-reminders')->assertSuccessful();
    $this->artisan('appointments:send-reminders')->assertSuccessful();
    Queue::assertPushed(SendWhatsAppMessage::class, 1);
    expect($a->fresh()->reminder_24h_sent_at)->not->toBeNull();
    CarbonImmutable::setTestNow();
});

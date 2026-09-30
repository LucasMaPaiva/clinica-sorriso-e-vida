<?php

use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ScheduleBlock;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config()->set('app.timezone', 'America/Boa_Vista');
    config()->set('clinic.booking.minimum_notice_hours', 2);
    config()->set('clinic.booking.max_days_ahead', 90);
    CarbonImmutable::setTestNow('2026-09-29 08:00:00');
    $this->patient = Patient::create(['name' => 'Maria Silva', 'phone' => '5595984000000']);
    $this->dentist = Dentist::create(['name' => 'Dra. Ana', 'cro' => 'CRO-RR 1234']);
    $this->procedure = Procedure::create(['name' => 'Limpeza', 'duration_minutes' => 60]);
    $this->dentist->procedures()->attach($this->procedure);
    Availability::create(['dentist_id' => $this->dentist->id, 'weekday' => 3, 'start_time' => '08:00', 'end_time' => '12:00', 'slot_interval_minutes' => 30]);
});
afterEach(fn () => CarbonImmutable::setTestNow());
it('creates an appointment and removes conflicting times', function () {
    $s = app(AppointmentService::class);
    $a = $s->create($this->patient, $this->dentist, $this->procedure, '2026-09-30 09:00');
    expect($a)->toBeInstanceOf(Appointment::class)->and($a->ends_at->format('H:i'))->toBe('10:00');
    $times = $s->availableSlots($this->dentist, $this->procedure, '2026-09-30')->map->format('H:i');
    expect($times)->not->toContain('08:30', '09:00', '09:30')->and($times)->toContain('10:00');
});
it('rejects overlapping appointments', function () {
    $s = app(AppointmentService::class);
    $s->create($this->patient, $this->dentist, $this->procedure, '2026-09-30 09:00');
    $s->create(Patient::create(['name' => 'João', 'phone' => '5595984111111']), $this->dentist, $this->procedure, '2026-09-30 09:30');
})->throws(DomainException::class, 'Esse horário não está mais disponível');
it('removes blocked periods', function () {
    ScheduleBlock::create(['dentist_id' => $this->dentist->id, 'starts_at' => '2026-09-30 10:00', 'ends_at' => '2026-09-30 11:00']);
    $times = app(AppointmentService::class)->availableSlots($this->dentist, $this->procedure, '2026-09-30')->map->format('H:i');
    expect($times)->not->toContain('09:30', '10:00', '10:30');
});

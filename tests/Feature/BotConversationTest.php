<?php

use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Dentist;
use App\Models\Procedure;
use App\Services\Bot\BotService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config()->set('app.timezone', 'America/Boa_Vista');
    config()->set('clinic.booking.minimum_notice_hours', 2);
    CarbonImmutable::setTestNow('2026-09-29 08:00:00');
    $this->dentist = Dentist::create(['name' => 'Dra. Ana', 'cro' => 'CRO-RR 1234']);
    $this->procedure = Procedure::create(['name' => 'Avaliação', 'duration_minutes' => 30]);
    $this->dentist->procedures()->attach($this->procedure);
    Availability::create(['dentist_id' => $this->dentist->id, 'weekday' => 3, 'start_time' => '08:00', 'end_time' => '12:00', 'slot_interval_minutes' => 30]);
});
afterEach(fn () => CarbonImmutable::setTestNow());
it('books through whatsapp', function () {
    $b = app(BotService::class);
    $p = '5595984000000';
    expect($b->handleIncomingMessage($p, 'oi', false)[0])->toContain('Agendar consulta');
    expect($b->handleIncomingMessage($p, '1', false)[0])->toContain('nome completo');
    $b->handleIncomingMessage($p, 'Maria da Silva', false);
    $b->handleIncomingMessage($p, '1', false);
    $b->handleIncomingMessage($p, '1', false);
    expect($b->handleIncomingMessage($p, '30/09/2026', false)[0])->toContain('08:00');
    $b->handleIncomingMessage($p, '1', false);
    expect($b->handleIncomingMessage($p, 'sim', false)[0])->toContain('agendada com sucesso');
    expect(Appointment::count())->toBe(1)->and(Appointment::first()->source)->toBe('whatsapp');
});

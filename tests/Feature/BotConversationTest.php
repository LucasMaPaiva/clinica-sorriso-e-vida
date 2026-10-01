<?php

use App\Enums\SessionState;
use App\Filament\Resources\WhatsappSessions\WhatsappSessionResource;
use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Dentist;
use App\Models\Procedure;
use App\Models\WhatsappSession;
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
    expect($b->handleIncomingMessage($p, 'sim', false)[0])
        ->toContain('agendada com sucesso')
        ->toContain('Posso ajudar com mais alguma coisa?');
    expect(Appointment::count())->toBe(1)
        ->and(Appointment::first()->source)->toBe('whatsapp')
        ->and(WhatsappSession::query()->where('phone', $p)->value('state'))->toBe(SessionState::AwaitingAnythingElse);
});

it('understands a natural scheduling conversation', function () {
    $bot = app(BotService::class);
    $phone = '5595984000001';

    $start = $bot->handleIncomingMessage($phone, 'quero agendar uma consulta', false);
    expect($start)->toHaveCount(2)
        ->and($start[0])->toContain('Que bom ter você')
        ->and($start[1])->toContain('nome completo');

    $name = $bot->handleIncomingMessage($phone, 'Maria da Silva', false);
    expect($name[0])->toContain('Prazer, Maria Da Silva')
        ->and($name[1])->toContain('Qual atendimento');

    expect($bot->handleIncomingMessage($phone, 'quero uma avaliação', false)[0])->toContain('qual profissional');
    expect($bot->handleIncomingMessage($phone, 'prefiro a Dra. Ana', false)[0])->toContain('Qual data');
    expect($bot->handleIncomingMessage($phone, 'amanhã', false)[0])->toContain('30/09/2026');
    expect($bot->handleIncomingMessage($phone, '8h', false)[0])->toContain('Horário: *08:00*');
    expect($bot->handleIncomingMessage($phone, 'pode confirmar', false)[0])
        ->toContain('agendada com sucesso')
        ->toContain('encerrar atendimento');

    expect(Appointment::query()->where('source', 'whatsapp')->count())->toBe(1);

    $finished = $bot->handleIncomingMessage($phone, 'não', false);

    expect($finished[0])->toContain('Atendimento encerrado')
        ->and(WhatsappSession::query()->where('phone', $phone)->value('state'))->toBe(SessionState::Idle);
});

it('hands the conversation to reception and keeps the bot silent', function () {
    $bot = app(BotService::class);
    $phone = '5595984000002';

    $bot->handleIncomingMessage($phone, 'oi', false);
    $handoff = $bot->handleIncomingMessage($phone, 'quero falar com um atendente', false);
    $silent = $bot->handleIncomingMessage($phone, 'preciso tirar uma dúvida', false);
    $session = WhatsappSession::query()->where('phone', $phone)->firstOrFail();

    expect($handoff[0])->toContain('Encaminhei sua conversa para a recepção')
        ->and($silent)->toBe([])
        ->and($session->state)->toBe(SessionState::HumanHandoff)
        ->and($session->context['last_patient_message'])->toBe('preciso tirar uma dúvida')
        ->and(WhatsappSessionResource::getNavigationBadge())->toBe('1');

    $resumed = $bot->handleIncomingMessage($phone, 'voltar ao bot', false);

    expect($resumed[0])->toContain('atendimento automático voltou')
        ->and($session->fresh()->state)->toBe(SessionState::AwaitingAction);
});

it('routes possible dental urgency to reception without diagnosing', function () {
    $phone = '5595984000003';

    $messages = app(BotService::class)->handleIncomingMessage($phone, 'estou com dor muito forte e o rosto inchado', false);
    $session = WhatsappSession::query()->where('phone', $phone)->firstOrFail();

    expect($messages[0])
        ->toContain('não consigo avaliar sintomas ou fazer diagnóstico')
        ->toContain('serviço presencial de urgência')
        ->and($session->state)->toBe(SessionState::HumanHandoff)
        ->and($session->context['handoff_reason'])->toBe('possible_urgency');
});

it('offers reception when receiving a non text message', function () {
    $messages = app(BotService::class)->handleIncomingMessage('5595984000004', null, true);

    expect($messages[0])->toContain('apenas por texto')->toContain('atendente');
});

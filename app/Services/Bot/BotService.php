<?php

namespace App\Services\Bot;

use App\Enums\AppointmentStatus;
use App\Enums\SessionState;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\WhatsappSession;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

class BotService
{
    public function __construct(protected AppointmentService $appointments) {}

    /** @return array<int, string> */
    public function handleIncomingMessage(string $phone, ?string $text, bool $hasNonTextMessage): array
    {
        $patient = Patient::query()->firstOrCreate(['phone' => $phone], ['active' => true]);
        $session = WhatsappSession::query()->firstOrCreate(['phone' => $phone],
            ['patient_id' => $patient->id, 'state' => SessionState::Idle, 'context' => [], 'last_interaction_at' => now()]);
        if ($session->patient_id !== $patient->id) {
            $session->update(['patient_id' => $patient->id]);
        }

        $text = trim((string) $text);
        $normalized = $this->normalize($text);

        if ($this->wantsHuman($normalized)) {
            return [$this->requestHumanHandoff($session, $text)];
        }

        if ($session->state === SessionState::HumanHandoff) {
            if ($this->wantsBot($normalized)) {
                $this->reset($session);
                $this->touch($session);

                return ['Tudo certo, o atendimento automático voltou 😊', ...$this->welcome($session)];
            }

            $this->rememberHandoffMessage($session, $text);

            // Enquanto uma pessoa atende, o bot não disputa a conversa.
            return [];
        }

        if ($this->looksUrgent($normalized)) {
            return [$this->requestUrgentHandoff($session, $text)];
        }

        if ($hasNonTextMessage) {
            $this->touch($session);

            return ['Recebi sua mensagem 😊 Por enquanto consigo atender apenas por texto. Você pode escrever sua dúvida ou digitar *atendente* para falar com a recepção.'];
        }

        if ($text === '') {
            return [];
        }

        if ($session->isExpired(config('clinic.session_timeout_minutes'))) {
            $this->reset($session);
        }
        if (($global = $this->globalCommand($session, $patient, $normalized)) !== null) {
            $this->touch($session);

            return $global;
        }

        $replies = match ($session->state) {
            SessionState::Idle => $this->handleFirstMessage($session, $patient, $normalized),
            SessionState::AwaitingAction => $this->handleAction($session, $patient, $normalized),
            SessionState::AwaitingName => $this->handleName($session, $patient, $text),
            SessionState::AwaitingProcedure => $this->handleProcedure($session, $normalized),
            SessionState::AwaitingDentist => $this->handleDentist($session, $normalized),
            SessionState::AwaitingDate => $this->handleDate($session, $text),
            SessionState::AwaitingTime => $this->handleTime($session, $text),
            SessionState::AwaitingConfirmation => $this->handleBookingConfirmation($session, $patient, $normalized),
            SessionState::AwaitingAppointmentToCancel => $this->handleAppointmentToCancel($session, $patient, $normalized),
            SessionState::AwaitingCancellationConfirmation => $this->handleCancellationConfirmation($session, $patient, $normalized),
            SessionState::AwaitingAppointmentToReschedule => $this->handleAppointmentToReschedule($session, $patient, $normalized),
            SessionState::AwaitingAnythingElse => $this->handleAnythingElse($session, $normalized),
            SessionState::HumanHandoff => [],
        };
        $this->touch($session);

        return $replies;
    }

    protected function globalCommand(WhatsappSession $session, Patient $patient, string $text): ?array
    {
        if (in_array($text, ['menu', 'inicio', 'iniciar', 'recomecar', 'comecar de novo'], true) || $this->isGreeting($text)) {
            $this->reset($session);

            return $this->welcome($session);
        }
        if (in_array($text, ['sair', 'parar', 'cancelar'], true) && $session->state !== SessionState::AwaitingAction) {
            $this->reset($session);

            return ['Atendimento atual encerrado. Digite *menu* quando quiser começar novamente.'];
        }
        if (in_array($text, ['status', 'minhas consultas', 'consultas'], true)) {
            return [$this->appointmentsMessage($patient)];
        }
        if (in_array($text, ['confirmar', 'confirmar consulta'], true)) {
            $appointment = $this->futureAppointments($patient)->first();
            if (! $appointment) {
                return ['Você não possui consulta futura para confirmar.'];
            }
            $this->appointments->confirm($appointment);
            $this->awaitAnythingElse($session);

            return ['Consulta confirmada ✅ '.$this->appointmentLine($appointment->fresh())."\n\nPosso ajudar com mais alguma coisa?\n\n*1* — Sim, voltar ao menu\n*2* — Não, encerrar atendimento"];
        }
        if ($text === 'ajuda') {
            return ['Sou a assistente virtual da '.config('clinic.name').".\n\nDigite *menu* para agendar, consultar, cancelar ou reagendar.\nDigite *confirmar* para confirmar sua próxima consulta.\nDigite *atendente* para falar com a recepção.\nDigite *sair* para abandonar o fluxo atual."];
        }

        return null;
    }

    protected function welcome(WhatsappSession $session): array
    {
        $session->update(['state' => SessionState::AwaitingAction, 'context' => []]);

        return ['Olá! Que bom ter você por aqui 😊\nEu sou a assistente virtual da *'.config('clinic.name')."*.\n\nComo posso ajudar?\n\n*1* — Agendar consulta\n*2* — Minhas consultas\n*3* — Cancelar consulta\n*4* — Reagendar consulta\n\nPode responder com o número ou escrever do seu jeito. Se preferir, digite *atendente* para falar com a recepção."];
    }

    protected function handleAction(WhatsappSession $session, Patient $patient, string $text): array
    {
        return match ($this->actionIntent($text)) {
            'book' => $this->beginBooking($session, $patient),
            'list' => [$this->appointmentsMessage($patient), ...$this->welcome($session)],
            'cancel' => $this->beginCancellation($session, $patient),
            'reschedule' => $this->beginReschedule($session, $patient),
            default => ["Não entendi bem o que você precisa. Você pode responder com *1*, *2*, *3* ou *4*, ou escrever algo como *quero agendar*.\n\nSe preferir falar com uma pessoa, digite *atendente*."],
        };
    }

    protected function handleFirstMessage(WhatsappSession $session, Patient $patient, string $text): array
    {
        $welcome = $this->welcome($session);

        if ($this->actionIntent($text) === null) {
            return $welcome;
        }

        return [$welcome[0], ...$this->handleAction($session, $patient, $text)];
    }

    protected function beginBooking(WhatsappSession $session, Patient $patient): array
    {
        if (! $patient->active) {
            return ['Seu cadastro precisa ser verificado pela recepção. Entre em contato pelo telefone da clínica.'];
        }
        if (blank($patient->name)) {
            $session->update(['state' => SessionState::AwaitingName, 'context' => []]);

            return ['Claro, vamos encontrar um horário para você 😊 Antes de começar, qual é o seu nome completo?'];
        }

        return ["Claro, {$patient->name}! Vamos encontrar um horário para você 😊", ...$this->askProcedure($session)];
    }

    protected function handleName(WhatsappSession $session, Patient $patient, string $text): array
    {
        if (mb_strlen($text) < 3 || ! preg_match('/[[:alpha:]]/u', $text)) {
            return ['Informe seu nome completo, por favor.'];
        }
        $patient->update(['name' => Str::of($text)->squish()->title()->value()]);

        return ["Prazer, {$patient->fresh()->name}!", ...$this->askProcedure($session)];
    }

    protected function askProcedure(WhatsappSession $session): array
    {
        $procedures = Procedure::query()->where('active', true)->orderBy('name')->get();
        if ($procedures->isEmpty()) {
            $this->reset($session);

            return ['A agenda ainda não possui procedimentos disponíveis. Fale com a recepção.'];
        }
        $session->update(['state' => SessionState::AwaitingProcedure, 'context' => ['procedure_ids' => $procedures->modelKeys()]]);

        return ["Qual atendimento você deseja?\n\n".$this->numbered($procedures, fn (Procedure $p) => $p->name.' — '.$p->duration_minutes.' min')."\n\nPode mandar o número ou o nome do procedimento."];
    }

    protected function handleProcedure(WhatsappSession $session, string $text): array
    {
        $procedure = $this->selectedModel(Procedure::class, $session->context['procedure_ids'] ?? [], $text);
        if (! $procedure) {
            return ['Não consegui identificar o procedimento. Envie o número ou o nome de uma das opções mostradas. Se tiver dúvida, digite *atendente*.'];
        }
        $dentists = $procedure->dentists()->where('active', true)->orderBy('name')->get();
        if ($dentists->isEmpty()) {
            return ['Nenhum profissional está disponível para esse procedimento. Escolha outro ou fale com a recepção.'];
        }
        $session->update(['state' => SessionState::AwaitingDentist, 'context' => ['procedure_id' => $procedure->id, 'dentist_ids' => $dentists->modelKeys()]]);

        return ["Certo! Com qual profissional você prefere agendar?\n\n".$this->numbered($dentists, fn (Dentist $d) => $d->name.($d->specialty ? ' — '.$d->specialty : ''))."\n\nPode mandar o número ou o nome do profissional."];
    }

    protected function handleDentist(WhatsappSession $session, string $text): array
    {
        $dentist = $this->selectedModel(Dentist::class, $session->context['dentist_ids'] ?? [], $text);
        if (! $dentist) {
            return ['Não consegui identificar o profissional. Envie o número ou o nome de uma das opções mostradas.'];
        }
        $context = $session->context;
        $context['dentist_id'] = $dentist->id;
        unset($context['dentist_ids']);
        $session->update(['state' => SessionState::AwaitingDate, 'context' => $context]);

        return ['Ótimo! Qual data você prefere? Pode escrever *amanhã* ou usar o formato *DD/MM/AAAA*.'];
    }

    protected function handleDate(WhatsappSession $session, string $text): array
    {
        $date = $this->parseDate($text);
        if (! $date || $date->isBefore(today()) || $date->isAfter(today()->addDays(config('clinic.booking.max_days_ahead')))) {
            return ['Não consegui entender essa data. Escreva *amanhã* ou informe uma data entre hoje e '.today()->addDays(config('clinic.booking.max_days_ahead'))->format('d/m/Y').' no formato *DD/MM/AAAA*.'];
        }
        $dentist = Dentist::query()->find($session->context['dentist_id'] ?? null);
        $procedure = Procedure::query()->find($session->context['procedure_id'] ?? null);
        if (! $dentist || ! $procedure) {
            $this->reset($session);

            return ['Não consegui recuperar os dados do agendamento. Digite *menu* para recomeçar.'];
        }
        $slots = $this->appointments->availableSlots($dentist, $procedure, $date);
        if ($slots->isEmpty()) {
            return ['Poxa, não encontrei horários livres nessa data. Você pode enviar outra data no formato *DD/MM/AAAA* ou digitar *atendente* para falar com a recepção.'];
        }
        $context = $session->context;
        $context['date'] = $date->format('Y-m-d');
        $context['slots'] = $slots->map(fn (CarbonImmutable $s) => $s->toIso8601String())->all();
        $session->update(['state' => SessionState::AwaitingTime, 'context' => $context]);

        return ["Horários disponíveis em {$date->format('d/m/Y')}:\n\n".$this->numbered($slots, fn (CarbonImmutable $s) => $s->format('H:i'))];
    }

    protected function handleTime(WhatsappSession $session, string $text): array
    {
        $slots = collect($session->context['slots'] ?? []);
        $index = $this->slotIndex($text, $slots);
        if ($index === null) {
            return ['Não consegui identificar o horário. Envie o número mostrado na lista ou escreva o horário, por exemplo *14h* ou *14:30*.'];
        }
        $context = $session->context;
        $context['starts_at'] = $slots[$index];
        unset($context['slots']);
        $session->update(['state' => SessionState::AwaitingConfirmation, 'context' => $context]);

        return [$this->bookingSummary($context)."\n\nConfirma? Responda *sim* ou *não*."];
    }

    protected function handleBookingConfirmation(WhatsappSession $session, Patient $patient, string $text): array
    {
        if ($this->isNegative($text)) {
            $this->reset($session);

            return ['Agendamento descartado. Digite *menu* para escolher outra opção.'];
        }
        if (! $this->isAffirmative($text)) {
            return ['Só para eu não marcar errado: responda *sim* para confirmar ou *não* para descartar.'];
        }
        $context = $session->context;
        try {
            if (! empty($context['reschedule_appointment_id'])) {
                $appointment = Appointment::query()->where('patient_id', $patient->id)->findOrFail($context['reschedule_appointment_id']);
                $appointment = $this->appointments->reschedule($appointment, $context['starts_at']);
                $message = 'Consulta reagendada com sucesso ✅';
            } else {
                $appointment = $this->appointments->create($patient, Dentist::findOrFail($context['dentist_id']), Procedure::findOrFail($context['procedure_id']), $context['starts_at'], 'whatsapp');
                $message = 'Consulta agendada com sucesso ✅';
            }
        } catch (DomainException $e) {
            $session->update(['state' => SessionState::AwaitingDate]);

            return [$e->getMessage().' Envie outra data no formato *DD/MM/AAAA*.'];
        }
        $this->awaitAnythingElse($session);

        return [$message."\n".$this->appointmentLine($appointment)."\n\nPosso ajudar com mais alguma coisa?\n\n*1* — Sim, voltar ao menu\n*2* — Não, encerrar atendimento"];
    }

    protected function beginCancellation(WhatsappSession $session, Patient $patient): array
    {
        $items = $this->futureAppointments($patient);
        if ($items->isEmpty()) {
            return ['Você não possui consultas futuras para cancelar.', ...$this->welcome($session)];
        }
        $session->update(['state' => SessionState::AwaitingAppointmentToCancel, 'context' => ['appointment_ids' => $items->modelKeys()]]);

        return ["Qual consulta deseja cancelar?\n\n".$this->numbered($items, fn (Appointment $a) => $this->appointmentLine($a))];
    }

    protected function handleAppointmentToCancel(WhatsappSession $session, Patient $patient, string $text): array
    {
        $appointment = $this->selectedModel(Appointment::class, $session->context['appointment_ids'] ?? [], $text);
        if (! $appointment || $appointment->patient_id !== $patient->id) {
            return ['Escolha a consulta pelo número exibido na lista.'];
        }
        $session->update(['state' => SessionState::AwaitingCancellationConfirmation, 'context' => ['cancel_appointment_id' => $appointment->id]]);

        return ['Confirma o cancelamento de '.$this->appointmentLine($appointment).'? Responda *sim* ou *não*.'];
    }

    protected function handleCancellationConfirmation(WhatsappSession $session, Patient $patient, string $text): array
    {
        if ($this->isNegative($text)) {
            $this->reset($session);

            return ['Cancelamento descartado. Digite *menu* para voltar.'];
        }
        if (! $this->isAffirmative($text)) {
            return ['Responda *sim* para cancelar ou *não* para manter a consulta.'];
        }
        $appointment = Appointment::query()->where('patient_id', $patient->id)->find($session->context['cancel_appointment_id'] ?? null);
        if (! $appointment) {
            $this->reset($session);

            return ['Consulta não encontrada. Digite *menu* para recomeçar.'];
        }
        try {
            $this->appointments->cancel($appointment, 'Cancelado pelo paciente via WhatsApp');
        } catch (DomainException $e) {
            return [$e->getMessage()];
        }
        $this->awaitAnythingElse($session);

        return ["Consulta cancelada com sucesso.\n\nPosso ajudar com mais alguma coisa?\n\n*1* — Sim, voltar ao menu\n*2* — Não, encerrar atendimento"];
    }

    protected function beginReschedule(WhatsappSession $session, Patient $patient): array
    {
        $items = $this->futureAppointments($patient);
        if ($items->isEmpty()) {
            return ['Você não possui consultas futuras para reagendar.', ...$this->welcome($session)];
        }
        $session->update(['state' => SessionState::AwaitingAppointmentToReschedule, 'context' => ['appointment_ids' => $items->modelKeys()]]);

        return ["Qual consulta deseja reagendar?\n\n".$this->numbered($items, fn (Appointment $a) => $this->appointmentLine($a))];
    }

    protected function handleAppointmentToReschedule(WhatsappSession $session, Patient $patient, string $text): array
    {
        $appointment = $this->selectedModel(Appointment::class, $session->context['appointment_ids'] ?? [], $text);
        if (! $appointment || $appointment->patient_id !== $patient->id) {
            return ['Escolha a consulta pelo número exibido na lista.'];
        }
        $session->update(['state' => SessionState::AwaitingDate, 'context' => ['reschedule_appointment_id' => $appointment->id, 'procedure_id' => $appointment->procedure_id, 'dentist_id' => $appointment->dentist_id]]);

        return ['Informe a nova data no formato *DD/MM/AAAA*.'];
    }

    protected function handleAnythingElse(WhatsappSession $session, string $text): array
    {
        if (in_array($text, ['1', 'sim', 's', 'quero', 'preciso'], true)) {
            return $this->welcome($session);
        }

        if (in_array($text, ['2', 'nao', 'n', 'encerrar', 'finalizar', 'so isso', 'era so isso'], true)) {
            $this->reset($session);

            return ['Tudo certo! Atendimento encerrado. Obrigado por falar com a gente e até mais 😊'];
        }

        return ["Só para eu entender: você precisa de mais alguma coisa?\n\n*1* — Sim, voltar ao menu\n*2* — Não, encerrar atendimento"];
    }

    protected function appointmentsMessage(Patient $patient): string
    {
        $items = $this->futureAppointments($patient);

        return $items->isEmpty() ? 'Você não possui consultas futuras.' : "Suas próximas consultas:\n\n".$this->numbered($items, fn (Appointment $a) => $this->appointmentLine($a));
    }

    protected function futureAppointments(Patient $patient): Collection
    {
        return $patient->appointments()->with(['dentist', 'procedure'])->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])->where('starts_at', '>=', now())->orderBy('starts_at')->get();
    }

    protected function appointmentLine(Appointment $appointment): string
    {
        $appointment->loadMissing(['dentist', 'procedure']);

        return $appointment->starts_at->format('d/m/Y \à\s H:i').' — '.$appointment->procedure->name.' com '.$appointment->dentist->name;
    }

    protected function bookingSummary(array $context): string
    {
        $dentist = Dentist::find($context['dentist_id']);
        $procedure = Procedure::find($context['procedure_id']);
        $start = CarbonImmutable::parse($context['starts_at']);

        return "Confira seu agendamento:\n\nProcedimento: *{$procedure->name}*\nProfissional: *{$dentist->name}*\nData: *{$start->format('d/m/Y')}*\nHorário: *{$start->format('H:i')}*";
    }

    protected function parseDate(string $text): ?CarbonImmutable
    {
        $normalized = $this->normalize($text);

        if ($normalized === 'hoje' || Str::contains($normalized, ['pode ser hoje', 'para hoje'])) {
            return CarbonImmutable::today(config('app.timezone'));
        }

        if ($normalized === 'amanha' || Str::contains($normalized, ['pode ser amanha', 'para amanha'])) {
            return CarbonImmutable::today(config('app.timezone'))->addDay();
        }

        if (preg_match('/\b(\d{2}\/\d{2}\/\d{4})\b/', $text, $matches)) {
            $text = $matches[1];
        }

        try {
            $date = CarbonImmutable::createFromFormat('!d/m/Y', trim($text), config('app.timezone'));

            return $date && $date->format('d/m/Y') === trim($text) ? $date : null;
        } catch (Throwable) {
            return null;
        }
    }

    protected function selectedModel(string $model, array $ids, string $choice): mixed
    {
        $i = $this->choiceIndex($choice, count($ids));

        if ($i !== null) {
            return $model::query()->find($ids[$i]);
        }

        $choice = $this->normalize($choice);
        $choiceTokens = $this->meaningfulTokens($choice);
        $matches = collect($ids)->map(function (int|string $id) use ($model, $choice, $choiceTokens) {
            $record = $model::query()->find($id);
            if (! $record || blank(data_get($record, 'name'))) {
                return null;
            }

            $name = $this->normalize((string) $record->name);
            $score = ($choice === $name || Str::contains($choice, $name))
                ? 100
                : count(array_intersect($choiceTokens, $this->meaningfulTokens($name)));

            return $score > 0 ? ['record' => $record, 'score' => $score] : null;
        })->filter()->sortByDesc('score')->values();

        if ($matches->isEmpty() || ($matches->count() > 1 && $matches[0]['score'] === $matches[1]['score'])) {
            return null;
        }

        return $matches[0]['record'];
    }

    protected function choiceIndex(string $choice, int $count): ?int
    {
        $choice = $this->normalize($choice);

        if (! ctype_digit($choice)) {
            return null;
        } $i = (int) $choice - 1;

        return $i >= 0 && $i < $count ? $i : null;
    }

    protected function slotIndex(string $choice, Collection $slots): ?int
    {
        $index = $this->choiceIndex($choice, $slots->count());
        if ($index !== null) {
            return $index;
        }

        $choice = $this->normalize($choice);
        $hour = null;
        $minute = 0;

        if (preg_match('/\b([01]?\d|2[0-3])(?::|h)([0-5]\d)?\b/', $choice, $matches)) {
            $hour = (int) $matches[1];
            $minute = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : 0;
        } elseif (preg_match('/\b([01]?\d|2[0-3])\s*horas?\b/', $choice, $matches)) {
            $hour = (int) $matches[1];
        }

        if ($hour === null) {
            return null;
        }

        $wanted = sprintf('%02d:%02d', $hour, $minute);
        $found = $slots->search(fn (string $slot) => CarbonImmutable::parse($slot)->format('H:i') === $wanted);

        return $found === false ? null : (int) $found;
    }

    protected function numbered(Collection $items, callable $label): string
    {
        return $items->values()->map(fn ($item, int $i) => ($i + 1).'. '.$label($item))->join("\n");
    }

    protected function reset(WhatsappSession $session): void
    {
        $session->update(['state' => SessionState::Idle, 'context' => []]);
    }

    protected function awaitAnythingElse(WhatsappSession $session): void
    {
        $session->update(['state' => SessionState::AwaitingAnythingElse, 'context' => []]);
    }

    protected function touch(WhatsappSession $session): void
    {
        $session->update(['last_interaction_at' => now()]);
    }

    protected function actionIntent(string $text): ?string
    {
        return match (true) {
            $text === '1' => 'book',
            $text === '2' => 'list',
            $text === '3' => 'cancel',
            $text === '4' => 'reschedule',
            Str::contains($text, ['reagendar', 'remarcar', 'mudar a data', 'trocar a data', 'mudar meu horario']) => 'reschedule',
            $text === 'cancelar' || Str::contains($text, ['cancelar consulta', 'desmarcar consulta', 'desmarcar horario']) => 'cancel',
            Str::contains($text, ['minhas consultas', 'meus agendamentos', 'ver consulta', 'consultar horario', 'tenho consulta']) => 'list',
            Str::contains($text, ['agendar', 'agendamento', 'quero marcar', 'marcar consulta', 'marcar horario', 'quero uma consulta']) => 'book',
            default => null,
        };
    }

    protected function normalize(string $text): string
    {
        return Str::of($text)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9:\/\s]/', ' ')
            ->squish()
            ->value();
    }

    /** @return array<int, string> */
    protected function meaningfulTokens(string $text): array
    {
        $ignored = ['com', 'das', 'dos', 'uma', 'para', 'por', 'que', 'quero', 'prefiro', 'doutor', 'doutora', 'dra', 'dr'];

        return collect(preg_split('/\s+/', $this->normalize($text)) ?: [])
            ->filter(fn (string $token) => mb_strlen($token) >= 3 && ! in_array($token, $ignored, true))
            ->unique()
            ->values()
            ->all();
    }

    protected function isGreeting(string $text): bool
    {
        return in_array($text, ['oi', 'ola', 'bom dia', 'boa tarde', 'boa noite', 'e ai', 'tudo bem'], true);
    }

    protected function isAffirmative(string $text): bool
    {
        return in_array($text, ['sim', 's', 'isso', 'isso mesmo', 'correto', 'confirmo', 'pode confirmar', 'sim por favor'], true);
    }

    protected function isNegative(string $text): bool
    {
        return in_array($text, ['nao', 'n', 'negativo', 'nao quero', 'pode cancelar', 'cancelar'], true);
    }

    protected function wantsHuman(string $text): bool
    {
        return Str::contains($text, ['atendente', 'recepcao', 'falar com alguem', 'falar com uma pessoa', 'pessoa de verdade']);
    }

    protected function wantsBot(string $text): bool
    {
        return in_array($text, ['menu', 'voltar ao bot', 'voltar pro bot', 'atendimento automatico', 'retomar atendimento'], true);
    }

    protected function looksUrgent(string $text): bool
    {
        return Str::contains($text, [
            'dor muito forte',
            'dor forte',
            'dor intensa',
            'dor insuportavel',
            'sangramento intenso',
            'nao para de sangrar',
            'rosto inchado',
            'inchaco no rosto',
            'dificuldade para respirar',
            'dificuldade de respirar',
            'quebrei o dente',
            'bati o dente',
            'trauma no dente',
            'dente caiu',
        ]);
    }

    protected function requestHumanHandoff(WhatsappSession $session, string $message): string
    {
        $context = $session->context ?? [];
        $context['handoff_reason'] = 'manual';
        $context['handoff_requested_at'] = now()->toIso8601String();
        $context['last_patient_message'] = $message;

        $session->update([
            'state' => SessionState::HumanHandoff,
            'context' => $context,
            'last_interaction_at' => now(),
        ]);

        return "Claro 😊 Encaminhei sua conversa para a recepção. Você pode deixar sua mensagem por aqui; o bot ficará em silêncio enquanto uma pessoa continua o atendimento.\n\nSe quiser voltar ao atendimento automático, escreva *voltar ao bot*.";
    }

    protected function requestUrgentHandoff(WhatsappSession $session, string $message): string
    {
        $context = $session->context ?? [];
        $context['handoff_reason'] = 'possible_urgency';
        $context['handoff_requested_at'] = now()->toIso8601String();
        $context['last_patient_message'] = $message;

        $session->update([
            'state' => SessionState::HumanHandoff,
            'context' => $context,
            'last_interaction_at' => now(),
        ]);

        return "Sinto muito que você esteja passando por isso. Sua mensagem pode precisar de avaliação rápida, e eu não consigo avaliar sintomas ou fazer diagnóstico por aqui. Encaminhei a conversa para a recepção.\n\nSe você considerar uma emergência ou não puder aguardar, procure imediatamente um serviço presencial de urgência da sua região. Em caso de risco imediato à vida, ligue *192*.";
    }

    protected function rememberHandoffMessage(WhatsappSession $session, string $message): void
    {
        $context = $session->context ?? [];

        if ($message !== '') {
            $context['last_patient_message'] = $message;
        }

        $session->update(['context' => $context, 'last_interaction_at' => now()]);
    }
}

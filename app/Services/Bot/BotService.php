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
        if ($hasNonTextMessage) {
            $this->touch($session);

            return ['No momento eu entendo apenas mensagens de texto. Digite *menu* para começar 🙂'];
        }

        $text = trim((string) $text);
        if ($text === '') {
            return [];
        }
        $normalized = Str::of($text)->ascii()->lower()->squish()->value();
        if ($session->isExpired(config('clinic.session_timeout_minutes'))) {
            $this->reset($session);
        }
        if (($global = $this->globalCommand($session, $patient, $normalized)) !== null) {
            $this->touch($session);

            return $global;
        }

        $replies = match ($session->state) {
            SessionState::Idle => $this->welcome($session),
            SessionState::AwaitingAction => $this->handleAction($session, $patient, $normalized),
            SessionState::AwaitingName => $this->handleName($session, $patient, $text),
            SessionState::AwaitingProcedure => $this->handleProcedure($session, $normalized),
            SessionState::AwaitingDentist => $this->handleDentist($session, $normalized),
            SessionState::AwaitingDate => $this->handleDate($session, $text),
            SessionState::AwaitingTime => $this->handleTime($session, $normalized),
            SessionState::AwaitingConfirmation => $this->handleBookingConfirmation($session, $patient, $normalized),
            SessionState::AwaitingAppointmentToCancel => $this->handleAppointmentToCancel($session, $patient, $normalized),
            SessionState::AwaitingCancellationConfirmation => $this->handleCancellationConfirmation($session, $patient, $normalized),
            SessionState::AwaitingAppointmentToReschedule => $this->handleAppointmentToReschedule($session, $patient, $normalized),
        };
        $this->touch($session);

        return $replies;
    }

    protected function globalCommand(WhatsappSession $session, Patient $patient, string $text): ?array
    {
        if (in_array($text, ['menu', 'inicio', 'iniciar', 'oi', 'ola'], true)) {
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

            return ['Consulta confirmada ✅ '.$this->appointmentLine($appointment->fresh())];
        }
        if ($text === 'ajuda') {
            return ['Sou a assistente virtual da '.config('clinic.name').".\n\nDigite *menu* para agendar, consultar, cancelar ou reagendar.\nDigite *confirmar* para confirmar sua próxima consulta.\nDigite *sair* para abandonar o fluxo atual."];
        }

        return null;
    }

    protected function welcome(WhatsappSession $session): array
    {
        $session->update(['state' => SessionState::AwaitingAction, 'context' => []]);

        return ['Olá! Bem-vindo(a) à *'.config('clinic.name')."* 😁\n\n1. Agendar consulta\n2. Minhas consultas\n3. Cancelar consulta\n4. Reagendar consulta\n\nResponda com o número da opção."];
    }

    protected function handleAction(WhatsappSession $session, Patient $patient, string $text): array
    {
        return match ($text) {
            '1', 'agendar' => $this->beginBooking($session, $patient),
            '2', 'minhas consultas', 'consultas' => [$this->appointmentsMessage($patient), ...$this->welcome($session)],
            '3', 'cancelar consulta' => $this->beginCancellation($session, $patient),
            '4', 'reagendar', 'reagendar consulta' => $this->beginReschedule($session, $patient),
            default => ['Opção inválida. Responda com *1*, *2*, *3* ou *4*.'],
        };
    }

    protected function beginBooking(WhatsappSession $session, Patient $patient): array
    {
        if (! $patient->active) {
            return ['Seu cadastro precisa ser verificado pela recepção. Entre em contato pelo telefone da clínica.'];
        }
        if (blank($patient->name)) {
            $session->update(['state' => SessionState::AwaitingName, 'context' => []]);

            return ['Antes de agendar, qual é o seu nome completo?'];
        }

        return $this->askProcedure($session);
    }

    protected function handleName(WhatsappSession $session, Patient $patient, string $text): array
    {
        if (mb_strlen($text) < 3 || ! preg_match('/[[:alpha:]]/u', $text)) {
            return ['Informe seu nome completo, por favor.'];
        }
        $patient->update(['name' => Str::of($text)->squish()->title()->value()]);

        return $this->askProcedure($session);
    }

    protected function askProcedure(WhatsappSession $session): array
    {
        $procedures = Procedure::query()->where('active', true)->orderBy('name')->get();
        if ($procedures->isEmpty()) {
            $this->reset($session);

            return ['A agenda ainda não possui procedimentos disponíveis. Fale com a recepção.'];
        }
        $session->update(['state' => SessionState::AwaitingProcedure, 'context' => ['procedure_ids' => $procedures->modelKeys()]]);

        return ["Qual atendimento você deseja?\n\n".$this->numbered($procedures, fn (Procedure $p) => $p->name.' — '.$p->duration_minutes.' min')];
    }

    protected function handleProcedure(WhatsappSession $session, string $text): array
    {
        $procedure = $this->selectedModel(Procedure::class, $session->context['procedure_ids'] ?? [], $text);
        if (! $procedure) {
            return ['Escolha o procedimento pelo número exibido na lista.'];
        }
        $dentists = $procedure->dentists()->where('active', true)->orderBy('name')->get();
        if ($dentists->isEmpty()) {
            return ['Nenhum profissional está disponível para esse procedimento. Escolha outro ou fale com a recepção.'];
        }
        $session->update(['state' => SessionState::AwaitingDentist, 'context' => ['procedure_id' => $procedure->id, 'dentist_ids' => $dentists->modelKeys()]]);

        return ["Escolha o profissional:\n\n".$this->numbered($dentists, fn (Dentist $d) => $d->name.($d->specialty ? ' — '.$d->specialty : ''))];
    }

    protected function handleDentist(WhatsappSession $session, string $text): array
    {
        $dentist = $this->selectedModel(Dentist::class, $session->context['dentist_ids'] ?? [], $text);
        if (! $dentist) {
            return ['Escolha o profissional pelo número exibido na lista.'];
        }
        $context = $session->context;
        $context['dentist_id'] = $dentist->id;
        unset($context['dentist_ids']);
        $session->update(['state' => SessionState::AwaitingDate, 'context' => $context]);

        return ['Qual data você prefere? Digite no formato *DD/MM/AAAA*.'];
    }

    protected function handleDate(WhatsappSession $session, string $text): array
    {
        $date = $this->parseDate($text);
        if (! $date || $date->isBefore(today()) || $date->isAfter(today()->addDays(config('clinic.booking.max_days_ahead')))) {
            return ['Data inválida. Informe uma data entre hoje e '.today()->addDays(config('clinic.booking.max_days_ahead'))->format('d/m/Y').'.'];
        }
        $dentist = Dentist::query()->find($session->context['dentist_id'] ?? null);
        $procedure = Procedure::query()->find($session->context['procedure_id'] ?? null);
        if (! $dentist || ! $procedure) {
            $this->reset($session);

            return ['Não consegui recuperar os dados do agendamento. Digite *menu* para recomeçar.'];
        }
        $slots = $this->appointments->availableSlots($dentist, $procedure, $date);
        if ($slots->isEmpty()) {
            return ['Não há horários livres nessa data. Envie outra data no formato *DD/MM/AAAA*.'];
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
        $index = $this->choiceIndex($text, $slots->count());
        if ($index === null) {
            return ['Escolha o horário pelo número exibido na lista.'];
        }
        $context = $session->context;
        $context['starts_at'] = $slots[$index];
        unset($context['slots']);
        $session->update(['state' => SessionState::AwaitingConfirmation, 'context' => $context]);

        return [$this->bookingSummary($context)."\n\nConfirma? Responda *sim* ou *não*."];
    }

    protected function handleBookingConfirmation(WhatsappSession $session, Patient $patient, string $text): array
    {
        if (in_array($text, ['nao', 'n'], true)) {
            $this->reset($session);

            return ['Agendamento descartado. Digite *menu* para escolher outra opção.'];
        }
        if (! in_array($text, ['sim', 's'], true)) {
            return ['Responda *sim* para confirmar ou *não* para descartar.'];
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
        $this->reset($session);

        return [$message."\n".$this->appointmentLine($appointment)."\n\nQuando precisar, digite *menu*."];
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
        if (in_array($text, ['nao', 'n'], true)) {
            $this->reset($session);

            return ['Cancelamento descartado. Digite *menu* para voltar.'];
        }
        if (! in_array($text, ['sim', 's'], true)) {
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
        $this->reset($session);

        return ['Consulta cancelada. Se quiser escolher outra data, digite *menu*.'];
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

        return $i === null ? null : $model::query()->find($ids[$i]);
    }

    protected function choiceIndex(string $choice, int $count): ?int
    {
        if (! ctype_digit($choice)) {
            return null;
        } $i = (int) $choice - 1;

        return $i >= 0 && $i < $count ? $i : null;
    }

    protected function numbered(Collection $items, callable $label): string
    {
        return $items->values()->map(fn ($item, int $i) => ($i + 1).'. '.$label($item))->join("\n");
    }

    protected function reset(WhatsappSession $session): void
    {
        $session->update(['state' => SessionState::Idle, 'context' => []]);
    }

    protected function touch(WhatsappSession $session): void
    {
        $session->update(['last_interaction_at' => now()]);
    }
}

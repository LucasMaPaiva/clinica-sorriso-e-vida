<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    /** @return Collection<int, CarbonImmutable> */
    public function availableSlots(Dentist $dentist, Procedure $procedure, CarbonInterface|string $date): Collection
    {
        $day = $date instanceof CarbonInterface ? CarbonImmutable::instance($date)->startOfDay() : CarbonImmutable::parse($date, config('app.timezone'))->startOfDay();
        if ($day->isBefore(today()) || $day->isAfter(today()->addDays(config('clinic.booking.max_days_ahead')))) {
            return collect();
        }

        $availabilities = $dentist->availabilities()->where('weekday', $day->isoWeekday())->where('active', true)->orderBy('start_time')->get();
        $slots = collect();
        foreach ($availabilities as $availability) {
            $cursor = $day->setTimeFromTimeString($availability->start_time);
            $windowEnd = $day->setTimeFromTimeString($availability->end_time);
            while ($cursor->addMinutes($procedure->duration_minutes)->lessThanOrEqualTo($windowEnd)) {
                $endsAt = $cursor->addMinutes($procedure->duration_minutes);
                if ($cursor->greaterThanOrEqualTo(now()->addHours(config('clinic.booking.minimum_notice_hours'))) && $this->hasNoConflict($dentist->id, $cursor, $endsAt)) {
                    $slots->push($cursor);
                }
                $cursor = $cursor->addMinutes($availability->slot_interval_minutes);
            }
        }

        return $slots->unique(fn (CarbonImmutable $slot) => $slot->toIso8601String())->values();
    }

    public function create(Patient $patient, Dentist $dentist, Procedure $procedure, CarbonInterface|string $startsAt, string $source = 'admin', ?string $notes = null): Appointment
    {
        return DB::transaction(function () use ($patient, $dentist, $procedure, $startsAt, $source, $notes) {
            Dentist::query()->lockForUpdate()->findOrFail($dentist->id);
            $this->guardCanBook($dentist, $procedure, $startsAt);
            $start = CarbonImmutable::parse($startsAt, config('app.timezone'));

            return Appointment::query()->create(['patient_id' => $patient->id, 'dentist_id' => $dentist->id, 'procedure_id' => $procedure->id,
                'starts_at' => $start, 'ends_at' => $start->addMinutes($procedure->duration_minutes), 'status' => AppointmentStatus::Scheduled, 'source' => $source, 'notes' => $notes]);
        }, 3);
    }

    public function reschedule(Appointment $appointment, CarbonInterface|string $startsAt): Appointment
    {
        return DB::transaction(function () use ($appointment, $startsAt) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)) {
                throw new DomainException('Esta consulta não pode mais ser reagendada.');
            }
            Dentist::query()->lockForUpdate()->findOrFail($appointment->dentist_id);
            $this->guardCanBook($appointment->dentist, $appointment->procedure, $startsAt, $appointment->id);
            $start = CarbonImmutable::parse($startsAt, config('app.timezone'));
            $appointment->update(['starts_at' => $start, 'ends_at' => $start->addMinutes($appointment->procedure->duration_minutes), 'status' => AppointmentStatus::Scheduled,
                'confirmed_at' => null, 'reminder_24h_sent_at' => null, 'reminder_2h_sent_at' => null]);

            return $appointment->fresh();
        }, 3);
    }

    public function updateFromAdmin(Appointment $appointment, array $data): Appointment
    {
        return DB::transaction(function () use ($appointment, $data) {
            $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            $dentist = Dentist::query()->lockForUpdate()->findOrFail($data['dentist_id']);
            $procedure = Procedure::query()->findOrFail($data['procedure_id']);
            $start = CarbonImmutable::parse($data['starts_at'], config('app.timezone'));
            $this->guardCanBook($dentist, $procedure, $start, $appointment->id);
            $appointment->update(['patient_id' => $data['patient_id'], 'dentist_id' => $dentist->id, 'procedure_id' => $procedure->id,
                'starts_at' => $start, 'ends_at' => $start->addMinutes($procedure->duration_minutes), 'status' => $data['status'], 'notes' => $data['notes'] ?? null]);

            return $appointment->fresh();
        }, 3);
    }

    public function cancel(Appointment $appointment, ?string $reason = null): Appointment
    {
        if (! in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)) {
            throw new DomainException('Esta consulta não pode mais ser cancelada.');
        }
        $appointment->update(['status' => AppointmentStatus::Cancelled, 'cancellation_reason' => $reason]);

        return $appointment->fresh();
    }

    public function confirm(Appointment $appointment): Appointment
    {
        if ($appointment->status === AppointmentStatus::Scheduled) {
            $appointment->update(['status' => AppointmentStatus::Confirmed, 'confirmed_at' => now()]);
        }

        return $appointment->fresh();
    }

    protected function guardCanBook(Dentist $dentist, Procedure $procedure, CarbonInterface|string $startsAt, ?int $ignoreAppointmentId = null): void
    {
        if (! $dentist->active || ! $procedure->active || ! $dentist->procedures()->whereKey($procedure->id)->exists()) {
            throw new DomainException('Profissional ou procedimento indisponível.');
        }
        $start = CarbonImmutable::parse($startsAt, config('app.timezone'));
        $end = $start->addMinutes($procedure->duration_minutes);
        if ($start->lessThan(now()->addHours(config('clinic.booking.minimum_notice_hours')))) {
            throw new DomainException('Esse horário não respeita a antecedência mínima da clínica.');
        }
        $fits = $dentist->availabilities()->where('weekday', $start->isoWeekday())->where('active', true)->get()->contains(function ($availability) use ($start, $end) {
            $windowStart = $start->startOfDay()->setTimeFromTimeString($availability->start_time);
            $windowEnd = $start->startOfDay()->setTimeFromTimeString($availability->end_time);

            return $start->greaterThanOrEqualTo($windowStart) && $end->lessThanOrEqualTo($windowEnd);
        });
        if (! $fits || ! $this->hasNoConflict($dentist->id, $start, $end, $ignoreAppointmentId)) {
            throw new DomainException('Esse horário não está mais disponível. Escolha outro.');
        }
    }

    protected function hasNoConflict(int $dentistId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreAppointmentId = null): bool
    {
        $appointmentConflict = Appointment::query()->where('dentist_id', $dentistId)
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value, AppointmentStatus::InAttendance->value])
            ->when($ignoreAppointmentId, fn ($query) => $query->where('id', '!=', $ignoreAppointmentId))
            ->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
        $blockConflict = ScheduleBlock::query()->where('dentist_id', $dentistId)->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();

        return ! $appointmentConflict && ! $blockConflict;
    }
}

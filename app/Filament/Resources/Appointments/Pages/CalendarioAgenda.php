<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Concerns\NavigatesCalendarMonth;
use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Appointment;
use App\Models\Dentist;
use App\Services\AppointmentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CalendarioAgenda extends Page
{
    use NavigatesCalendarMonth;

    protected static string $resource = AppointmentResource::class;

    protected string $view = 'filament.resources.appointments-calendar';

    public ?int $dentistId = null;

    public function mount(): void
    {
        $this->mountCalendar();
    }

    public function getTitle(): string
    {
        return 'Agenda';
    }

    /** @return Collection<int, string> */
    public function dentists(): Collection
    {
        return Dentist::query()->where('active', true)->orderBy('name')->pluck('name', 'id');
    }

    /**
     * Semanas do mês (domingo a sábado), cada dia com a contagem de consultas ativas.
     *
     * @return array<int, array<int, array{date: string, day: int, inMonth: bool, isToday: bool, count: int, done: int}>>
     */
    public function weeks(): array
    {
        [$first, $start, $end] = $this->calendarRange();

        $perDay = $this->query()
            ->whereBetween('starts_at', [$start->startOfDay(), $end->endOfDay()])
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value])
            ->get(['starts_at', 'status'])
            ->groupBy(fn (Appointment $a) => $a->starts_at->toDateString());

        $weeks = [];

        for ($day = $start; $day <= $end; $day = $day->addDay()) {
            $items = $perDay->get($day->toDateString(), collect());

            $weeks[intdiv($day->diffInDays($start), 7)][] = [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'inMonth' => $day->month === $first->month,
                'isToday' => $day->isToday(),
                'count' => $items->count(),
                'done' => $items->where('status', AppointmentStatus::Completed)->count(),
            ];
        }

        return $weeks;
    }

    /** @return Collection<int, Appointment> */
    public function dayAppointments(): Collection
    {
        return $this->query()
            ->with(['patient', 'procedure', 'dentist'])
            ->whereDate('starts_at', $this->selected)
            ->orderBy('starts_at')
            ->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lista')->label('Ver em lista')->icon('heroicon-m-table-cells')->color('gray')
                ->url(AppointmentResource::getUrl('lista')),
            Action::make('nova')->label('Nova consulta')->icon('heroicon-m-plus')
                ->url(AppointmentResource::getUrl('create')),
        ];
    }

    public function confirm(int $id): void
    {
        $appointment = $this->query()->findOrFail($id);

        if ($appointment->status === AppointmentStatus::Scheduled) {
            app(AppointmentService::class)->confirm($appointment);
            Notification::make()->title('Consulta confirmada')->success()->send();
        }
    }

    public function start(int $id): void
    {
        $this->changeStatus($id, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], AppointmentStatus::InAttendance);
    }

    public function complete(int $id): void
    {
        $this->changeStatus($id, [AppointmentStatus::InAttendance], AppointmentStatus::Completed);
    }

    public function noShow(int $id): void
    {
        $this->changeStatus($id, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], AppointmentStatus::NoShow);
    }

    public function cancel(int $id): void
    {
        $appointment = $this->query()->findOrFail($id);

        if (in_array($appointment->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true)) {
            app(AppointmentService::class)->cancel($appointment, 'Cancelado pela recepção');
            Notification::make()->title('Consulta cancelada')->success()->send();
        }
    }

    /** @param array<int, AppointmentStatus> $from */
    private function changeStatus(int $id, array $from, AppointmentStatus $to): void
    {
        $appointment = $this->query()->findOrFail($id);

        if (in_array($appointment->status, $from, true)) {
            $appointment->update(['status' => $to]);
        }
    }

    public function editUrl(Appointment $appointment): string
    {
        return AppointmentResource::getUrl('edit', ['record' => $appointment]);
    }

    private function query(): Builder
    {
        return Appointment::query()
            ->when($this->dentistId, fn (Builder $q) => $q->where('dentist_id', $this->dentistId));
    }
}

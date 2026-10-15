<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $today = Appointment::query()->whereDate('starts_at', today());
        $active = $today->clone()->where('status', '!=', AppointmentStatus::Cancelled)->count();
        $confirmed = $today->clone()->whereIn('status', [AppointmentStatus::Confirmed, AppointmentStatus::InAttendance, AppointmentStatus::Completed])->count();

        $month = Appointment::query()->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()]);
        $completed = $month->clone()->where('status', AppointmentStatus::Completed)->count();
        $noShow = $month->clone()->where('status', AppointmentStatus::NoShow)->count();
        $attendance = $completed + $noShow > 0 ? round($completed / ($completed + $noShow) * 100) : null;
        $viaWhatsapp = $month->clone()->where('source', 'whatsapp')->count();
        $monthTotal = $month->clone()->count();

        return [
            Stat::make('Consultas de hoje', $active)
                ->description('Últimos 7 dias')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart($this->lastDays(7))
                ->color('primary'),
            Stat::make('Confirmadas hoje', $active > 0 ? "{$confirmed} de {$active}" : '0')
                ->description($active - $confirmed > 0 ? ($active - $confirmed).' aguardando confirmação' : 'Tudo confirmado')
                ->descriptionIcon($active - $confirmed > 0 ? 'heroicon-m-clock' : 'heroicon-m-check-circle')
                ->color($active - $confirmed > 0 ? 'warning' : 'success'),
            Stat::make('Comparecimento no mês', $attendance === null ? '—' : "{$attendance}%")
                ->description("{$completed} concluídas · {$noShow} faltas")
                ->descriptionIcon('heroicon-m-user-group')
                ->color($attendance === null || $attendance >= 80 ? 'success' : 'danger'),
            Stat::make('Agendadas pelo WhatsApp', $viaWhatsapp)
                ->description($monthTotal > 0 ? round($viaWhatsapp / $monthTotal * 100).'% das consultas do mês' : 'Nenhuma consulta no mês')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('success'),
        ];
    }

    /** @return array<int, int> */
    private function lastDays(int $days): array
    {
        $counts = Appointment::query()
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->whereBetween('starts_at', [today()->subDays($days - 1), today()->endOfDay()])
            ->pluck('starts_at')
            ->countBy(fn ($startsAt) => $startsAt->toDateString());

        return collect(range($days - 1, 0))
            ->map(fn (int $ago) => $counts->get(today()->subDays($ago)->toDateString(), 0))
            ->all();
    }
}

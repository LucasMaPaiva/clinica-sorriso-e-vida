<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $q = Appointment::query()->whereDate('starts_at', today());

        return [Stat::make('Consultas de hoje', $q->clone()->where('status', '!=', AppointmentStatus::Cancelled)->count())->icon('heroicon-o-calendar-days')->color('primary'), Stat::make('Confirmadas', $q->clone()->where('status', AppointmentStatus::Confirmed)->count())->color('success'), Stat::make('Concluídas', $q->clone()->where('status', AppointmentStatus::Completed)->count())->color('info'), Stat::make('Canceladas ou faltas', $q->clone()->whereIn('status', [AppointmentStatus::Cancelled, AppointmentStatus::NoShow])->count())->color('danger')];
    }
}

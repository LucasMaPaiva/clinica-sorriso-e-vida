<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class AppointmentsTrendChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Movimento da agenda';

    protected ?string $description = 'Consultas por dia (sem as canceladas)';

    protected ?string $maxHeight = '280px';

    public ?string $filter = 'month';

    protected function getFilters(): ?array
    {
        return [
            'week' => 'Próximos 7 dias',
            'month' => 'Este mês',
            'last30' => 'Últimos 30 dias',
        ];
    }

    protected function getData(): array
    {
        [$from, $to] = match ($this->filter) {
            'week' => [CarbonImmutable::today(), CarbonImmutable::today()->addDays(6)],
            'last30' => [CarbonImmutable::today()->subDays(29), CarbonImmutable::today()],
            default => [CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth()->startOfDay()],
        };

        $rows = Appointment::query()
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->whereBetween('starts_at', [$from, $to->endOfDay()])
            ->get(['starts_at', 'status']);

        $byDay = $rows->groupBy(fn ($a) => $a->starts_at->toDateString());
        $days = collect();
        for ($day = $from; $day->lte($to); $day = $day->addDay()) {
            $days->push($day);
        }

        $count = fn (CarbonImmutable $day, ?AppointmentStatus $status = null) => $byDay->get($day->toDateString(), collect())
            ->when($status, fn ($c) => $c->where('status', $status))
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Consultas',
                    'data' => $days->map(fn ($d) => $count($d))->all(),
                    'borderColor' => '#0d9488',
                    'backgroundColor' => 'rgba(13, 148, 136, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 3,
                ],
                [
                    'label' => 'Concluídas',
                    'data' => $days->map(fn ($d) => $count($d, AppointmentStatus::Completed))->all(),
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [6, 4],
                    'tension' => 0.35,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => $days->map(fn ($d) => $d->format('d/m'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}

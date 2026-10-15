<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Filament\Widgets\ChartWidget;

class StatusBreakdownChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Situação das consultas';

    protected ?string $description = 'Consultas deste mês por status';

    protected ?string $maxHeight = '260px';

    private const COLORS = [
        'scheduled' => '#94a3b8',
        'confirmed' => '#10b981',
        'in_attendance' => '#f59e0b',
        'completed' => '#0ea5e9',
        'cancelled' => '#ef4444',
        'no_show' => '#f97316',
    ];

    protected function getData(): array
    {
        $counts = Appointment::query()
            ->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $statuses = collect(AppointmentStatus::cases())->filter(fn ($s) => $counts->get($s->value, 0) > 0)->values();

        return [
            'datasets' => [[
                'data' => $statuses->map(fn ($s) => (int) $counts[$s->value])->all(),
                'backgroundColor' => $statuses->map(fn ($s) => self::COLORS[$s->value])->all(),
                'borderWidth' => 0,
                'hoverOffset' => 6,
            ]],
            'labels' => $statuses->map(fn ($s) => $s->getLabel())->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '65%',
            'plugins' => ['legend' => ['display' => true, 'position' => 'right']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}

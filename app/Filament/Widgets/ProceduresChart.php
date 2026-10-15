<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Procedure;
use Filament\Widgets\ChartWidget;

class ProceduresChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Procedimentos mais procurados';

    protected ?string $description = 'Consultas deste mês por procedimento';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $counts = Appointment::query()
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->toBase()
            ->selectRaw('procedure_id, count(*) as total')
            ->groupBy('procedure_id')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'procedure_id');

        $names = Procedure::query()->whereKey($counts->keys())->pluck('name', 'id');

        return [
            'datasets' => [[
                'label' => 'Consultas',
                'data' => $counts->values()->map(fn ($v) => (int) $v)->all(),
                'backgroundColor' => 'rgba(13, 148, 136, 0.75)',
                'hoverBackgroundColor' => '#0d9488',
                'borderRadius' => 6,
                'maxBarThickness' => 28,
            ]],
            'labels' => $counts->keys()->map(fn ($id) => $names->get($id, '—'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}

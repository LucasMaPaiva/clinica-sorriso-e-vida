<?php

namespace App\Filament\Concerns;

use Carbon\CarbonImmutable;

/** Navegação de mês e dia selecionado das páginas em calendário (Agenda). */
trait NavigatesCalendarMonth
{
    /** Primeiro dia do mês exibido (Y-m-d). */
    public string $month;

    /** Dia selecionado (Y-m-d). */
    public string $selected;

    protected function mountCalendar(): void
    {
        $this->month = today()->startOfMonth()->toDateString();
        $this->selected = today()->toDateString();
    }

    public function previousMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month)->subMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->month = CarbonImmutable::parse($this->month)->addMonth()->toDateString();
    }

    public function goToToday(): void
    {
        $this->mountCalendar();
    }

    public function selectDay(string $date): void
    {
        $day = CarbonImmutable::parse($date);

        $this->selected = $day->toDateString();
        $this->month = $day->startOfMonth()->toDateString();
    }

    public function monthLabel(): string
    {
        return CarbonImmutable::parse($this->month)->locale('pt_BR')->translatedFormat('F \d\e Y');
    }

    public function selectedLabel(): string
    {
        return CarbonImmutable::parse($this->selected)->locale('pt_BR')->translatedFormat('l, d \d\e F');
    }

    /**
     * Primeiro dia do mês e o intervalo visível da grade (domingo a sábado).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    protected function calendarRange(): array
    {
        $first = CarbonImmutable::parse($this->month)->startOfMonth();

        return [$first, $first->startOfWeek(CarbonImmutable::SUNDAY), $first->endOfMonth()->endOfWeek(CarbonImmutable::SATURDAY)];
    }
}

<?php

use App\Models\Availability;
use App\Models\Dentist;
use App\Models\Procedure;
use App\Models\ScheduleBlock;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-10-01 09:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('prepares an idempotent partner demo with an unavailable date', function () {
    $this->artisan('demo:prepare-partner-test')->assertSuccessful();
    $this->artisan('demo:prepare-partner-test')->assertSuccessful();

    expect(Dentist::query()->whereIn('cro', ['CRO-RR 0000', 'CRO-RR 0001'])->count())->toBe(2)
        ->and(Procedure::query()->whereIn('name', [
            'Avaliação odontológica',
            'Limpeza',
            'Restauração simples',
            'Clareamento — avaliação',
        ])->count())->toBe(4)
        ->and(Availability::query()->count())->toBe(20)
        ->and(ScheduleBlock::query()->count())->toBe(1);

    $block = ScheduleBlock::query()->with('dentist')->sole();

    expect($block->starts_at->format('d/m/Y H:i'))->toBe('05/10/2026 08:00')
        ->and($block->ends_at->format('d/m/Y H:i'))->toBe('05/10/2026 18:00')
        ->and($block->dentist->cro)->toBe('CRO-RR 0000');
});

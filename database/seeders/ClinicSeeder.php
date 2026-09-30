<?php

namespace Database\Seeders;

use App\Models\Dentist;
use App\Models\Procedure;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        $dentist = Dentist::query()->firstOrCreate(['cro' => 'CRO-RR 0000'], ['name' => 'Dra. Ana Sorriso', 'specialty' => 'Clínica geral', 'active' => true]);
        $procedures = collect([
            ['name' => 'Avaliação odontológica', 'duration_minutes' => 30, 'price' => 120],
            ['name' => 'Limpeza', 'duration_minutes' => 45, 'price' => 180],
            ['name' => 'Clareamento — avaliação', 'duration_minutes' => 30, 'price' => null],
            ['name' => 'Restauração', 'duration_minutes' => 60, 'price' => null],
        ])->map(fn (array $data) => Procedure::query()->firstOrCreate(['name' => $data['name']], $data + ['active' => true]));
        $dentist->procedures()->syncWithoutDetaching($procedures->pluck('id')->all());
        foreach (range(1, 5) as $weekday) {
            foreach ([['08:00', '12:00'], ['14:00', '18:00']] as [$start, $end]) {
                $dentist->availabilities()->firstOrCreate(['weekday' => $weekday, 'start_time' => $start, 'end_time' => $end], ['slot_interval_minutes' => 30, 'active' => true]);
            }
        }
    }
}

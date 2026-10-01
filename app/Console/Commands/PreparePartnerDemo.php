<?php

namespace App\Console\Commands;

use App\Models\Dentist;
use App\Models\Procedure;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PreparePartnerDemo extends Command
{
    protected $signature = 'demo:prepare-partner-test
        {--force : Permite executar conscientemente em ambiente de produção}';

    protected $description = 'Prepara dados básicos e repetíveis para o teste do painel e do bot';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Comando bloqueado em produção. Use primeiro uma homologação ou execute novamente com --force por sua conta e risco.');

            return self::FAILURE;
        }

        $blockedDate = CarbonImmutable::today(config('app.timezone'))->next(CarbonInterface::MONDAY);

        [$ana, $bruno, $procedures] = DB::transaction(function () use ($blockedDate) {
            $procedures = collect([
                ['name' => 'Avaliação odontológica', 'duration_minutes' => 30, 'price' => 120, 'description' => 'Primeira avaliação e planejamento do atendimento.'],
                ['name' => 'Limpeza', 'duration_minutes' => 45, 'price' => 180, 'description' => 'Profilaxia e orientação de higiene bucal.'],
                ['name' => 'Restauração simples', 'duration_minutes' => 60, 'price' => 250, 'description' => 'Procedimento restaurador para o roteiro de testes.'],
                ['name' => 'Clareamento — avaliação', 'duration_minutes' => 30, 'price' => null, 'description' => 'Avaliação inicial para clareamento.'],
            ])->map(fn (array $data) => Procedure::query()->updateOrCreate(
                ['name' => $data['name']],
                $data + ['active' => true],
            ));

            $ana = Dentist::query()->updateOrCreate(
                ['cro' => 'CRO-RR 0000'],
                ['name' => 'Dra. Ana Sorriso', 'specialty' => 'Clínica geral', 'active' => true],
            );

            $bruno = Dentist::query()->updateOrCreate(
                ['cro' => 'CRO-RR 0001'],
                ['name' => 'Dr. Bruno Vida', 'specialty' => 'Dentística', 'active' => true],
            );

            $ana->procedures()->sync($procedures->pluck('id')->all());
            $bruno->procedures()->sync($procedures->whereIn('name', [
                'Avaliação odontológica',
                'Limpeza',
                'Restauração simples',
            ])->pluck('id')->all());

            foreach ([$ana, $bruno] as $dentist) {
                foreach (range(1, 5) as $weekday) {
                    foreach ([['08:00', '12:00'], ['14:00', '18:00']] as [$start, $end]) {
                        $dentist->availabilities()->updateOrCreate(
                            ['weekday' => $weekday, 'start_time' => $start, 'end_time' => $end],
                            ['slot_interval_minutes' => 30, 'active' => true],
                        );
                    }
                }
            }

            $ana->blocks()->where('reason', '[DEMO] Dia indisponível para teste')->delete();
            $ana->blocks()->create([
                'starts_at' => $blockedDate->setTime(8, 0),
                'ends_at' => $blockedDate->setTime(18, 0),
                'reason' => '[DEMO] Dia indisponível para teste',
            ]);

            return [$ana, $bruno, $procedures];
        });

        $this->newLine();
        $this->info('Ambiente de demonstração preparado com sucesso.');
        $this->table(
            ['Item', 'Resultado'],
            [
                ['Dentistas', "{$ana->name} e {$bruno->name}"],
                ['Procedimentos', $procedures->pluck('name')->join(', ')],
                ['Expediente', 'Segunda a sexta, 08:00–12:00 e 14:00–18:00'],
                ['Data indisponível', $blockedDate->format('d/m/Y').' para '.$ana->name],
            ],
        );
        $this->line('Teste sugerido: um sócio escolhe a Dra. Ana na data bloqueada; o outro escolhe o Dr. Bruno na mesma data.');

        return self::SUCCESS;
    }
}

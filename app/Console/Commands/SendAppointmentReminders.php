<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Appointment;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:send-reminders';

    protected $description = 'Envia lembretes de consultas pelo WhatsApp';

    public function handle(): int
    {
        $this->sendDue('reminder_24h_sent_at', now()->addHours(2), now()->addHours(24), fn (Appointment $a) => "Olá, {$a->patient->name}! Lembramos que sua consulta é amanhã: {$this->details($a)}. Responda *confirmar* para confirmar ou digite *menu* para alterar.");
        $this->sendDue('reminder_2h_sent_at', now(), now()->addHours(2), fn (Appointment $a) => "Sua consulta está próxima 🦷 {$this->details($a)}. Se precisar alterar, digite *menu*.");

        return self::SUCCESS;
    }

    protected function sendDue(string $column, $from, $until, callable $message): void
    {
        Appointment::query()->with(['patient', 'dentist', 'procedure'])
            ->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Confirmed->value])
            ->whereNull($column)->whereBetween('starts_at', [$from, $until])
            ->chunkById(100, function ($items) use ($column, $message) {
                foreach ($items as $item) {
                    SendWhatsAppMessage::dispatch($item->patient->phone, $message($item));
                    $item->update([$column => now()]);
                }
            });
    }

    protected function details(Appointment $a): string
    {
        return $a->starts_at->format('d/m/Y \à\s H:i').", {$a->procedure->name}, com {$a->dentist->name}";
    }
}

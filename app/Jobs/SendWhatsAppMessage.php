<?php

namespace App\Jobs;

use App\Services\Evolution\EvolutionApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> segundos entre tentativas */
    public array $backoff = [5, 15, 60];

    public function __construct(
        public string $phone,
        public string $text,
    ) {}

    public function handle(EvolutionApiClient $client): void
    {
        $client->sendText($this->phone, $this->text);
    }
}

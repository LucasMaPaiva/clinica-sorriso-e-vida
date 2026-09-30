<?php

namespace App\Jobs;

use App\Services\Bot\BotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class ProcessIncomingMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> segundos entre tentativas */
    public array $backoff = [5, 15, 60];

    public function __construct(
        public string $phone,
        public ?string $text,
        public bool $hasNonTextMessage,
        public string $messageId,
    ) {}

    public function handle(BotService $bot): void
    {
        // A Evolution API pode reenviar o mesmo evento; evita responder 2x.
        if (! Cache::add("evolution:processed-message:{$this->messageId}", true, now()->addDay())) {
            return;
        }

        foreach ($bot->handleIncomingMessage($this->phone, $this->text, $this->hasNonTextMessage) as $message) {
            SendWhatsAppMessage::dispatch($this->phone, $message);
        }
    }
}

<?php

use App\Jobs\ProcessIncomingMessage;
use App\Jobs\SendWhatsAppMessage;
use App\Services\Bot\BotService;
use Illuminate\Support\Facades\Queue;

it('does not process duplicate evolution message', function () {
    Queue::fake();
    $j = new ProcessIncomingMessage('5595984000000', 'ajuda', false, 'MSG-1');
    $j->handle(app(BotService::class));
    $j->handle(app(BotService::class));
    Queue::assertPushed(SendWhatsAppMessage::class, 1);
});

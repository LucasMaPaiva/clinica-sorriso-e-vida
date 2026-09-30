<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Recebe os webhooks da Evolution API e responde 200 imediatamente.
 * O processamento de verdade (BotService) roda na fila via
 * ProcessIncomingMessage, para nunca travar o webhook.
 */
class EvolutionWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $event = $request->input('event');

        Log::info('Evolution webhook received', [
            'event' => $event,
            'instance' => $request->input('instance'),
        ]);

        if ($event === 'messages.upsert') {
            $this->handleIncomingMessage($request->input('data', []));
        }

        return response()->json(['ok' => true]);
    }

    protected function handleIncomingMessage(array $data): void
    {
        $remoteJid = data_get($data, 'key.remoteJid', '');
        $fromMe = (bool) data_get($data, 'key.fromMe', false);
        $messageId = data_get($data, 'key.id');

        // Contatos com o LID (privacidade) ativado mandam o remoteJid como
        // "<id>@lid" em vez do número; o número real vem em remoteJidAlt.
        $phoneJid = Str::endsWith($remoteJid, '@lid')
            ? data_get($data, 'key.remoteJidAlt', '')
            : $remoteJid;

        // Só conversas individuais com telefone resolvido e id de mensagem
        // (ignora grupos, status, o próprio bot e LIDs sem remoteJidAlt)
        if (! Str::endsWith($phoneJid, '@s.whatsapp.net') || $fromMe || blank($messageId)) {
            return;
        }

        $message = data_get($data, 'message');

        // Sem objeto de mensagem (reação, exclusão, evento de protocolo...) — nada a processar
        if (blank($message)) {
            return;
        }

        $text = data_get($message, 'conversation') ?? data_get($message, 'extendedTextMessage.text');
        $phone = Str::before($phoneJid, '@');

        if (! $this->isNumberAllowed($phone)) {
            Log::info('Evolution webhook: number outside the homologação allowlist, ignoring', ['phone' => $phone]);

            return;
        }

        ProcessIncomingMessage::dispatch($phone, $text, hasNonTextMessage: blank($text), messageId: (string) $messageId);
    }

    /**
     * Em homologação, restringe o bot a uma lista de números (config
     * EVOLUTION_ALLOWED_NUMBERS). Lista vazia = libera todo mundo (produção).
     */
    protected function isNumberAllowed(string $phone): bool
    {
        $allowed = config('services.evolution.allowed_numbers', []);

        return empty($allowed) || in_array($phone, $allowed, true);
    }
}

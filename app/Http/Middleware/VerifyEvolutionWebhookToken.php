<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEvolutionWebhookToken
{
    /**
     * A Evolution API não assina os webhooks; a autenticação é feita por um
     * token secreto embutido na URL do webhook (?token=...), conferido aqui.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.evolution.webhook_token');

        if (blank($expected) || ! hash_equals($expected, (string) $request->query('token'))) {
            abort(401, 'Invalid webhook token.');
        }

        return $next($request);
    }
}

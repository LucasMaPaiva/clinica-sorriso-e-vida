<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\AuthenticateSession;

/** Sessão invalidada (ex.: senha trocada em outro aparelho) também volta para o /entrar. */
class AuthenticatePanelSession extends AuthenticateSession
{
    protected function redirectTo($request): ?string
    {
        return route('entrar');
    }
}

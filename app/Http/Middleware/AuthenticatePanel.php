<?php

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;

/** O painel não tem login próprio: quem não está logado vai para o /entrar. */
class AuthenticatePanel extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return route('entrar');
    }
}

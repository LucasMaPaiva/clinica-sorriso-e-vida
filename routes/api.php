<?php

use App\Http\Controllers\Webhooks\EvolutionWebhookController;
use App\Http\Middleware\VerifyEvolutionWebhookToken;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/evolution', EvolutionWebhookController::class)
    ->middleware(VerifyEvolutionWebhookToken::class)
    ->name('webhooks.evolution');

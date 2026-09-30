<?php

namespace App\Services\Evolution;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Único ponto de contato HTTP com a Evolution API.
 * Se um dia migrarmos para a API oficial da Meta, troca-se esta classe.
 */
class EvolutionApiClient
{
    public function sendText(string $number, string $text): array
    {
        return $this->request()
            ->post('/message/sendText/'.$this->instance(), [
                'number' => $number,
                'text' => $text,
            ])
            ->throw()
            ->json();
    }

    public function connectionState(): array
    {
        return $this->request()
            ->get('/instance/connectionState/'.$this->instance())
            ->throw()
            ->json();
    }

    protected function request(): PendingRequest
    {
        return Http::baseUrl(config('services.evolution.url'))
            ->withHeaders(['apikey' => config('services.evolution.key')])
            ->acceptJson()
            ->timeout(15);
    }

    protected function instance(): string
    {
        return config('services.evolution.instance');
    }
}

<?php

namespace App\Filament\Resources\Dentists\Pages;

use App\Filament\Resources\Dentists\DentistResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDentists extends ListRecords
{
    protected static string $resource = DentistResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}

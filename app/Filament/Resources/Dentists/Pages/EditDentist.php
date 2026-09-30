<?php

namespace App\Filament\Resources\Dentists\Pages;

use App\Filament\Resources\Dentists\DentistResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDentist extends EditRecord
{
    protected static string $resource = DentistResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

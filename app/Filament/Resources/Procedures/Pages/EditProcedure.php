<?php

namespace App\Filament\Resources\Procedures\Pages;

use App\Filament\Resources\Procedures\ProcedureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProcedure extends EditRecord
{
    protected static string $resource = ProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

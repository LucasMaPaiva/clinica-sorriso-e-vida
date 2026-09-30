<?php

namespace App\Filament\Resources\ScheduleBlocks\Pages;

use App\Filament\Resources\ScheduleBlocks\ScheduleBlockResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScheduleBlock extends EditRecord
{
    protected static string $resource = ScheduleBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}

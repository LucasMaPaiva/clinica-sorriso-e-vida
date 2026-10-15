<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAppointments extends ListRecords
{
    protected static string $resource = AppointmentResource::class;

    protected ?string $heading = 'Agenda em lista';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calendario')->label('Ver calendário')->icon('heroicon-m-calendar-days')->color('gray')
                ->url(AppointmentResource::getUrl('index')),
            CreateAction::make()->label('Nova consulta'),
        ];
    }
}

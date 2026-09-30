<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Services\AppointmentService;
use DomainException;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditAppointment extends EditRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(AppointmentService::class)->updateFromAdmin($record, $data);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['data.starts_at' => $e->getMessage()]);
        }
    }
}

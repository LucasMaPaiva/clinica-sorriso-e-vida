<?php

namespace App\Filament\Resources\Appointments\Pages;

use App\Filament\Resources\Appointments\AppointmentResource;
use App\Models\Dentist;
use App\Models\Patient;
use App\Models\Procedure;
use App\Services\AppointmentService;
use DomainException;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $a = app(AppointmentService::class)->create(Patient::findOrFail($data['patient_id']), Dentist::findOrFail($data['dentist_id']), Procedure::findOrFail($data['procedure_id']), $data['starts_at'], 'admin', $data['notes'] ?? null);
            $a->update(['status' => $data['status']]);

            return $a;
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['data.starts_at' => $e->getMessage()]);
        }
    }
}

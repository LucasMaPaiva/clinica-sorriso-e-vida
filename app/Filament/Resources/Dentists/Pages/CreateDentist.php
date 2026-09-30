<?php

namespace App\Filament\Resources\Dentists\Pages;

use App\Filament\Resources\Dentists\DentistResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDentist extends CreateRecord
{
    protected static string $resource = DentistResource::class;
}

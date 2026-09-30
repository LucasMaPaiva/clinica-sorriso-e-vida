<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AppointmentStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InAttendance = 'in_attendance';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Agendada', self::Confirmed => 'Confirmada',
            self::InAttendance => 'Em atendimento', self::Completed => 'Concluída',
            self::Cancelled => 'Cancelada', self::NoShow => 'Paciente faltou',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'gray', self::Confirmed => 'success', self::InAttendance => 'warning',
            self::Completed => 'info', self::Cancelled, self::NoShow => 'danger',
        };
    }
}

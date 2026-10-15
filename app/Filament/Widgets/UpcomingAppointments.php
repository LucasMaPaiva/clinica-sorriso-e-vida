<?php

namespace App\Filament\Widgets;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingAppointments extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Próximas consultas')
            ->description('A partir de agora, sem as canceladas')
            ->query(fn () => Appointment::query()
                ->with(['patient', 'dentist', 'procedure'])
                ->where('starts_at', '>=', now()->startOfHour())
                ->whereIn('status', [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed, AppointmentStatus::InAttendance]))
            ->defaultSort('starts_at')
            ->paginated([5])
            ->columns([
                TextColumn::make('starts_at')->label('Horário')
                    ->formatStateUsing(fn ($state) => $state->isToday() ? 'Hoje, '.$state->format('H:i') : ($state->isTomorrow() ? 'Amanhã, '.$state->format('H:i') : $state->format('d/m H:i')))
                    ->weight('semibold'),
                TextColumn::make('patient.name')->label('Paciente')->description(fn (Appointment $r) => $r->patient->phone),
                TextColumn::make('dentist.name')->label('Dentista'),
                TextColumn::make('procedure.name')->label('Procedimento'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('source')->label('Origem')->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'whatsapp' ? 'WhatsApp' : 'Painel')
                    ->color(fn (string $state) => $state === 'whatsapp' ? 'success' : 'gray'),
            ])
            ->emptyStateHeading('Nenhuma consulta pela frente')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}

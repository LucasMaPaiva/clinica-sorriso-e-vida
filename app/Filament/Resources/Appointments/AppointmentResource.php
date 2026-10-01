<?php

namespace App\Filament\Resources\Appointments;

use App\Enums\AppointmentStatus;
use App\Filament\Resources\Appointments\Pages\CreateAppointment;
use App\Filament\Resources\Appointments\Pages\EditAppointment;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Models\Appointment;
use App\Services\AppointmentService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Agenda';

    protected static ?string $modelLabel = 'consulta';

    protected static ?string $pluralModelLabel = 'agenda';

    protected static ?int $navigationSort = -10;

    public static function form(Schema $s): Schema
    {
        return $s->components([Select::make('patient_id')->label('Paciente')->relationship('patient', 'name')->searchable(['name', 'phone'])->preload()->required(), Select::make('dentist_id')->label('Dentista')->relationship('dentist', 'name', fn ($query) => $query->where('active', true))->searchable()->preload()->required(), Select::make('procedure_id')->label('Procedimento')->relationship('procedure', 'name', fn ($query) => $query->where('active', true))->searchable()->preload()->required(), DateTimePicker::make('starts_at')->label('Data e horário')->seconds(false)->required(), Select::make('status')->label('Status')->options(AppointmentStatus::class)->default(AppointmentStatus::Scheduled->value)->required()->native(false), Textarea::make('notes')->label('Observações')->columnSpanFull()]);
    }

    public static function table(Table $t): Table
    {
        return $t->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['patient', 'dentist', 'procedure']))->columns([TextColumn::make('starts_at')->label('Horário')->dateTime('d/m/Y H:i')->sortable(), TextColumn::make('patient.name')->label('Paciente')->description(fn (Appointment $r) => $r->patient->phone)->searchable()->sortable(), TextColumn::make('dentist.name')->label('Dentista')->searchable(), TextColumn::make('procedure.name')->label('Procedimento'), TextColumn::make('status')->label('Status')->badge(), TextColumn::make('source')->label('Origem')->formatStateUsing(fn (string $s) => $s === 'whatsapp' ? 'WhatsApp' : 'Painel')->badge()])->defaultSort('starts_at')->filters([Filter::make('today')->label('Hoje')->query(fn (Builder $query): Builder => $query->whereDate('starts_at', today()))->default(), SelectFilter::make('dentist')->relationship('dentist', 'name'), SelectFilter::make('status')->options(AppointmentStatus::class)])->recordActions([
            EditAction::make(),
            Action::make('confirm')->label('Confirmar')->color('success')->visible(fn (Appointment $r) => $r->status === AppointmentStatus::Scheduled)->action(function (Appointment $r) {
                app(AppointmentService::class)->confirm($r);
                Notification::make()->title('Consulta confirmada')->success()->send();
            }),
            Action::make('start')->label('Iniciar')->color('warning')->visible(fn (Appointment $r) => in_array($r->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true))->action(fn (Appointment $r) => $r->update(['status' => AppointmentStatus::InAttendance])),
            Action::make('complete')->label('Concluir')->color('info')->visible(fn (Appointment $r) => $r->status === AppointmentStatus::InAttendance)->action(fn (Appointment $r) => $r->update(['status' => AppointmentStatus::Completed])),
            Action::make('cancel')->label('Cancelar')->color('danger')->requiresConfirmation()->visible(fn (Appointment $r) => in_array($r->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true))->action(fn (Appointment $r) => app(AppointmentService::class)->cancel($r, 'Cancelado pela recepção')),
            Action::make('no_show')->label('Marcar falta')->color('danger')->requiresConfirmation()->visible(fn (Appointment $r) => in_array($r->status, [AppointmentStatus::Scheduled, AppointmentStatus::Confirmed], true))->action(fn (Appointment $r) => $r->update(['status' => AppointmentStatus::NoShow])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAppointments::route('/'), 'create' => CreateAppointment::route('/create'), 'edit' => EditAppointment::route('/{record}/edit')];
    }
}

<?php

namespace App\Filament\Resources\Patients;

use App\Filament\Resources\Patients\Pages\CreatePatient;
use App\Filament\Resources\Patients\Pages\EditPatient;
use App\Filament\Resources\Patients\Pages\ListPatients;
use App\Models\Patient;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PatientResource extends Resource
{
    protected static ?string $model = Patient::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Pacientes';

    protected static ?string $modelLabel = 'paciente';

    protected static ?string $pluralModelLabel = 'pacientes';

    public static function form(Schema $s): Schema
    {
        return $s->components([TextInput::make('name')->label('Nome completo')->required(), TextInput::make('phone')->label('WhatsApp')->tel()->required()->unique(ignoreRecord: true), TextInput::make('email')->label('E-mail')->email(), DatePicker::make('birth_date')->label('Nascimento')->displayFormat('d/m/Y'), Textarea::make('notes')->label('Observações')->columnSpanFull(), Toggle::make('active')->label('Ativo')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('name')->label('Paciente')->searchable()->sortable(), TextColumn::make('phone')->label('WhatsApp')->searchable(), TextColumn::make('appointments_count')->label('Consultas')->counts('appointments')->sortable(), TextColumn::make('birth_date')->label('Nascimento')->date('d/m/Y')->placeholder('—'), IconColumn::make('active')->label('Ativo')->boolean()])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPatients::route('/'), 'create' => CreatePatient::route('/create'), 'edit' => EditPatient::route('/{record}/edit')];
    }
}

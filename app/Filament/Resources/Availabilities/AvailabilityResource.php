<?php

namespace App\Filament\Resources\Availabilities;

use App\Filament\Resources\Availabilities\Pages\CreateAvailability;
use App\Filament\Resources\Availabilities\Pages\EditAvailability;
use App\Filament\Resources\Availabilities\Pages\ListAvailabilities;
use App\Models\Availability;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AvailabilityResource extends Resource
{
    protected static ?string $model = Availability::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Horários de trabalho';

    protected static ?string $modelLabel = 'horário de trabalho';

    protected static ?string $pluralModelLabel = 'horários de trabalho';

    public static function weekdays(): array
    {
        return [1 => 'Segunda-feira', 2 => 'Terça-feira', 3 => 'Quarta-feira', 4 => 'Quinta-feira', 5 => 'Sexta-feira', 6 => 'Sábado', 7 => 'Domingo'];
    }

    public static function form(Schema $s): Schema
    {
        return $s->components([Select::make('dentist_id')->label('Dentista')->relationship('dentist', 'name')->searchable()->preload()->required(), Select::make('weekday')->label('Dia')->options(self::weekdays())->required(), TimePicker::make('start_time')->label('Início')->seconds(false)->required(), TimePicker::make('end_time')->label('Fim')->seconds(false)->required()->after('start_time'), TextInput::make('slot_interval_minutes')->label('Intervalo')->suffix('min')->numeric()->integer()->minValue(5)->default(30)->required(), Toggle::make('active')->label('Ativo')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('dentist.name')->label('Dentista')->searchable()->sortable(), TextColumn::make('weekday')->label('Dia')->formatStateUsing(fn ($state) => self::weekdays()[(int) $state] ?? $state)->sortable(), TextColumn::make('start_time')->label('Início')->time('H:i'), TextColumn::make('end_time')->label('Fim')->time('H:i'), TextColumn::make('slot_interval_minutes')->label('Intervalo')->suffix(' min'), IconColumn::make('active')->label('Ativo')->boolean()])->defaultSort('weekday')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAvailabilities::route('/'), 'create' => CreateAvailability::route('/create'), 'edit' => EditAvailability::route('/{record}/edit')];
    }
}

<?php

namespace App\Filament\Resources\Dentists;

use App\Filament\Resources\Dentists\Pages\CreateDentist;
use App\Filament\Resources\Dentists\Pages\EditDentist;
use App\Filament\Resources\Dentists\Pages\ListDentists;
use App\Models\Dentist;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DentistResource extends Resource
{
    protected static ?string $model = Dentist::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Dentistas';

    protected static ?string $modelLabel = 'dentista';

    protected static ?string $pluralModelLabel = 'dentistas';

    public static function form(Schema $s): Schema
    {
        return $s->components([TextInput::make('name')->label('Nome')->required(), TextInput::make('cro')->label('CRO')->required()->unique(ignoreRecord: true), TextInput::make('specialty')->label('Especialidade'), TextInput::make('phone')->label('Telefone')->tel(), TextInput::make('email')->label('E-mail')->email(), Select::make('procedures')->label('Procedimentos')->relationship('procedures', 'name')->multiple()->preload()->searchable()->columnSpanFull(), Toggle::make('active')->label('Ativo')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('name')->label('Dentista')->searchable()->sortable(), TextColumn::make('cro')->label('CRO')->searchable(), TextColumn::make('specialty')->label('Especialidade')->placeholder('—'), TextColumn::make('procedures.name')->label('Procedimentos')->badge()->limitList(3), IconColumn::make('active')->label('Ativo')->boolean()])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListDentists::route('/'), 'create' => CreateDentist::route('/create'), 'edit' => EditDentist::route('/{record}/edit')];
    }
}

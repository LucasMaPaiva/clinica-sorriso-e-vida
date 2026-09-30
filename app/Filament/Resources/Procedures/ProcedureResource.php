<?php

namespace App\Filament\Resources\Procedures;

use App\Filament\Resources\Procedures\Pages\CreateProcedure;
use App\Filament\Resources\Procedures\Pages\EditProcedure;
use App\Filament\Resources\Procedures\Pages\ListProcedures;
use App\Models\Procedure;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProcedureResource extends Resource
{
    protected static ?string $model = Procedure::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Procedimentos';

    protected static ?string $modelLabel = 'procedimento';

    protected static ?string $pluralModelLabel = 'procedimentos';

    public static function form(Schema $s): Schema
    {
        return $s->components([TextInput::make('name')->label('Nome')->required(), TextInput::make('duration_minutes')->label('Duração')->suffix('min')->numeric()->integer()->minValue(5)->required()->default(30), TextInput::make('price')->label('Valor')->prefix('R$')->numeric()->step('0.01'), Textarea::make('description')->label('Descrição')->columnSpanFull(), Select::make('dentists')->label('Dentistas')->relationship('dentists', 'name')->multiple()->preload()->searchable()->columnSpanFull(), Toggle::make('active')->label('Ativo')->default(true)]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('name')->label('Procedimento')->searchable()->sortable(), TextColumn::make('duration_minutes')->label('Duração')->suffix(' min')->sortable(), TextColumn::make('price')->label('Valor')->money('BRL')->placeholder('Sob consulta'), TextColumn::make('dentists.name')->label('Dentistas')->badge()->limitList(3), IconColumn::make('active')->label('Ativo')->boolean()])->defaultSort('name')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListProcedures::route('/'), 'create' => CreateProcedure::route('/create'), 'edit' => EditProcedure::route('/{record}/edit')];
    }
}

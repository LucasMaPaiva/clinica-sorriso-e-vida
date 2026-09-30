<?php

namespace App\Filament\Resources\ScheduleBlocks;

use App\Filament\Resources\ScheduleBlocks\Pages\CreateScheduleBlock;
use App\Filament\Resources\ScheduleBlocks\Pages\EditScheduleBlock;
use App\Filament\Resources\ScheduleBlocks\Pages\ListScheduleBlocks;
use App\Models\ScheduleBlock;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ScheduleBlockResource extends Resource
{
    protected static ?string $model = ScheduleBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'Bloqueios da agenda';

    protected static ?string $modelLabel = 'bloqueio';

    protected static ?string $pluralModelLabel = 'bloqueios';

    public static function form(Schema $s): Schema
    {
        return $s->components([Select::make('dentist_id')->label('Dentista')->relationship('dentist', 'name')->searchable()->preload()->required(), DateTimePicker::make('starts_at')->label('Início')->seconds(false)->required(), DateTimePicker::make('ends_at')->label('Fim')->seconds(false)->required()->after('starts_at'), TextInput::make('reason')->label('Motivo')->columnSpanFull()]);
    }

    public static function table(Table $t): Table
    {
        return $t->columns([TextColumn::make('dentist.name')->label('Dentista')->searchable(), TextColumn::make('starts_at')->label('Início')->dateTime('d/m/Y H:i')->sortable(), TextColumn::make('ends_at')->label('Fim')->dateTime('d/m/Y H:i'), TextColumn::make('reason')->label('Motivo')->placeholder('—')])->defaultSort('starts_at', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListScheduleBlocks::route('/'), 'create' => CreateScheduleBlock::route('/create'), 'edit' => EditScheduleBlock::route('/{record}/edit')];
    }
}

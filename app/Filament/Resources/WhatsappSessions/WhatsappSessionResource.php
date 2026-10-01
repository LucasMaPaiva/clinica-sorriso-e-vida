<?php

namespace App\Filament\Resources\WhatsappSessions;

use App\Enums\SessionState;
use App\Filament\Resources\WhatsappSessions\Pages\ListWhatsappSessions;
use App\Models\WhatsappSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappSessionResource extends Resource
{
    protected static ?string $model = WhatsappSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Atendimentos';

    protected static ?string $modelLabel = 'atendimento';

    protected static ?string $pluralModelLabel = 'atendimentos';

    protected static ?int $navigationSort = -20;

    public static function getNavigationBadge(): ?string
    {
        $count = WhatsappSession::query()->where('state', SessionState::HumanHandoff->value)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('patient.name')
                    ->label('Paciente')
                    ->placeholder('Nome ainda não informado')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->searchable(),
                TextColumn::make('state')
                    ->label('Situação')
                    ->badge()
                    ->formatStateUsing(fn (SessionState $state): string => self::stateLabel($state))
                    ->color(fn (SessionState $state): string => $state === SessionState::HumanHandoff ? 'danger' : 'gray'),
                TextColumn::make('context.handoff_reason')
                    ->label('Motivo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'possible_urgency' => 'Possível urgência',
                        'manual' => 'Pediu recepção',
                        default => '—',
                    })
                    ->color(fn (?string $state): string => $state === 'possible_urgency' ? 'danger' : 'warning'),
                TextColumn::make('context.last_patient_message')
                    ->label('Última mensagem')
                    ->limit(55)
                    ->placeholder('—')
                    ->tooltip(fn (WhatsappSession $record): ?string => data_get($record->context, 'last_patient_message')),
                TextColumn::make('last_interaction_at')
                    ->label('Última interação')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('last_interaction_at', 'desc')
            ->filters([
                SelectFilter::make('state')
                    ->label('Situação')
                    ->options([
                        SessionState::HumanHandoff->value => 'Aguardando recepção',
                        SessionState::AwaitingAction->value => 'Menu automático',
                    ]),
            ])
            ->recordActions([
                Action::make('openWhatsapp')
                    ->label('Abrir WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->url(fn (WhatsappSession $record): string => 'https://wa.me/'.$record->phone)
                    ->openUrlInNewTab(),
                Action::make('resumeBot')
                    ->label('Devolver ao bot')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->visible(fn (WhatsappSession $record): bool => $record->state === SessionState::HumanHandoff)
                    ->action(fn (WhatsappSession $record) => $record->update([
                        'state' => SessionState::Idle,
                        'context' => [],
                        'last_interaction_at' => now(),
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListWhatsappSessions::route('/')];
    }

    private static function stateLabel(SessionState $state): string
    {
        return match ($state) {
            SessionState::HumanHandoff => 'Aguardando recepção',
            SessionState::Idle, SessionState::AwaitingAction => 'Menu automático',
            SessionState::AwaitingName,
            SessionState::AwaitingProcedure,
            SessionState::AwaitingDentist,
            SessionState::AwaitingDate,
            SessionState::AwaitingTime,
            SessionState::AwaitingConfirmation => 'Agendando consulta',
            SessionState::AwaitingAppointmentToCancel,
            SessionState::AwaitingCancellationConfirmation => 'Cancelando consulta',
            SessionState::AwaitingAppointmentToReschedule => 'Reagendando consulta',
            SessionState::AwaitingAnythingElse => 'Finalizando atendimento',
        };
    }
}

<x-filament-panels::page>
    <div class="cs-cal">
        {{-- Calendário --}}
        <section class="cs-cal-card">
            <header class="cs-cal-head">
                <div class="cs-cal-title">
                    <button type="button" wire:click="previousMonth" class="cs-cal-nav" aria-label="Mês anterior">
                        <x-filament::icon icon="heroicon-m-chevron-left" class="h-5 w-5" />
                    </button>
                    <h2>{{ $this->monthLabel() }}</h2>
                    <button type="button" wire:click="nextMonth" class="cs-cal-nav" aria-label="Próximo mês">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" />
                    </button>
                </div>

                <div class="cs-cal-tools">
                    <select wire:model.live="dentistId" class="cs-cal-select">
                        <option value="">Todos os dentistas</option>
                        @foreach ($this->dentists() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-filament::button color="gray" size="sm" wire:click="goToToday">Hoje</x-filament::button>
                </div>
            </header>

            <div class="cs-cal-grid cs-cal-weekdays">
                @foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
                    <div>{{ $weekday }}</div>
                @endforeach
            </div>

            <div class="cs-cal-grid cs-cal-days">
                @foreach ($this->weeks() as $week)
                    @foreach ($week as $d)
                        <button
                            type="button"
                            wire:key="day-{{ $d['date'] }}"
                            wire:click="selectDay('{{ $d['date'] }}')"
                            @class([
                                'cs-day',
                                'is-out' => ! $d['inMonth'],
                                'is-today' => $d['isToday'],
                                'is-selected' => $d['date'] === $selected,
                            ])
                        >
                            <span class="cs-day-num">{{ $d['day'] }}</span>
                            @if ($d['count'] > 0)
                                <span class="cs-day-count">
                                    {{ $d['count'] }} {{ $d['count'] === 1 ? 'consulta' : 'consultas' }}
                                </span>
                            @endif
                        </button>
                    @endforeach
                @endforeach
            </div>
        </section>

        {{-- Agendamentos do dia --}}
        <aside class="cs-cal-card cs-cal-side">
            <header class="cs-side-head">
                <div>
                    <p class="cs-side-kicker">Agendamentos</p>
                    <h3>{{ $this->selectedLabel() }}</h3>
                </div>
            </header>

            @php($appointments = $this->dayAppointments())

            @forelse ($appointments as $appointment)
                <article class="cs-appt" wire:key="appt-{{ $appointment->id }}">
                    <div class="cs-appt-time">{{ $appointment->starts_at->format('H:i') }}</div>
                    <div class="cs-appt-body">
                        <strong>{{ $appointment->patient?->name }}</strong>
                        <span>
                            {{ $appointment->procedure?->name }}
                            @if ($appointment->dentist)
                                · {{ $appointment->dentist->name }}
                            @endif
                        </span>
                    </div>
                    <x-filament::badge :color="$appointment->status->getColor()" size="sm">
                        {{ $appointment->status->getLabel() }}
                    </x-filament::badge>

                    @php($open = in_array($appointment->status, [\App\Enums\AppointmentStatus::Scheduled, \App\Enums\AppointmentStatus::Confirmed], true))
                    <div class="cs-appt-actions">
                        @if ($appointment->status === \App\Enums\AppointmentStatus::Scheduled)
                            <x-filament::button size="xs" color="success" wire:click="confirm({{ $appointment->id }})">Confirmar</x-filament::button>
                        @endif
                        @if ($open)
                            <x-filament::button size="xs" color="warning" wire:click="start({{ $appointment->id }})">Iniciar</x-filament::button>
                        @endif
                        @if ($appointment->status === \App\Enums\AppointmentStatus::InAttendance)
                            <x-filament::button size="xs" color="info" wire:click="complete({{ $appointment->id }})">Concluir</x-filament::button>
                        @endif
                        <x-filament::button size="xs" color="gray" tag="a" :href="$this->editUrl($appointment)">Editar</x-filament::button>
                        @if ($open)
                            <x-filament::button size="xs" color="danger" outlined wire:click="noShow({{ $appointment->id }})" wire:confirm="Marcar falta do paciente?">Faltou</x-filament::button>
                            <x-filament::button size="xs" color="danger" outlined wire:click="cancel({{ $appointment->id }})" wire:confirm="Cancelar esta consulta?">Cancelar</x-filament::button>
                        @endif
                    </div>
                </article>
            @empty
                <div class="cs-empty">
                    <x-filament::icon icon="heroicon-o-calendar" class="h-10 w-10" />
                    <p>Nenhuma consulta neste dia.</p>
                </div>
            @endforelse
        </aside>
    </div>
</x-filament-panels::page>

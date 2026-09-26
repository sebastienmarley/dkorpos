<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-start justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Rendez-vous') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ ucfirst($startDate->translatedFormat('d F')) }} – {{ $endDate->translatedFormat('d F Y') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-3">
            {{-- Nouveau client --}}
            <flux:button
                size="sm"
                variant="ghost"
                icon="user-plus"
                x-on:click="$dispatch('open-customer-create')"
            >
                {{ __('Nouveau client') }}
            </flux:button>

            {{-- Sélecteur d'employé --}}
            <flux:select wire:model.live="selectedUserId" class="w-48">
                @foreach ($users as $user)
                    <flux:select.option value="{{ $user->id }}">{{ $user->fullName() }}</flux:select.option>
                @endforeach
            </flux:select>

            {{-- Navigation semaine --}}
            <div class="flex items-center gap-2">
                @if (! $isCurrentWeek)
                    <flux:button size="sm" variant="ghost" wire:click="goToCurrentWeek">
                        {{ __('Semaine actuelle') }}
                    </flux:button>
                @endif

                <flux:button.group>
                    <flux:button
                        size="sm"
                        icon="chevron-left"
                        wire:click="previousWeek"
                        :label="__('Semaine précédente')"
                    />
                    <flux:button
                        size="sm"
                        icon="chevron-right"
                        wire:click="nextWeek"
                        :label="__('Semaine suivante')"
                    />
                </flux:button.group>
            </div>
        </div>
    </div>

    @if ($isPastWeek)
        <flux:callout icon="information-circle" color="zinc" class="mb-4">
            {{ __('Cette semaine est passée. Les rendez-vous sont en lecture seule.') }}
        </flux:callout>
    @endif

    {{-- Grille --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full table-fixed border-collapse text-sm">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th class="w-20 px-3 py-2 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                        {{ __('Heure') }}
                    </th>
                    @foreach ($days as $day)
                        @php $isToday = $day->isToday(); @endphp
                        <th class="px-2 py-2 text-center text-xs font-semibold @if($isToday) text-blue-600 dark:text-blue-400 @else text-zinc-500 dark:text-zinc-400 @endif">
                            <div>{{ ucfirst($day->translatedFormat('l')) }}</div>
                            <div @class(['mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold' => true, 'bg-blue-600 text-white' => $isToday])>
                                {{ $day->translatedFormat('d') }}
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($hours as $hour)
                    <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                        <td class="px-3 py-1 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                            {{ sprintf('%02d:00', $hour) }}
                        </td>
                        @foreach ($days as $day)
                            @php
                                $dateKey = $day->toDateString();
                                $cellKey = $dateKey.':'.(string) $hour;

                                // Skip cells covered by a multi-hour appointment above.
                                if ($coveredCells->contains($cellKey)) {
                                    continue;
                                }

                                $appointment = $appointments->get($dateKey)?->get($hour);
                                $isPast = $day->startOfDay()->lt(\Carbon\Carbon::now()->startOfDay());

                                $schedule = $schedules->get($dateKey);
                                $worksAtHour = false;
                                if ($schedule && $schedule->start_time && $schedule->end_time) {
                                    $scheduleStartHour = (int) \Illuminate\Support\Str::substr($schedule->start_time, 0, 2);
                                    $scheduleEndHour = (int) \Illuminate\Support\Str::substr($schedule->end_time, 0, 2);
                                    $worksAtHour = $hour >= $scheduleStartHour && $hour < $scheduleEndHour;
                                }

                                $isClickable = ! $isPastWeek && ! $isPast && (! $isCurrentWeek || $worksAtHour);
                                $rowspan = $appointment ? $appointment->duration_hours : 1;
                            @endphp

                            <td
                                @if ($rowspan > 1) rowspan="{{ $rowspan }}" @endif
                                @class([
                                    'relative px-1 py-0.5 transition-colors align-top' => true,
                                    'cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-950/30' => $isClickable && ! $appointment,
                                    'cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-950/30' => $isClickable && $appointment,
                                    'bg-zinc-50/60 dark:bg-zinc-800/40' => ! $isClickable && ! $appointment,
                                    'opacity-40' => $isPast && ! $appointment,
                                ])
                                @if ($isClickable)
                                    wire:click="openCell('{{ $dateKey }}', {{ $hour }})"
                                @endif
                            >
                                <div @class(['min-h-[2rem]' => $rowspan === 1])>
                                    @if ($appointment)
                                        <div @class([
                                            'rounded px-1.5 py-1 text-xs leading-tight h-full' => true,
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200' => $isClickable,
                                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => ! $isClickable,
                                        ])>
                                            <div class="truncate font-medium">{{ $appointment->title }}</div>
                                            @if ($appointment->customer)
                                                <div class="truncate text-[10px] opacity-75">{{ $appointment->customer->firstname }} {{ $appointment->customer->lastname }}</div>
                                            @endif
                                            @if ($appointment->duration_hours > 1)
                                                <div class="mt-0.5 text-[10px] opacity-60">{{ $appointment->duration_hours }}h</div>
                                            @endif
                                        </div>
                                    @elseif ($isClickable)
                                        <div class="flex h-8 items-center justify-center opacity-0 transition-opacity hover:opacity-100">
                                            <flux:icon.plus class="h-3 w-3 text-zinc-400" />
                                        </div>
                                    @endif
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Modal ajout/édition --}}
    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <flux:heading class="mb-1">
            @if ($editingAppointmentId)
                {{ __('Modifier le rendez-vous') }}
            @else
                {{ __('Nouveau rendez-vous') }}
            @endif
        </flux:heading>
        @if ($editingDate)
            <flux:text class="mb-4 text-zinc-500">
                {{ ucfirst(\Illuminate\Support\Carbon::parse($editingDate)->translatedFormat('l d F Y')) }}
                {{ __('à') }} {{ sprintf('%02d:00', $editingHour) }}
            </flux:text>
        @endif

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <flux:field class="col-span-2">
                    <flux:label>{{ __('Titre') }}</flux:label>
                    <flux:input wire:model="title" autofocus placeholder="{{ __('Ex: Consultation, Réunion…') }}" />
                    <flux:error name="title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Durée') }}</flux:label>
                    <flux:select wire:model="durationHours">
                        @for ($h = 1; $h <= $maxDuration; $h++)
                            <flux:select.option value="{{ $h }}">
                                {{ $h }}h00
                            </flux:select.option>
                        @endfor
                    </flux:select>
                    <flux:error name="durationHours" />
                </flux:field>
            </div>

            {{-- Recherche client --}}
            <flux:field>
                <flux:label>{{ __('Client') }}</flux:label>

                @if ($selectedCustomerId)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex items-center gap-2">
                            <flux:icon.user class="h-4 w-4 text-zinc-400" />
                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $customerSearch }}</span>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearCustomer" :label="__('Retirer')" />
                    </div>
                @else
                    <flux:input
                        wire:model.live.debounce.300ms="customerSearch"
                        placeholder="{{ __('Rechercher un client…') }}"
                        icon="magnifying-glass"
                    />

                    @if ($customerResults->isNotEmpty())
                        <div class="mt-1 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-md dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($customerResults as $c)
                                <button
                                    type="button"
                                    wire:click="selectCustomer({{ $c->id }})"
                                    class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                >
                                    <flux:avatar :name="$c->firstname.' '.$c->lastname" size="sm" />
                                    <div>
                                        <div class="font-medium text-zinc-800 dark:text-zinc-100">{{ $c->firstname }} {{ $c->lastname }}</div>
                                        @if ($c->phone || $c->cellphone)
                                            <div class="text-xs text-zinc-400">{{ $c->phone ?: $c->cellphone }}</div>
                                        @endif
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @elseif (strlen($customerSearch) >= 4)
                        <div class="mt-1 flex items-center justify-between rounded-lg border border-dashed border-zinc-200 px-3 py-2 dark:border-zinc-700">
                            <flux:text class="text-sm text-zinc-400">{{ __('Aucun client trouvé.') }}</flux:text>
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="user-plus"
                                x-on:click="$dispatch('open-customer-create')"
                            >
                                {{ __('Créer') }}
                            </flux:button>
                        </div>
                    @endif
                @endif
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" rows="3" placeholder="{{ __('Informations complémentaires…') }}" />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex items-center justify-between pt-2">
                <div>
                    @if ($editingAppointmentId)
                        <flux:button
                            type="button"
                            variant="danger"
                            icon="trash"
                            wire:click="deleteAppointment"
                            wire:confirm="{{ __('Supprimer ce rendez-vous ?') }}"
                        >
                            {{ __('Supprimer') }}
                        </flux:button>
                    @endif
                </div>

                <div class="flex gap-3">
                    <flux:button type="button" variant="ghost" wire:click="closeModal">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Enregistrer') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <livewire:customer-form />
</div>

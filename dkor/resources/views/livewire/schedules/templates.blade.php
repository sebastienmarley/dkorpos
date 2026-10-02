<div class="space-y-10 p-6">
    <div>
        <flux:heading level="1" size="xl">{{ __('Modèles d\'horaire') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">
            {{ __('Préparez des quarts type et des semaines type pour remplir les horaires plus vite.') }}
        </flux:text>
    </div>

    {{-- Quarts type --}}
    <section>
        <div class="mb-3 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Quarts type') }}</flux:heading>
            @can('schedule_templates.create')
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateShift">
                    {{ __('Nouveau quart type') }}
                </flux:button>
            @endcan
        </div>

        @if ($shiftTemplates->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-400 dark:border-zinc-700">
                {{ __('Aucun quart type pour le moment.') }}
            </div>
        @else
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($shiftTemplates as $shift)
                    <div
                        wire:key="shift-{{ $shift->id }}"
                        class="group relative rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-colors hover:border-blue-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-blue-700"
                    >
                        <button
                            type="button"
                            @can('schedule_templates.edit') wire:click="openEditShift({{ $shift->id }})" @else disabled @endcan
                            class="block w-full text-left"
                            aria-label="{{ __('Modifier :name', ['name' => $shift->name]) }}"
                        >
                            <div class="truncate pr-8 font-medium text-zinc-800 dark:text-zinc-100">{{ $shift->name }}</div>
                            <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ substr($shift->start_time, 0, 5) }} – {{ substr($shift->end_time, 0, 5) }}
                            </div>
                            @if ($shift->break_minutes)
                                <div class="mt-0.5 text-xs text-zinc-400">{{ __('Pause :minutes min', ['minutes' => $shift->break_minutes]) }}</div>
                            @endif
                        </button>

                        @can('schedule_templates.delete')
                            <div class="absolute right-2 top-2 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                                <flux:button
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    wire:click="deleteShift({{ $shift->id }})"
                                    wire:confirm="{{ $shift->entries_count > 0
                                        ? __('Ce quart type est utilisé dans des semaines type : il sera retiré de ces cases. Supprimer ?')
                                        : __('Supprimer ce quart type ?') }}"
                                    :label="__('Supprimer')"
                                />
                            </div>
                        @endcan
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Semaines type --}}
    <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('Semaines type') }}</flux:heading>

            <div class="flex items-center gap-2">
                <flux:select wire:model.live="selectedWeekTemplateId" class="w-56">
                    <flux:select.option value="">{{ __('Choisir une semaine type…') }}</flux:select.option>
                    @foreach ($weekTemplates as $week)
                        <flux:select.option value="{{ $week->id }}">{{ $week->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($selectedWeekTemplateId !== '')
                    @can('schedule_templates.edit')
                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="openRenameWeek" :label="__('Renommer')" />
                    @endcan
                    @can('schedule_templates.delete')
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="trash"
                            wire:click="deleteWeek"
                            wire:confirm="{{ __('Supprimer cette semaine type ?') }}"
                            :label="__('Supprimer')"
                        />
                    @endcan
                @endif

                @can('schedule_templates.create')
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreateWeek">
                        {{ __('Nouvelle semaine type') }}
                    </flux:button>
                @endcan
            </div>
        </div>

        @if ($selectedWeekTemplateId === '')
            <flux:callout icon="information-circle" color="zinc">
                {{ __('Choisissez ou créez une semaine type pour définir le quart de chaque employé, jour par jour.') }}
            </flux:callout>
        @elseif ($shiftTemplates->isEmpty())
            <flux:callout icon="information-circle" color="zinc">
                {{ __('Créez d\'abord au moins un quart type.') }}
            </flux:callout>
        @else
            <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 dark:border-zinc-700">
                            <th class="px-4 py-3 text-left font-medium text-zinc-500">{{ __('Employé') }}</th>
                            @foreach ($weekdayLabels as $label)
                                <th class="px-2 py-3 text-center font-medium text-zinc-500">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($users as $user)
                            <tr wire:key="week-user-{{ $selectedWeekTemplateId }}-{{ $user->id }}">
                                <td class="px-4 py-2 font-medium text-zinc-800 dark:text-zinc-100">{{ $user->fullName() }}</td>
                                @foreach ($weekdayLabels as $weekday => $label)
                                    <td class="px-1 py-2">
                                        <flux:select size="sm" wire:model.live="entries.{{ $user->id }}.{{ $weekday }}" :disabled="! auth()->user()->can('schedule_templates.edit')">
                                            <flux:select.option value="">—</flux:select.option>
                                            @foreach ($shiftTemplates as $shift)
                                                <flux:select.option value="{{ $shift->id }}">{{ $shift->name }}</flux:select.option>
                                            @endforeach
                                        </flux:select>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    {{-- Modal quart type --}}
    <flux:modal wire:model="showShiftModal" class="w-full max-w-sm">
        <flux:heading class="mb-4">
            {{ $editingShiftId ? __('Modifier le quart type') : __('Nouveau quart type') }}
        </flux:heading>

        <form wire:submit="saveShift" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="shiftName" autofocus placeholder="{{ __('Ex: Ouverture') }}" />
                <flux:error name="shiftName" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Début') }}</flux:label>
                    <flux:input wire:model="shiftStart" type="time" />
                    <flux:error name="shiftStart" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Fin') }}</flux:label>
                    <flux:input wire:model="shiftEnd" type="time" />
                    <flux:error name="shiftEnd" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Pause non payée') }}</flux:label>
                <flux:select wire:model="shiftBreak">
                    <flux:select.option value="0">{{ __('Aucune') }}</flux:select.option>
                    <flux:select.option value="30">{{ __('30 minutes') }}</flux:select.option>
                    <flux:select.option value="60">{{ __('60 minutes') }}</flux:select.option>
                </flux:select>
                <flux:error name="shiftBreak" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showShiftModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal semaine type --}}
    <flux:modal wire:model="showWeekModal" class="w-full max-w-sm">
        <flux:heading class="mb-4">
            {{ $editingWeekId ? __('Renommer la semaine type') : __('Nouvelle semaine type') }}
        </flux:heading>

        <form wire:submit="saveWeek" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="weekName" autofocus placeholder="{{ __('Ex: Semaine normale') }}" />
                <flux:error name="weekName" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showWeekModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

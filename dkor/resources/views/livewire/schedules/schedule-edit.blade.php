<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Gestion des horaires') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ ucfirst($startDate->translatedFormat('d F')) }} – {{ $endDate->translatedFormat('d F Y') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            @can('schedule_management.edit')
                <flux:dropdown align="end">
                    <flux:button size="sm" variant="ghost" icon="document-duplicate" icon-trailing="chevron-down">
                        {{ __('Remplir') }}
                    </flux:button>

                    <flux:menu>
                        <flux:menu.item
                            icon="arrow-uturn-right"
                            wire:click="copyPreviousWeek"
                            wire:confirm="{{ __('Copier les quarts de la semaine précédente vers cette semaine ? Les quarts déjà présents ne sont pas modifiés.') }}"
                        >
                            {{ __('Copier la semaine précédente') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        <flux:menu.group :heading="__('Semaines type')">
                            @forelse ($weekTemplates as $weekTemplate)
                                <flux:menu.item
                                    wire:click="applyWeekTemplate({{ $weekTemplate->id }})"
                                    wire:confirm="{{ __('Appliquer cette semaine type ? Les quarts déjà présents ne sont pas modifiés.') }}"
                                >
                                    {{ $weekTemplate->name }}
                                </flux:menu.item>
                            @empty
                                <flux:menu.item disabled>{{ __('Aucune semaine type') }}</flux:menu.item>
                            @endforelse
                        </flux:menu.group>

                        <flux:menu.separator />

                        <flux:menu.item icon="cog-6-tooth" :href="route('schedules.templates')" wire:navigate>
                            {{ __('Gérer les modèles') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            @endcan

            @can('schedule_management.publish')
                <flux:button size="sm" variant="ghost" wire:click="unpublishWeek" wire:confirm="{{ __('Dépublier tous les quarts de cette semaine ?') }}">
                    {{ __('Dépublier') }}
                </flux:button>
                <flux:button size="sm" variant="primary" wire:click="publishWeek" wire:confirm="{{ __('Publier tous les quarts de cette semaine ?') }}">
                    {{ __('Publier') }}
                </flux:button>
            @endcan

            @if (! $isCurrentWeek)
                <flux:button size="sm" variant="ghost" wire:click="goToCurrentWeek">
                    {{ __('Semaine actuelle') }}
                </flux:button>
            @endif

            <flux:button.group>
                <flux:button size="sm" icon="chevron-left" wire:click="previousWeek" :label="__('Semaine précédente')" />
                <flux:button size="sm" icon="chevron-right" wire:click="nextWeek" :label="__('Semaine suivante')" />
            </flux:button.group>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th class="px-4 py-3 text-left font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('Employé') }}
                    </th>
                    @foreach ($days as $day)
                        <th class="px-3 py-3 text-center font-medium @if($day->isToday()) text-blue-600 dark:text-blue-400 @else text-zinc-500 dark:text-zinc-400 @endif">
                            <div>{{ ucfirst($day->translatedFormat('D')) }}</div>
                            <div class="text-xs font-normal">{{ $day->translatedFormat('d M') }}</div>
                            @if ($holiday = $holidays->get($day->toDateString()))
                                <div @class([
                                    'mx-auto mt-0.5 max-w-24 truncate rounded px-1 text-[10px] font-medium',
                                    'bg-violet-100 text-violet-700 dark:bg-violet-900/40 dark:text-violet-300' => $holiday->is_closed,
                                    'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $holiday->is_closed,
                                ]) title="{{ $holiday->is_closed ? __('Magasin fermé') : __('Magasin ouvert') }}">{{ $holiday->name }}</div>
                            @endif
                        </th>
                    @endforeach
                    <th class="px-4 py-3 text-right font-medium text-zinc-500 dark:text-zinc-400">
                        {{ __('Total') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($users as $user)
                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <flux:avatar :name="$user->fullName()" size="sm" />
                                <span class="font-medium text-zinc-800 dark:text-zinc-100">{{ $user->fullName() }}</span>
                            </div>
                        </td>

                        @foreach ($days as $day)
                            @php
                                $dateKey = $day->toDateString();
                                $schedule = $schedules->get($user->id)?->get($dateKey);
                                $isToday = $day->isToday();
                            @endphp
                            <td
                                class="px-2 py-2 text-center"
                            >
                                <button
                                    @can('schedule_management.edit') wire:click="openCell({{ $user->id }}, '{{ $dateKey }}')" @else disabled @endcan
                                    class="w-full min-w-20 rounded-lg border px-2 py-1.5 text-xs transition
                                        @if ($schedule?->type->isAbsence())
                                            {{ match ($schedule->type) {
                                                \App\Enums\ScheduleType::Sick => 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300',
                                                \App\Enums\ScheduleType::Absent => 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
                                                \App\Enums\ScheduleType::Vacation => 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
                                                default => 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-900/30 dark:text-violet-300',
                                            } }}
                                        @elseif ($schedule?->type === \App\Enums\ScheduleType::Training)
                                            border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300
                                        @elseif ($schedule?->start_time)
                                            border-blue-200 bg-blue-50 text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-300 dark:hover:bg-blue-900/50
                                        @elseif ($isToday)
                                            border-zinc-300 bg-zinc-100 text-zinc-500 hover:bg-zinc-200 dark:border-zinc-600 dark:bg-zinc-700/50 dark:text-zinc-400 dark:hover:bg-zinc-700
                                        @else
                                            border-dashed border-zinc-200 bg-transparent text-zinc-400 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-600 dark:hover:bg-zinc-800/50
                                        @endif
                                    "
                                >
                                    @if ($schedule?->type->isAbsence())
                                        <div class="font-medium">{{ $schedule->type->label() }}</div>
                                        @if ($schedule->status === \App\Enums\ScheduleStatus::Published)
                                            <div class="mt-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">● {{ __('Publiée') }}</div>
                                        @elseif ($schedule->status === \App\Enums\ScheduleStatus::Closed)
                                            <div class="mt-0.5 text-[10px] font-medium text-zinc-500">🔒 {{ __('Fermée') }}</div>
                                        @endif
                                    @elseif ($schedule?->start_time)
                                        @if ($schedule->type === \App\Enums\ScheduleType::Training)
                                            <div class="font-medium">{{ $schedule->type->label() }}</div>
                                        @endif
                                        <div>{{ substr($schedule->start_time, 0, 5) }}</div>
                                        @if ($schedule->end_time)
                                            <div class="text-blue-500 dark:text-blue-400">{{ substr($schedule->end_time, 0, 5) }}</div>
                                        @endif
                                        @if ($schedule->status === \App\Enums\ScheduleStatus::Published)
                                            <div class="mt-0.5 text-[10px] font-medium text-emerald-600 dark:text-emerald-400">● {{ __('Publiée') }}</div>
                                        @elseif ($schedule->status === \App\Enums\ScheduleStatus::Closed)
                                            <div class="mt-0.5 text-[10px] font-medium text-zinc-500">🔒 {{ __('Fermée') }}</div>
                                        @endif
                                    @else
                                        <span>+</span>
                                    @endif
                                </button>
                            </td>
                        @endforeach
                        @php
                            $minutes = $totalHours->get($user->id, 0);
                        @endphp
                        <td class="px-4 py-3 text-right">
                            @if ($minutes > 0)
                                <flux:badge size="sm" color="zinc" inset="top bottom">
                                    {{ sprintf('%dh%02d', intdiv($minutes, 60), $minutes % 60) }}
                                </flux:badge>
                            @else
                                <span class="text-zinc-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-12 text-center text-zinc-400">
                            {{ __('Aucun employé actif.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Modal --}}
    <flux:modal wire:model="showModal" class="w-full max-w-sm">
        @if ($editingUserId)
            @php $editingUser = $users->find($editingUserId); @endphp
            <flux:heading class="mb-1">
                {{ $editingUser?->fullName() }}
            </flux:heading>
            <flux:text class="mb-4 text-zinc-500">
                {{ $editingDate ? ucfirst(\Carbon\Carbon::parse($editingDate)->translatedFormat('l d F Y')) : '' }}
            </flux:text>
        @endif

        @if ($editingSchedule)
            <flux:text class="mb-4 text-xs text-zinc-400">
                @if ($editingSchedule->creator)
                    {{ __('Créé par :name', ['name' => $editingSchedule->creator->fullName()]) }}
                    · {{ $editingSchedule->created_at?->translatedFormat('d M Y H:i') }}
                @endif
                @if ($editingSchedule->lastUpdatedBy && $editingSchedule->updated_at?->ne($editingSchedule->created_at))
                    <br>{{ __('Dernière modification par :name', ['name' => $editingSchedule->lastUpdatedBy->fullName()]) }}
                    · {{ $editingSchedule->updated_at?->translatedFormat('d M Y H:i') }}
                @endif
            </flux:text>
        @endif

        <form wire:submit="save" class="space-y-4">
            @error('editingDate')
                <flux:callout variant="danger" icon="exclamation-triangle">
                    <flux:callout.text>{{ $message }}</flux:callout.text>
                </flux:callout>
            @enderror

            <flux:field>
                <flux:label>{{ __('Type') }}</flux:label>
                <flux:select wire:model.live="scheduleType">
                    @foreach ($scheduleTypes as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="scheduleType" />
            </flux:field>

            @if (\App\Enums\ScheduleType::tryFrom($scheduleType)?->hasHours())
                @if ($scheduleType === 'work' && $shiftTemplates->isNotEmpty())
                    <flux:field>
                        <flux:label>{{ __('Quart type') }}</flux:label>
                        <flux:select wire:model.live="shiftTemplateId">
                            <flux:select.option value="">{{ __('— Personnalisé —') }}</flux:select.option>
                            @foreach ($shiftTemplates as $shiftTemplate)
                                <flux:select.option value="{{ $shiftTemplate->id }}">{{ $shiftTemplate->summary() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                @endif

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Début') }}</flux:label>
                        <flux:input wire:model="startTime" type="time" />
                        <flux:error name="startTime" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Fin') }}</flux:label>
                        <flux:input wire:model="endTime" type="time" />
                        <flux:error name="endTime" />
                    </flux:field>
                </div>
            @else
                <flux:field>
                    <flux:label>{{ __('Jusqu\'au (inclus)') }}</flux:label>
                    <flux:input wire:model="untilDate" type="date" :min="$editingDate" />
                    <flux:description>{{ __('Laissez vide pour un seul jour.') }}</flux:description>
                    <flux:error name="untilDate" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Statut') }}</flux:label>
                <flux:select wire:model="status">
                    <flux:select.option value="draft">{{ __('Non publiée') }}</flux:select.option>
                    <flux:select.option value="published">{{ __('Publiée') }}</flux:select.option>
                </flux:select>
                <flux:error name="status" />
            </flux:field>

            @if (\App\Enums\ScheduleType::tryFrom($scheduleType)?->hasHours())
                <flux:field>
                    <flux:label>{{ __('Pause non payée') }}</flux:label>
                    <flux:select wire:model="breakMinutes">
                        <flux:select.option value="0">{{ __('Aucune') }}</flux:select.option>
                        <flux:select.option value="30">{{ __('30 minutes') }}</flux:select.option>
                        <flux:select.option value="60">{{ __('60 minutes') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="breakMinutes" />
                </flux:field>
            @endif

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea wire:model="notes" rows="3" />
                <flux:error name="notes" />
            </flux:field>

            <div class="flex items-center justify-between pt-2">
                <div>
                    @if ($editingScheduleId)
                        <flux:button
                            type="button"
                            variant="danger"
                            icon="trash"
                            wire:click="deleteSchedule"
                            wire:confirm="{{ __('Supprimer ce quart de travail ?') }}"
                        >
                            {{ __('Supprimer') }}
                        </flux:button>
                    @endif
                </div>

                <div class="flex gap-3">
                    <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Sauvegarder') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>
</div>

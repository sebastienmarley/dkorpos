<div class="p-6" wire:poll.30s="checkForChanges">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Gestion des horaires') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ ucfirst($startDate->translatedFormat('d F')) }} – {{ $endDate->translatedFormat('d F Y') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->canChooseStore())
                <flux:select wire:model.live="storeId" size="sm" class="w-56">
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

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

    {{-- Semaine modifiée par quelqu'un d'autre --}}
    @if ($weekChangeNotice)
        <flux:callout icon="arrow-path" color="sky" class="mb-4">
            <flux:callout.heading>{{ $weekChangeNotice }}</flux:callout.heading>
            <flux:callout.text>{{ __('Vérifiez la grille avant de publier, dépublier ou copier la semaine.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" wire:click="refreshWeek">{{ __('Rafraîchir') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    {{-- Rendez-vous à couvrir --}}
    @if ($uncoveredAppointments->isNotEmpty())
        <flux:callout icon="exclamation-triangle" color="amber" class="mb-4">
            <flux:callout.heading>
                {{ trans_choice(':count rendez-vous à couvrir cette semaine|:count rendez-vous à couvrir cette semaine', $uncoveredAppointments->count()) }}
            </flux:callout.heading>
            <flux:callout.text>
                {{ __('Ajoutez ou ajustez le quart de l\'employé, ou réassignez le rendez-vous. Une semaine qui laisse un rendez-vous sans quart ne peut pas être publiée.') }}
            </flux:callout.text>
            <ul class="mt-2 divide-y divide-amber-200/60 text-sm dark:divide-amber-800/60">
                @foreach ($uncoveredAppointments as $row)
                    @php $appointment = $row['appointment']; @endphp
                    <li class="flex flex-wrap items-center justify-between gap-2 py-1.5">
                        <div>
                            <span class="font-medium">{{ $appointment->user?->fullName() }}</span>
                            · {{ ucfirst(\Carbon\Carbon::parse($appointment->date)->translatedFormat('l d F')) }}
                            · {{ \App\Support\AppointmentBooking::formatMinute($appointment->start_minute) }}–{{ \App\Support\AppointmentBooking::formatMinute($appointment->endMinute()) }}
                            · {{ $appointment->title }}
                            @if ($appointment->customer)
                                ({{ $appointment->customer->firstname }} {{ $appointment->customer->lastname }})
                            @endif
                            <flux:badge size="sm" color="amber" inset="top bottom" class="ms-1">{{ $row['coverage']->label() }}</flux:badge>
                        </div>
                        @can('appointments.edit')
                            <flux:button size="xs" variant="ghost" icon="arrows-right-left" wire:click="openReassign({{ $appointment->id }})">
                                {{ __('Réassigner') }}
                            </flux:button>
                        @endcan
                    </li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

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
                                @can('schedule_management.edit')
                                    <flux:dropdown align="start">
                                        <flux:button size="xs" variant="ghost" icon="squares-2x2" :label="__('Appliquer une semaine type')" />

                                        <flux:menu>
                                            <flux:menu.group :heading="__('Appliquer une semaine type')">
                                                @forelse ($weekTemplates as $weekTemplate)
                                                    <flux:menu.item
                                                        wire:click="applyWeekTemplate({{ $user->id }}, {{ $weekTemplate->id }})"
                                                        wire:confirm="{{ __('Appliquer « :week » à :employee pour cette semaine ? Les quarts déjà présents ne sont pas modifiés.', ['week' => $weekTemplate->name, 'employee' => $user->fullName()]) }}"
                                                    >
                                                        {{ $weekTemplate->name }}
                                                        <span class="ms-1 text-xs text-zinc-400">({{ trans_choice(':count jour|:count jours', $weekTemplate->entries_count) }})</span>
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
                            </div>
                        </td>

                        @foreach ($days as $day)
                            @php
                                $dateKey = $day->toDateString();
                                $schedule = $schedules->get($user->id)?->get($dateKey);
                                $isToday = $day->isToday();
                                $cellAppointments = $weekAppointments->get($user->id.'|'.$dateKey, collect());
                                $needsShift = $cellAppointments->contains(fn ($appointment) => $uncoveredCoverage->has($appointment->id));
                            @endphp
                            <td
                                class="px-2 py-2 text-center"
                            >
                                <button
                                    @can('schedule_management.edit') wire:click="openCell({{ $user->id }}, '{{ $dateKey }}')" @else disabled @endcan
                                    class="w-full min-w-20 rounded-lg border px-2 py-1.5 text-xs transition
                                        @if ($needsShift) ring-2 ring-amber-400 dark:ring-amber-500 @endif
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
                                    @elseif ($needsShift)
                                        <div class="font-medium text-amber-700 dark:text-amber-300">{{ __('Quart requis') }}</div>
                                    @else
                                        <span>+</span>
                                    @endif
                                    @foreach ($cellAppointments as $appointment)
                                        <div
                                            @class([
                                                'mt-0.5 truncate text-[10px]',
                                                'font-semibold text-amber-700 dark:text-amber-300' => $uncoveredCoverage->has($appointment->id),
                                                'text-zinc-500 dark:text-zinc-400' => ! $uncoveredCoverage->has($appointment->id),
                                            ])
                                            title="{{ $appointment->title }}"
                                        >
                                            {{ __('Rdv') }} {{ \App\Support\AppointmentBooking::formatMinute($appointment->start_minute) }}–{{ \App\Support\AppointmentBooking::formatMinute($appointment->endMinute()) }}
                                        </div>
                                    @endforeach
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

        @if ($editingAppointments->isNotEmpty())
            <div class="mb-4 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                <flux:text class="mb-1 text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Rendez-vous ce jour-là') }}</flux:text>
                <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                    @foreach ($editingAppointments as $appointment)
                        <li class="flex items-center justify-between gap-2 py-1">
                            <div class="min-w-0">
                                <span @class(['font-medium', 'text-amber-700 dark:text-amber-300' => $uncoveredCoverage->has($appointment->id)])>
                                    {{ \App\Support\AppointmentBooking::formatMinute($appointment->start_minute) }}–{{ \App\Support\AppointmentBooking::formatMinute($appointment->endMinute()) }}
                                </span>
                                <span class="truncate text-zinc-500">· {{ $appointment->title }}</span>
                            </div>
                            @can('appointments.edit')
                                @if ($appointment->date >= now()->toDateString())
                                    <flux:button size="xs" variant="ghost" icon="arrows-right-left" wire:click="openReassign({{ $appointment->id }})">
                                        {{ __('Réassigner') }}
                                    </flux:button>
                                @endif
                            @endcan
                        </li>
                    @endforeach
                </ul>
            </div>
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

    {{-- Réassignation d'un rendez-vous --}}
    <flux:modal wire:model="showReassignModal" class="w-full max-w-md" x-on:close="$wire.closeReassignModal()">
        @if ($reassigningAppointment)
            <flux:heading class="mb-1">{{ __('Réassigner le rendez-vous') }}</flux:heading>
            <flux:text class="mb-4 text-zinc-500">
                {{ $reassigningAppointment->title }}
                @if ($reassigningAppointment->customer)
                    · {{ $reassigningAppointment->customer->firstname }} {{ $reassigningAppointment->customer->lastname }}
                @endif
                <br>
                {{ __('Actuellement : :name, :date, :start–:end', [
                    'name' => $reassigningAppointment->user?->fullName(),
                    'date' => \Carbon\Carbon::parse($reassigningAppointment->date)->translatedFormat('l d F'),
                    'start' => \App\Support\AppointmentBooking::formatMinute($reassigningAppointment->start_minute),
                    'end' => \App\Support\AppointmentBooking::formatMinute($reassigningAppointment->endMinute()),
                ]) }}
            </flux:text>

            <form wire:submit="reassign" class="space-y-4">
                @error('reassignUserId')
                    <flux:callout variant="danger" icon="exclamation-triangle">
                        <flux:callout.text>{{ $message }}</flux:callout.text>
                    </flux:callout>
                @enderror

                <div class="grid grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>{{ __('Date') }}</flux:label>
                        <flux:input wire:model.live="reassignDate" type="date" :min="now()->toDateString()" />
                        <flux:error name="reassignDate" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Heure') }}</flux:label>
                        <flux:select wire:model="reassignStartMinute">
                            @foreach ($reassignTimeSlots as $minute => $label)
                                <flux:select.option value="{{ $minute }}">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="reassignStartMinute" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Employé') }}</flux:label>
                    <flux:select wire:model="reassignUserId">
                        @foreach ($reassignCandidates as $candidate)
                            <flux:select.option value="{{ $candidate['id'] }}">{{ $candidate['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Le quart de chaque employé à la date choisie est indiqué. La durée du rendez-vous ne change pas.') }}</flux:description>
                </flux:field>

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="closeReassignModal">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Réassigner') }}
                    </flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>

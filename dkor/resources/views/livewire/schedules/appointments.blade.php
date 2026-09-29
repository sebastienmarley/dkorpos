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
            <flux:button size="sm" variant="ghost" icon="user-plus" x-on:click="$dispatch('open-customer-create')">
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
                    <flux:button size="sm" variant="ghost" wire:click="goToCurrentWeek">{{ __('Semaine actuelle') }}</flux:button>
                @endif

                <flux:button.group>
                    <flux:button size="sm" icon="chevron-left" wire:click="previousWeek" :label="__('Semaine précédente')" />
                    <flux:button size="sm" icon="chevron-right" wire:click="nextWeek" :label="__('Semaine suivante')" />
                </flux:button.group>
            </div>
        </div>
    </div>

    @if ($isPastWeek)
        <flux:callout icon="information-circle" color="zinc" class="mb-4">
            {{ __('Cette semaine est passée. Les rendez-vous sont en lecture seule.') }}
        </flux:callout>
    @endif

    @assets
    <script>
        window.appointmentGrid = (slotMinutes, dayStartMinute) => ({
            drag: null,
            preview: null,

            start(event, mode) {
                if (event.button !== 0) return;
                const block = event.currentTarget.closest('[data-appt]');
                const rows = this.rows();
                const startRow = Math.round((Number(block.dataset.start) - dayStartMinute) / slotMinutes);
                const pointer = this.locate(event);

                this.drag = {
                    mode,
                    id: Number(block.dataset.appt),
                    version: Number(block.dataset.version),
                    originDate: block.dataset.date,
                    startRow,
                    slots: Math.round(Number(block.dataset.duration) / slotMinutes),
                    grabOffset: pointer.row - startRow,
                    x: event.clientX,
                    y: event.clientY,
                    moved: false,
                    total: rows.length,
                };
                this.drag.target = { date: block.dataset.date, row: startRow, slots: this.drag.slots };

                window.addEventListener('pointermove', this.onMove = (e) => this.move(e));
                window.addEventListener('pointerup', this.onUp = () => this.end());
                event.preventDefault();
            },

            move(event) {
                const d = this.drag;
                if (!d) return;
                if (!d.moved && Math.hypot(event.clientX - d.x, event.clientY - d.y) < 4) return;
                d.moved = true;

                const pointer = this.locate(event);
                if (d.mode === 'move') {
                    const row = Math.min(Math.max(pointer.row - d.grabOffset, 0), d.total - d.slots);
                    d.target = { date: pointer.date, row, slots: d.slots };
                } else {
                    const slots = Math.min(Math.max(pointer.row - d.startRow + 1, 1), d.total - d.startRow);
                    d.target = { date: d.originDate, row: d.startRow, slots };
                }
                this.draw(d.target);
            },

            isValid(target) {
                const grid = JSON.parse(this.$root.dataset.grid);
                const open = new Set(grid.open[target.date] ?? []);
                const from = dayStartMinute + target.row * slotMinutes;
                const to = from + target.slots * slotMinutes;

                for (let minute = from; minute < to; minute += slotMinutes) {
                    if (!open.has(minute)) return false;
                }

                return !grid.appointments.some((a) =>
                    a.id !== this.drag.id && a.date === target.date && a.start < to && a.end > from);
            },

            end() {
                window.removeEventListener('pointermove', this.onMove);
                window.removeEventListener('pointerup', this.onUp);
                const d = this.drag;
                this.drag = null;
                this.preview = null;
                if (!d || !d.moved) return;

                // Le clic qui suit un glisser ne doit pas ouvrir la fenêtre d'édition.
                const swallow = (e) => e.stopPropagation();
                this.$root.addEventListener('click', swallow, { capture: true, once: true });
                setTimeout(() => this.$root.removeEventListener('click', swallow, { capture: true }), 0);

                const t = d.target;
                if (t.date === d.originDate && t.row === d.startRow && t.slots === d.slots) return;
                this.$wire.updateAppointmentTime(d.id, t.date, dayStartMinute + t.row * slotMinutes, t.slots * slotMinutes, d.version);
            },

            rows() {
                return [...this.$root.querySelectorAll('tbody > tr')];
            },

            columns() {
                return [...this.$root.querySelectorAll('thead th[data-date]')];
            },

            locate(event) {
                const pick = (items, value, from, to) => {
                    let index = items.findIndex((el) => value < el.getBoundingClientRect()[to]);
                    return index === -1 ? items.length - 1 : index;
                };
                const rows = this.rows();
                const columns = this.columns();
                return {
                    row: pick(rows, event.clientY, 'top', 'bottom'),
                    date: columns[pick(columns, event.clientX, 'left', 'right')].dataset.date,
                };
            },

            draw(target) {
                const rows = this.rows();
                const column = this.columns().find((th) => th.dataset.date === target.date);
                const root = this.$root.getBoundingClientRect();
                const first = rows[target.row].getBoundingClientRect();
                const last = rows[target.row + target.slots - 1].getBoundingClientRect();
                const col = column.getBoundingClientRect();

                this.preview = {
                    left: col.left - root.left + this.$root.scrollLeft,
                    top: first.top - root.top + this.$root.scrollTop,
                    width: col.width,
                    height: last.bottom - first.top,
                    valid: this.isValid(target),
                };
            },
        });
    </script>
    @endassets

    {{-- Grille --}}
    <div
        x-data="appointmentGrid({{ $slotMinutes }}, {{ \App\Livewire\Schedules\Appointments::DAY_START_MINUTE }})"
        data-grid="{{ json_encode($gridData) }}"
        class="relative overflow-x-auto rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <table class="min-w-full table-fixed border-collapse text-sm">
            <thead>
                <tr class="border-b border-zinc-200 dark:border-zinc-700">
                    <th class="w-20 px-3 py-2 text-left text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                        {{ __('Heure') }}
                    </th>
                    @foreach ($days as $day)
                        @php $isToday = $day->isToday(); @endphp
                        <th data-date="{{ $day->toDateString() }}" class="px-2 py-2 text-center text-xs font-semibold @if($isToday) text-blue-600 dark:text-blue-400 @else text-zinc-500 dark:text-zinc-400 @endif">
                            <div>{{ ucfirst($day->translatedFormat('l')) }}</div>
                            <div @class(['mt-0.5 inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold' => true, 'bg-blue-600 text-white' => $isToday])>
                                {{ $day->translatedFormat('d') }}
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($timeSlots as $slotStart)
                    <tr class="border-b border-zinc-100 last:border-0 dark:border-zinc-800">
                        <td class="px-3 py-1 text-xs font-medium text-zinc-400 dark:text-zinc-500">
                            {{ sprintf('%02d:%02d', intdiv($slotStart, 60), $slotStart % 60) }}
                        </td>
                        @foreach ($days as $day)
                            @php
                                $dateKey = $day->toDateString();
                                $cellKey = $dateKey.':'.$slotStart;

                                if ($coveredCells->contains($cellKey)) {
                                    continue;
                                }

                                $appointment = $appointments->get($dateKey)?->get($slotStart);
                                $isPast = $day->copy()->startOfDay()->addMinutes($slotStart)->lt(\Carbon\Carbon::now());

                                $isClickable = $openSlots[$dateKey][$slotStart] ?? false;
                                $rowspan = $appointment ? (int) ceil($appointment->duration_minutes / $slotMinutes) : 1;
                            @endphp

                            <td
                                @if ($rowspan > 1) rowspan="{{ $rowspan }}" @endif
                                @class([
                                    'relative px-1 py-0.5 align-top transition-colors' => true,
                                    'cursor-pointer hover:bg-blue-50 dark:hover:bg-blue-950/30' => $isClickable && ! $appointment,
                                    'cursor-pointer hover:bg-amber-50 dark:hover:bg-amber-950/30' => $isClickable && $appointment,
                                    'bg-zinc-50/60 dark:bg-zinc-800/40' => ! $isClickable && ! $appointment,
                                    'opacity-40' => $isPast && ! $appointment,
                                ])
                                @if ($isClickable)
                                    wire:click="openCell('{{ $dateKey }}', {{ $slotStart }})"
                                @endif
                            >
                                <div class="min-h-[2rem]">
                                    @if ($appointment)
                                        <div
                                            @if ($isClickable)
                                                data-appt="{{ $appointment->id }}"
                                                data-date="{{ $dateKey }}"
                                                data-start="{{ $appointment->start_minute }}"
                                                data-duration="{{ $appointment->duration_minutes }}"
                                                data-version="{{ $appointment->updated_at?->getTimestamp() }}"
                                                x-on:pointerdown="start($event, 'move')"
                                            @endif
                                            @class([
                                            'absolute inset-0.5 select-none rounded px-1.5 py-1 text-xs leading-tight' => true,
                                            'touch-none cursor-grab active:cursor-grabbing' => $isClickable,
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200' => $isClickable,
                                            'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400' => ! $isClickable,
                                        ])>
                                            <div class="truncate font-medium">{{ $appointment->title }}</div>
                                            @if ($appointment->customer)
                                                <div class="truncate text-[10px] opacity-75">{{ $appointment->customer->firstname }} {{ $appointment->customer->lastname }}</div>
                                            @endif
                                            <div class="mt-0.5 text-[10px] opacity-60">
                                                {{ sprintf('%02d:%02d', intdiv($appointment->start_minute, 60), $appointment->start_minute % 60) }}
                                                – {{ sprintf('%02d:%02d', intdiv($appointment->endMinute(), 60), $appointment->endMinute() % 60) }}
                                            </div>
                                            @if ($isClickable)
                                                <div
                                                    x-on:pointerdown.stop="start($event, 'resize')"
                                                    x-on:click.stop
                                                    class="absolute inset-x-0 bottom-0 flex h-2 cursor-ns-resize items-end justify-center"
                                                    title="{{ __('Ajuster la durée') }}"
                                                >
                                                    <span class="mb-0.5 h-0.5 w-6 rounded bg-blue-400/60"></span>
                                                </div>
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

        <div
            x-show="preview"
            x-cloak
            class="pointer-events-none absolute rounded border-2 border-dashed"
            x-bind:class="preview && !preview.valid ? 'border-red-500 bg-red-500/20' : 'border-blue-500 bg-blue-500/10'"
            x-bind:style="preview && `left:${preview.left}px;top:${preview.top}px;width:${preview.width}px;height:${preview.height}px`"
        ></div>
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
                {{ __('à') }} {{ sprintf('%02d:%02d', intdiv($editingStartMinute, 60), $editingStartMinute % 60) }}
            </flux:text>
        @endif

        @if ($editingAppointment)
            <flux:text class="mb-4 text-xs text-zinc-400">
                @if ($editingAppointment->creator)
                    {{ __('Créé par :name', ['name' => $editingAppointment->creator->fullName()]) }}
                    · {{ $editingAppointment->created_at?->translatedFormat('d M Y H:i') }}
                @endif
                @if ($editingAppointment->lastUpdatedBy && $editingAppointment->updated_at?->ne($editingAppointment->created_at))
                    <br>{{ __('Dernière modification par :name', ['name' => $editingAppointment->lastUpdatedBy->fullName()]) }}
                    · {{ $editingAppointment->updated_at?->translatedFormat('d M Y H:i') }}
                @endif
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
                    <flux:select wire:model="durationMinutes">
                        @for ($m = $slotMinutes; $m <= $maxDuration; $m += $slotMinutes)
                            <flux:select.option value="{{ $m }}">
                                {{ $m >= 60 ? intdiv($m, 60).'h'.($m % 60 ? sprintf('%02d', $m % 60) : '') : $m.' min' }}
                            </flux:select.option>
                        @endfor
                    </flux:select>
                    <flux:error name="durationMinutes" />
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
                            <flux:button size="sm" variant="ghost" icon="user-plus" x-on:click="$dispatch('open-customer-create')">
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
                    <flux:button type="button" variant="ghost" wire:click="closeModal">{{ __('Annuler') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <livewire:customer-form />
</div>

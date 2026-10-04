<div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading level="1" size="xl">{{ __('Paie') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Verrouillez les horaires d\'une période de 2 semaines puis téléchargez le rapport.') }}</flux:text>
        </div>

        <div class="w-72">
            <flux:select wire:model.live="periodStart" :label="__('Période de paie')">
                @foreach ($this->periodOptions() as $option)
                    <flux:select.option :value="$option['start']">{{ $option['label'] }}{{ $option['locked'] ? ' 🔒' : '' }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex items-center gap-2 text-sm">
            @if ($period)
                <flux:badge color="zinc" icon="lock-closed" size="sm">{{ __('Verrouillée') }}</flux:badge>
                <span class="text-zinc-500">
                    {{ __('le :date par :name', ['date' => $period->locked_at->translatedFormat('j M Y à H:i'), 'name' => $period->lockedBy?->fullName() ?? '—']) }}
                </span>
            @else
                <flux:badge color="amber" icon="lock-open" size="sm">{{ __('Non verrouillée') }}</flux:badge>
                <span class="text-zinc-500">{{ __('Les horaires de la période peuvent encore être modifiés.') }}</span>
            @endif
        </div>

        <div class="flex gap-2">
            @can('payroll.lock')
                @if ($period)
                    <flux:button size="sm" variant="ghost" icon="lock-open" wire:click="unlock"
                        wire:confirm="{{ __('Déverrouiller cette période ? Ses horaires redeviendront modifiables.') }}">
                        {{ __('Déverrouiller') }}
                    </flux:button>
                @else
                    <flux:button size="sm" variant="primary" icon="lock-closed" wire:click="lock"
                        wire:confirm="{{ __('Verrouiller les horaires du :start au :end ? Ils ne pourront plus être modifiés.', ['start' => \Illuminate\Support\Carbon::parse($periodStart)->translatedFormat('j M'), 'end' => $periodEnd->translatedFormat('j M Y')]) }}">
                        {{ __('Verrouiller les horaires') }}
                    </flux:button>
                @endif
            @endcan

            <flux:button size="sm" icon="arrow-down-tray" wire:click="export" :disabled="! $period">
                {{ __('Télécharger le rapport') }}
            </flux:button>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Employé') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Heures travaillées') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Maladie payée (h)') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Formation (h)') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Vacances à payer (h)') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Primes ($)') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Commissions ($)') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($rows as $row)
                    <flux:table.row :key="$row['user']->id">
                        <flux:table.cell variant="strong">{{ $row['user']->lastname }}, {{ $row['user']->firstname }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($row['worked_hours'], 2, ',', ' ') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($row['sick_paid_hours'], 2, ',', ' ') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($row['training_hours'], 2, ',', ' ') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($row['vacation_hours'], 2, ',', ' ') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($row['bonus'], 2, ',', ' ') }}</flux:table.cell>
                        <flux:table.cell align="end">{{ number_format($row['commission'], 2, ',', ' ') }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <flux:text class="text-zinc-400">{{ __('Aucun employé pour cette période.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:text class="mt-3 text-sm text-zinc-400">
        {{ __('Primes et commissions : à 0 tant que le module de ventes n\'existe pas.') }}
    </flux:text>
</div>

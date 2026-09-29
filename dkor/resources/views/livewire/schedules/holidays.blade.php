<div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading level="1" size="xl">{{ __('Jours fériés') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Un férié fermé bloque les rendez-vous pour tous. Un férié ouvert s\'attribue comme un congé aux employés qui ne travaillent pas.') }}
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            <flux:button.group>
                <flux:button size="sm" icon="chevron-left" wire:click="previousYear" :label="__('Année précédente')" />
                <flux:button size="sm" disabled>{{ $year }}</flux:button>
                <flux:button size="sm" icon="chevron-right" wire:click="nextYear" :label="__('Année suivante')" />
            </flux:button.group>

            <flux:button size="sm" variant="ghost" icon="sparkles" wire:click="generateQuebec">
                {{ __('Générer les fériés du Québec') }}
            </flux:button>

            <flux:button size="sm" variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Nouveau férié') }}
            </flux:button>
        </div>
    </div>

    @if ($holidays->isEmpty())
        <div class="rounded-xl border border-dashed border-zinc-300 px-4 py-10 text-center text-sm text-zinc-400 dark:border-zinc-700">
            {{ __('Aucun férié en :year.', ['year' => $year]) }}
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($holidays as $holiday)
                <div
                    wire:key="holiday-{{ $holiday->id }}"
                    class="group relative rounded-xl border border-zinc-200 bg-white p-4 shadow-sm transition-colors hover:border-blue-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-blue-700"
                >
                    <button type="button" wire:click="openEdit({{ $holiday->id }})" class="block w-full text-left">
                        <div class="truncate pr-8 font-medium text-zinc-800 dark:text-zinc-100">{{ $holiday->name }}</div>
                        <div class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ ucfirst(\Illuminate\Support\Carbon::parse($holiday->date)->translatedFormat('l d F')) }}
                        </div>
                        <div class="mt-2">
                            <flux:badge size="sm" :color="$holiday->is_closed ? 'violet' : 'zinc'" inset="top bottom">
                                {{ $holiday->is_closed ? __('Magasin fermé') : __('Magasin ouvert') }}
                            </flux:badge>
                        </div>
                    </button>

                    <div class="absolute right-2 top-2 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100">
                        <flux:button
                            size="xs"
                            variant="ghost"
                            icon="trash"
                            wire:click="delete({{ $holiday->id }})"
                            wire:confirm="{{ __('Supprimer ce férié ?') }}"
                            :label="__('Supprimer')"
                        />
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <flux:modal wire:model="showModal" class="w-full max-w-sm">
        <flux:heading class="mb-4">
            {{ $editingId ? __('Modifier le férié') : __('Nouveau férié') }}
        </flux:heading>

        <form wire:submit="save" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" autofocus placeholder="{{ __('Ex: Fête du travail') }}" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Date') }}</flux:label>
                <flux:input wire:model="date" type="date" />
                <flux:error name="date" />
            </flux:field>

            <flux:field variant="inline">
                <flux:switch wire:model="isClosed" />
                <flux:label>{{ __('Magasin fermé ce jour-là') }}</flux:label>
                <flux:error name="isClosed" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

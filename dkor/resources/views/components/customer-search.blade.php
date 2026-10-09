@props(['results', 'search', 'select', 'autofocus' => true])

{{-- Recherche d'un client avec création via le formulaire client si aucun résultat. --}}
<flux:field>
    <flux:label>{{ __('Client') }}</flux:label>

    <flux:input
        wire:model.live.debounce.300ms="customerSearch"
        placeholder="{{ __('Rechercher un client…') }}"
        icon="magnifying-glass"
        :autofocus="$autofocus"
    />

    @if ($results->isNotEmpty())
        <div class="mt-1 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-md dark:border-zinc-700 dark:bg-zinc-900">
            @foreach ($results as $c)
                <button
                    type="button"
                    wire:key="customer-result-{{ $c->id }}"
                    wire:click="{{ $select }}({{ $c->id }})"
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
    @elseif (mb_strlen(trim($search)) >= 4)
        <div class="mt-1 flex items-center justify-between rounded-lg border border-dashed border-zinc-200 px-3 py-2 dark:border-zinc-700">
            <flux:text class="text-sm text-zinc-400">{{ __('Aucun client trouvé.') }}</flux:text>
            @can('customers.create')
                <flux:button size="sm" variant="ghost" icon="user-plus" x-on:click="$dispatch('open-customer-create')">
                    {{ __('Créer') }}
                </flux:button>
            @endcan
        </div>
    @endif
</flux:field>

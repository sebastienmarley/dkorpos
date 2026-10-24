<div class="mt-6 max-w-3xl space-y-4">
    <div class="flex items-start justify-between gap-4">
        <flux:text class="text-zinc-500">
            {{ __('Les zones servent à la prise d\'inventaire : le stock se compte zone par zone. Un emplacement est une place précise dans une zone (allée, étagère, niveau) : on le note sur les articles pour savoir où les chercher.') }}
        </flux:text>
        @can('inventory.supervise')
            <flux:button variant="primary" icon="plus" wire:click="openZone">{{ __('Nouvelle zone') }}</flux:button>
        @endcan
    </div>

    @forelse ($zones as $zone)
        <div wire:key="zone-{{ $zone->id }}" @class([
            'rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900',
            'opacity-60' => ! $zone->is_active,
        ])>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <div class="flex items-center gap-2">
                    <flux:icon.map class="h-4 w-4 text-zinc-400" />
                    <span class="font-medium text-zinc-900 dark:text-white">{{ $zone->name }}</span>
                    <span class="text-xs text-zinc-400">{{ __('Ordre :order', ['order' => $zone->sort_order]) }}</span>
                    @unless ($zone->is_active)
                        <flux:badge size="sm">{{ __('Désactivée') }}</flux:badge>
                    @endunless
                </div>
                @can('inventory.supervise')
                    <div class="flex gap-1">
                        <flux:button size="xs" variant="ghost" icon="plus" wire:click="openLocation({{ $zone->id }})">{{ __('Emplacement') }}</flux:button>
                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openZone({{ $zone->id }})">{{ __('Modifier') }}</flux:button>
                        <flux:button size="xs" variant="ghost" wire:click="toggleZone({{ $zone->id }})">{{ $zone->is_active ? __('Désactiver') : __('Réactiver') }}</flux:button>
                    </div>
                @endcan
            </div>

            <ul class="divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                @forelse ($zone->locations as $location)
                    <li wire:key="location-{{ $location->id }}" @class(['flex items-center justify-between gap-3 px-4 py-2', 'opacity-50' => ! $location->is_active])>
                        <div>
                            <span class="font-mono font-medium">{{ $location->code }}</span>
                            @if ($location->description)
                                <span class="text-zinc-500">· {{ $location->description }}</span>
                            @endif
                            @unless ($location->is_active)
                                <flux:badge size="sm" class="ms-2">{{ __('Désactivé') }}</flux:badge>
                            @endunless
                        </div>
                        @can('inventory.supervise')
                            <div class="flex gap-1">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openLocation(null, {{ $location->id }})" :label="__('Modifier')" />
                                <flux:button size="xs" variant="ghost" wire:click="toggleLocation({{ $location->id }})">{{ $location->is_active ? __('Désactiver') : __('Réactiver') }}</flux:button>
                            </div>
                        @endcan
                    </li>
                @empty
                    <li class="px-4 py-3 text-zinc-400">{{ __('Aucun emplacement dans cette zone.') }}</li>
                @endforelse
            </ul>
        </div>
    @empty
        <div class="rounded-xl border border-zinc-200 bg-white py-12 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <flux:icon name="map" class="mx-auto h-8 w-8 text-zinc-300" />
            <flux:text class="text-zinc-400">{{ __('Aucune zone : sans zone, le stock se compte à la main dans la prise.') }}</flux:text>
        </div>
    @endforelse

    <flux:modal wire:model="showZoneModal" class="w-full max-w-md">
        <form wire:submit="saveZone" class="space-y-4">
            <flux:heading>{{ $editingZoneId ? __('Modifier la zone') : __('Nouvelle zone') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="zoneName" :placeholder="__('Allée 1, Mezzanine…')" required />
                <flux:error name="zoneName" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Ordre') }}</flux:label>
                <flux:input wire:model="zoneSortOrder" type="number" min="0" required />
                <flux:error name="zoneSortOrder" />
            </flux:field>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showZoneModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showLocationModal" class="w-full max-w-md">
        <form wire:submit="saveLocation" class="space-y-4">
            <flux:heading>{{ $editingLocationId ? __('Modifier l\'emplacement') : __('Nouvel emplacement') }}</flux:heading>
            <flux:field>
                <flux:label>{{ __('Zone') }}</flux:label>
                <flux:select wire:model="locationZoneId">
                    @foreach ($zones as $zone)
                        <flux:select.option :value="$zone->id">{{ $zone->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="locationZoneId" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Code') }}</flux:label>
                <flux:input wire:model="locationCode" :placeholder="__('A-03-2')" required />
                <flux:description>{{ __('Unique dans le magasin. C\'est le code balayé avec la douchette.') }}</flux:description>
                <flux:error name="locationCode" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="locationDescription" :placeholder="__('Étagère du haut, côté quai…')" />
                <flux:error name="locationDescription" />
            </flux:field>
            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showLocationModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

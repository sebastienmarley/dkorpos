<div class="space-y-2">
    <div class="flex flex-wrap items-end gap-2">
        <flux:field class="min-w-40 flex-1">
            <flux:label>{{ __('Emplacement') }}</flux:label>
            <flux:select wire:model="locationId" size="sm">
                <flux:select.option value="">{{ __('— Aucun —') }}</flux:select.option>
                @if ($line->location && ! $line->location->is_active)
                    <flux:select.option :value="$line->location->id">{{ $line->location->label() }} ({{ __('désactivé') }})</flux:select.option>
                @endif
                @foreach ($this->getLocations() as $location)
                    <flux:select.option :value="$location->id">{{ $location->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="locationId" />
        </flux:field>
        <flux:button size="sm" wire:click="save">{{ __('Ranger') }}</flux:button>
    </div>
    <flux:field>
        <flux:input wire:model="scan" wire:keydown.enter.prevent="scanned" size="sm" icon="qr-code" autocomplete="off" :placeholder="__('Balayer le code de l\'emplacement…')" />
        <flux:error name="scan" />
    </flux:field>
    @if ($line->location)
        <flux:text class="text-xs text-zinc-500">{{ __('Rangé à : :location', ['location' => $line->location->label()]) }}</flux:text>
    @elseif ($locationId !== '')
        <flux:text class="text-xs text-zinc-400">{{ __('Emplacement habituel du produit proposé.') }}</flux:text>
    @endif
</div>

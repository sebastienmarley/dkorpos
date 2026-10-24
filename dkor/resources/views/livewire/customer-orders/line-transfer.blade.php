<div>
    @if ($eligible && $sources->isNotEmpty())
        <div class="space-y-2 rounded-lg border border-cyan-200 bg-cyan-50 p-3 dark:border-cyan-800 dark:bg-cyan-950">
            <flux:heading size="sm">{{ __('Transférer d\'un autre magasin') }}</flux:heading>
            <flux:text class="text-xs">{{ __('Plutôt que de commander au fournisseur, demandez la marchandise d\'un autre magasin.') }}</flux:text>
            <div class="flex flex-wrap items-end gap-2">
                <flux:field class="min-w-48 flex-1">
                    <flux:select wire:model="source" size="sm">
                        @foreach ($sources as $option)
                            <flux:select.option :value="$option['key']">{{ $option['label'] }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>
                <div class="w-20">
                    <flux:input wire:model="quantity" type="number" min="1" size="sm" />
                </div>
                @can('transfers.request')
                    <flux:button size="sm" variant="primary" icon="arrows-right-left" wire:click="request">{{ __('Demander') }}</flux:button>
                @endcan
            </div>
            <flux:error name="quantity" />
        </div>
    @endif
</div>

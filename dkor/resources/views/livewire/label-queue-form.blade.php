<div>
    <flux:modal wire:model="showModal" class="w-full max-w-sm">
        @if ($product)
            <flux:heading class="mb-1">{{ __('Étiquette de prix') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ $product->model }}</flux:text>

            <form wire:submit="save" class="mt-6 space-y-4">
                <flux:field>
                    <flux:label>{{ __('Nombre d\'étiquettes') }}</flux:label>
                    <flux:input wire:model="quantity" type="number" min="1" max="99" required autofocus />
                    <flux:description>{{ trans_choice(':count unité en démo.|:count unités en démo.', $product->totalQuantity(\App\Enums\InventoryStatus::InDemo)) }}</flux:description>
                    <flux:error name="quantity" />
                </flux:field>

                <div class="flex justify-between gap-3 pt-2">
                    <flux:button icon="printer" :href="route('products.label', $product)" target="_blank" x-on:click="$wire.set('showModal', false)">
                        {{ __('Imprimer maintenant') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" icon="queue-list">{{ __('Ajouter à la file') }}</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>

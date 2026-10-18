<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Ajustements d\'inventaire') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Pertes, vols, usage magasin, corrections de comptage et stock initial, avec leur écriture au grand livre.') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="adjustments-horizontal" wire:click="openAdjust">{{ __('Nouvel ajustement') }}</flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$adjustments">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Produit') }}</flux:table.column>
                <flux:table.column>{{ __('Motif') }}</flux:table.column>
                <flux:table.column>{{ __('État') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Qté') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Valeur') }}</flux:table.column>
                <flux:table.column>{{ __('Par') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($adjustments as $adjustment)
                    <flux:table.row :key="$adjustment->id">
                        <flux:table.cell>{{ $adjustment->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                        <flux:table.cell variant="strong">
                            <flux:link :href="route('products.show', $adjustment->product)" wire:navigate>{{ $adjustment->product->display_name }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $adjustment->reason->label() }}
                            <flux:text class="text-xs text-zinc-400">{{ $adjustment->note }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge :color="$adjustment->status->color()" size="sm">{{ $adjustment->status->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end" class="{{ $adjustment->is_entry ? 'text-green-600' : 'text-red-600' }}">
                            {{ $adjustment->is_entry ? '+' : '−' }}{{ $adjustment->quantity }}
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            {{ $adjustment->movement?->value?->format() ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $adjustment->user ? $adjustment->user->firstname.' '.$adjustment->user->lastname : '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="adjustments-horizontal" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucun ajustement d\'inventaire.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Nouvel ajustement --}}
    <flux:modal wire:model="showAdjust" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Nouvel ajustement d\'inventaire') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Une sortie est valorisée au coût des unités les plus anciennes; une entrée, au coût du produit. L\'écriture au grand livre suit.') }}</flux:text>

        <div class="mt-6 space-y-4">
            @if ($selectedProduct)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div>
                        <flux:text class="font-medium">{{ $selectedProduct->display_name }}</flux:text>
                        @if ($selectedProduct->inventoryStock)
                            <flux:text class="text-xs text-zinc-400">
                                @foreach ($reasonStatuses as $reasonStatus)
                                    {{ $reasonStatus->label() }} : {{ $selectedProduct->inventoryStock->quantityFor($reasonStatus) }}{{ ! $loop->last ? ' · ' : '' }}
                                @endforeach
                            </flux:text>
                        @endif
                    </div>
                    <flux:button size="xs" variant="ghost" wire:click="$set('adjustProductId', null)">{{ __('Changer') }}</flux:button>
                </div>
                <flux:error name="adjustProductId" />

                <form wire:submit="adjust" class="space-y-4">
                    <flux:select wire:model.live="reason" :label="__('Motif')">
                        @foreach ($reasons as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="direction" :label="__('Sens')">
                            @if ($selectedReason->allowsExit())
                                <flux:select.option value="exit">{{ __('Sortie') }}</flux:select.option>
                            @endif
                            @if ($selectedReason->allowsEntry())
                                <flux:select.option value="entry">{{ __('Entrée') }}</flux:select.option>
                            @endif
                        </flux:select>
                        <flux:select wire:model="status" :label="__('État')">
                            @foreach ($reasonStatuses as $reasonStatus)
                                <flux:select.option :value="$reasonStatus->value">{{ $reasonStatus->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Quantité') }}</flux:label>
                        <flux:input wire:model="quantity" type="number" min="1" step="1" required />
                        <flux:error name="quantity" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Note') }}</flux:label>
                        <flux:input wire:model="note" type="text" required />
                        <flux:error name="note" />
                    </flux:field>

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showAdjust', false)">{{ __('Annuler') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
                    </div>
                </form>
            @else
                <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Rechercher un produit…')" />

                <div class="max-h-72 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                    @forelse ($productResults as $result)
                        <button type="button" wire:key="product-{{ $result->id }}" wire:click="selectProduct({{ $result->id }})"
                            class="flex w-full items-center justify-between px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span>{{ $result->display_name }}</span>
                        </button>
                    @empty
                        <div class="p-4 text-center"><flux:text class="text-zinc-400">{{ __('Aucun produit trouvé.') }}</flux:text></div>
                    @endforelse
                </div>
            @endif
        </div>
    </flux:modal>
</div>

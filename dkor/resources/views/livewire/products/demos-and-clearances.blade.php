<div class="mt-6">
    <flux:heading size="sm" class="mb-1">{{ __('Démonstrateurs et liquidations') }}</flux:heading>
    <flux:text class="mb-3 text-sm text-zinc-500">{{ __('Un démonstrateur donne son prix à un article en démo : passez d\'abord l\'article en démo dans les mouvements d\'inventaire. Une liquidation vend le stock en main du magasin à son prix, sans commande au fournisseur.') }}</flux:text>

    <div class="space-y-4">
        @forelse ($this->getStoreRows() as $row)
            @php($store = $row['store'])
            @php($canManage = $this->canManage($store->id))
            <div wire:key="markdown-store-{{ $store->id }}" class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <flux:heading size="sm">{{ $store->name }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500">{{ trans_choice(':count article en démo|:count articles en démo', $row['inDemo']) }}</flux:text>
                </div>

                <div class="space-y-2">
                    @foreach ($row['demos'] as $demo)
                        @php($takenBy = $demo->lines->first(fn ($line) => $line->status !== \App\Enums\CustomerOrderLineStatus::Cancelled))
                        <div wire:key="demo-{{ $demo->id }}" class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <span class="font-medium">{{ __('Démonstrateur') }} · {{ $demo->price->format() }}</span>
                                @if ($demo->is_as_is)
                                    <flux:badge size="sm" color="amber" class="ms-1">{{ __('Tel quel') }}</flux:badge>
                                @endif
                                @if ($takenBy)
                                    <flux:badge size="sm" color="zinc" class="ms-1">{{ __('Commande #:id', ['id' => $takenBy->customer_order_id]) }}</flux:badge>
                                @endif
                                @if ($demo->note)
                                    <div class="truncate text-xs text-zinc-400">{{ $demo->note }}</div>
                                @endif
                            </div>
                            @if ($canManage && ! $takenBy)
                                <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeDemo({{ $demo->id }})" wire:confirm="{{ __('Retirer ce démonstrateur ?') }}" :label="__('Retirer')" />
                            @endif
                        </div>
                    @endforeach

                    @if ($canManage)
                        @if ($demoStoreId === $store->id)
                            <form wire:submit="declareDemo" class="space-y-3 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800">
                                <div class="flex flex-wrap items-start gap-3">
                                    <flux:field class="w-36">
                                        <flux:label>{{ __('Prix') }}</flux:label>
                                        <flux:input wire:model="demoPrice" inputmode="decimal" required />
                                    </flux:field>
                                    <flux:field class="min-w-48 flex-1">
                                        <flux:label>{{ __('Note') }}</flux:label>
                                        <flux:input wire:model="demoNote" :placeholder="__('Égratignure côté gauche…')" />
                                    </flux:field>
                                </div>
                                <flux:error name="demoPrice" />
                                <flux:error name="demoNote" />
                                <flux:checkbox wire:model="demoIsAsIs" :label="__('Vendu tel quel (non retournable)')" />
                                <div class="flex justify-end gap-2">
                                    <flux:button type="button" size="sm" variant="ghost" wire:click="$set('demoStoreId', null)">{{ __('Annuler') }}</flux:button>
                                    <flux:button type="submit" size="sm" variant="primary">{{ __('Ajouter') }}</flux:button>
                                </div>
                            </form>
                        @else
                            <flux:button size="sm" variant="ghost" icon="plus" wire:click="openDemoForm({{ $store->id }})">{{ __('Ajouter un démonstrateur') }}</flux:button>
                        @endif
                    @endif
                </div>

                <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                    @if ($canManage)
                        <div class="flex flex-wrap items-end gap-2">
                            <flux:field class="w-40">
                                <flux:label>{{ __('Prix de liquidation') }}</flux:label>
                                <flux:input size="sm" wire:model="clearancePrices.{{ $store->id }}" inputmode="decimal" />
                            </flux:field>
                            <flux:button size="sm" wire:click="saveClearance({{ $store->id }})">{{ $row['clearance'] ? __('Changer le prix') : __('Mettre en liquidation') }}</flux:button>
                            @if ($row['clearance'])
                                <flux:button size="sm" variant="ghost" wire:click="endClearance({{ $store->id }})" wire:confirm="{{ __('Mettre fin à la liquidation dans ce magasin ?') }}">{{ __('Terminer') }}</flux:button>
                                <flux:button size="sm" variant="ghost" icon="printer" :href="route('products.label', ['product' => $product, 'store' => $store->id])" target="_blank">{{ __('Étiquette') }}</flux:button>
                            @endif
                        </div>
                        <flux:error name="clearancePrices.{{ $store->id }}" />
                    @elseif ($row['clearance'])
                        <flux:text class="text-sm">{{ __('Liquidation : :price', ['price' => $row['clearance']->price->format()]) }}</flux:text>
                    @else
                        <flux:text class="text-sm text-zinc-400">{{ __('Pas en liquidation.') }}</flux:text>
                    @endif
                </div>
            </div>
        @empty
            <flux:text class="text-sm text-zinc-400">{{ __('Aucun démonstrateur ni liquidation.') }}</flux:text>
        @endforelse
    </div>
</div>

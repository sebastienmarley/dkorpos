<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 grid grid-cols-1 items-center gap-4 md:grid-cols-[1fr_auto_1fr]">
        <div>
            <flux:link :href="route('customer-orders.index')" wire:navigate class="text-sm">
                ← {{ __('Commandes clients') }}
            </flux:link>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading level="1" size="xl">{{ __('Commande #:id', ['id' => $order->id]) }}</flux:heading>
                <flux:badge :color="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge>
            </div>
            <flux:text class="mt-1 text-zinc-500">
                {{ __('Créée le :date', ['date' => $order->created_at->format('Y-m-d')]) }}
                @if ($order->creator)
                    {{ __('par :name', ['name' => $order->creator->fullName()]) }}
                @endif
            </flux:text>
        </div>

        {{-- Client et vendeurs --}}
        <div class="flex flex-col items-center gap-2">
            <div class="flex items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                <flux:icon.user class="h-4 w-4 text-zinc-400" />
                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $order->customer->firstname }} {{ $order->customer->lastname }}</span>
            </div>

            <div class="text-center text-sm text-zinc-600 dark:text-zinc-300">
                <span class="font-medium">{{ __('Vendeur(s)') }} :</span>
                {{ $order->salespeople->map(fn ($salesperson) => $salesperson->fullName())->join(', ') ?: '—' }}
            </div>
        </div>

        <div class="flex flex-col items-start gap-2 md:items-end">
            @can('customer_orders.edit')
                <flux:button variant="primary" icon="user-plus" wire:click="openCustomerModal">
                    {{ __('Changer le client') }}
                </flux:button>
            @endcan

            @can('customer_orders.assign_salespeople')
                <flux:button icon="users" wire:click="openSalespeopleModal">
                    {{ __('Ajouter un vendeur') }}
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Produits --}}
    <div class="mb-6">
        @can('customer_orders.edit')
            <div class="mb-3 flex items-start gap-3">
                <flux:button icon="plus" wire:click="openProductModal">{{ __('Ajouter un produit') }}</flux:button>

                <form wire:submit="scanUpc" class="w-64">
                    <flux:field>
                        <flux:input wire:model="upcScan" icon="qr-code" inputmode="numeric" autocomplete="off" autofocus :placeholder="__('Scanner un UPC…')" />
                        <flux:error name="upcScan" />
                    </flux:field>
                </form>
            </div>
        @endcan

        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Produit') }}</flux:table.column>
                    <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                    <flux:table.column>{{ __('Statut') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('En stock') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('En commande') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Qté totale') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Prix') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Total') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @php($canEditLines = auth()->user()->can('customer_orders.edit'))
                    @forelse ($order->lines as $line)
                        <flux:table.row
                            :key="$line->id"
                            x-on:click="{{ $canEditLines ? '$wire.openLineModal('.$line->id.')' : '' }}"
                            :class="$canEditLines ? 'cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-800' : ''"
                        >
                            <flux:table.cell variant="strong">
                                {{ $line->product->model }}
                                <span class="font-normal text-zinc-500">#{{ $line->product_id }}</span>
                                @if ($line->note)
                                    <div class="max-w-xs truncate text-xs font-normal text-zinc-400">{{ $line->note }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $line->product->supplier->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$line->status->color()" size="sm">{{ $line->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity_reserved }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity_on_order }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($line->unit_price, 2) }} $</flux:table.cell>
                            <flux:table.cell align="end">{{ number_format($line->total, 2) }} $</flux:table.cell>
                            <flux:table.cell align="end">
                                @can('customer_orders.edit')
                                    @if ($line->status->isEditable())
                                        <flux:button size="sm" variant="ghost" icon="x-mark" wire:click.stop="removeLine({{ $line->id }})" :label="__('Retirer')" />
                                    @endif
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9" class="py-8 text-center">
                                <flux:text class="text-zinc-400">{{ __('Aucun produit dans cette commande.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>

        <div class="mt-3 flex justify-end">
            <flux:text class="text-sm">
                <span class="font-medium">{{ __('Solde à payer') }} :</span> {{ number_format($order->balance_due, 2) }} $
            </flux:text>
        </div>
    </div>

    @can('customer_orders.assign_salespeople')
        <flux:modal wire:model="showSalespeopleModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Vendeurs') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Répartir la vente entre un maximum de 3 vendeurs. La somme doit être de 100 %.') }}</flux:text>

            <form wire:submit="saveSalespeople" class="mt-6 space-y-3">
                @foreach ($salespeopleDraft as $index => $row)
                    <div wire:key="draft-{{ $index }}" class="flex items-start gap-2">
                        <div class="flex-1">
                            <flux:select wire:model="salespeopleDraft.{{ $index }}.user_id">
                                <flux:select.option value="">{{ __('Sélectionner un employé…') }}</flux:select.option>
                                @foreach ($this->getEmployees() as $employee)
                                    <flux:select.option :value="$employee->id" :disabled="collect($salespeopleDraft)->except($index)->pluck('user_id')->contains($employee->id)">{{ $employee->fullName() }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="salespeopleDraft.{{ $index }}.user_id" />
                        </div>

                        <div class="w-28">
                            <flux:select wire:model="salespeopleDraft.{{ $index }}.percent">
                                @foreach (collect(\App\Livewire\CustomerOrders\Show::PERCENT_OPTIONS)->when(! in_array((int) $row['percent'], \App\Livewire\CustomerOrders\Show::PERCENT_OPTIONS, true), fn ($options) => $options->push((int) $row['percent']))->sort() as $percent)
                                    <flux:select.option :value="$percent">{{ $percent }} %</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="salespeopleDraft.{{ $index }}.percent" />
                        </div>

                        @if (count($salespeopleDraft) > 1)
                            <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeSalesperson({{ $index }})" :label="__('Retirer')" />
                        @endif
                    </div>
                @endforeach

                <flux:error name="salespeopleDraft" />

                @if (count($salespeopleDraft) < \App\Livewire\CustomerOrders\Show::MAX_SALESPEOPLE)
                    <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addSalesperson">{{ __('Ajouter un vendeur') }}</flux:button>
                @endif

                <div class="flex justify-end gap-3 pt-2">
                    <flux:button type="button" variant="ghost" wire:click="$set('showSalespeopleModal', false)">{{ __('Annuler') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    {{-- Changement de client --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showCustomerModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Changer le client') }}</flux:heading>

            <div class="mt-6">
                <x-customer-search :results="$customerResults" :search="$customerSearch" select="selectCustomer" />
            </div>
        </flux:modal>
    @endcan

    {{-- Recherche de produit --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showProductModal" class="w-full max-w-lg">
            <flux:heading class="mb-1">{{ __('Ajouter un produit') }}</flux:heading>

            <div class="mt-6 space-y-4">
                <flux:field>
                    <flux:label>{{ __('Fournisseur') }}</flux:label>
                    <flux:select wire:model.live="productSupplierId">
                        <flux:select.option value="">{{ __('Tous — recherche par ID seulement') }}</flux:select.option>
                        @foreach ($this->getSuppliers() as $supplier)
                            <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:field>

                <flux:field>
                    <flux:label>{{ filled($productSupplierId) ? __('Produit') : __('ID du produit') }}</flux:label>
                    <flux:input
                        wire:model.live.debounce.300ms="productSearch"
                        icon="magnifying-glass"
                        :placeholder="filled($productSupplierId) ? __('ID, modèle ou collection…') : __('ID du produit…')"
                    />

                    @if (filled($productSearch))
                        <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                            @forelse ($this->getProductResults() as $result)
                                <button type="button" wire:key="product-result-{{ $result->id }}" wire:click="addProduct({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                    <span class="min-w-0">
                                        <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                        <span class="text-zinc-500"> · {{ $result->supplier->name }} · #{{ $result->id }}</span>
                                    </span>
                                </button>
                            @empty
                                <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun produit trouvé.') }}</div>
                            @endforelse
                        </div>
                    @endif
                </flux:field>

                @if (filled($productSupplierId))
                    <flux:field>
                        <flux:label>{{ __('Chercher dans les listes de prix') }}</flux:label>
                        <flux:input wire:model.live.debounce.300ms="priceListSearch" icon="magnifying-glass" :placeholder="__('Modèle ou collection…')" />

                        @if (filled($priceListSearch))
                            <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                                @forelse ($this->getPriceListResults() as $result)
                                    <button type="button" wire:key="price-list-{{ $result->id }}" wire:click="addFromPriceList({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                        <span class="min-w-0">
                                            <span class="font-medium text-zinc-900 dark:text-white">{{ $result->model }}</span>
                                            @if ($result->collection)
                                                <span class="text-zinc-500"> · {{ $result->collection }}</span>
                                            @endif
                                        </span>
                                        <span class="flex-shrink-0 text-zinc-500">{{ number_format($result->cost ?? 0, 2) }} $</span>
                                    </button>
                                @empty
                                    <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun résultat dans les listes de prix actives.') }}</div>
                                @endforelse
                            </div>
                        @endif
                    </flux:field>
                @endif
            </div>
        </flux:modal>
    @endcan

    {{-- Édition d'une ligne --}}
    @can('customer_orders.edit')
        <flux:modal wire:model="showLineModal" class="w-full max-w-lg">
            @if ($editingLine)
                <flux:heading class="mb-1">{{ $editingLine->product->model }} <span class="font-normal text-zinc-500">#{{ $editingLine->product_id }}</span></flux:heading>
                <flux:badge :color="$editingLine->status->color()" size="sm">{{ $editingLine->status->label() }}</flux:badge>

                <form wire:submit="saveLine" class="mt-6 space-y-4">
                    @php($editable = $editingLine->status->isEditable())

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>
                                {{ __('En stock (réservé)') }}
                                <span class="ms-1 font-normal text-zinc-400">{{ __('max. :count', ['count' => $editingLine->quantity_reserved + ($editingLine->product->inventoryStock?->quantityAvailable() ?? 0)]) }}</span>
                            </flux:label>
                            <flux:input wire:model="editReserved" type="number" min="0" :disabled="! $editable" />
                            <flux:error name="editReserved" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('En commande') }}</flux:label>
                            <flux:input wire:model="editOnOrder" type="number" min="0" :disabled="! $editable" />
                            <flux:error name="editOnOrder" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Prix vendant') }}</flux:label>
                        <flux:input wire:model="editUnitPrice" inputmode="decimal" :disabled="! $editable" />
                        <flux:description>{{ __('Prix suggéré : :price $', ['price' => number_format($editingLine->product->selling_price, 2)]) }}</flux:description>
                        <flux:error name="editUnitPrice" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Note') }}</flux:label>
                        <flux:textarea wire:model="editNote" rows="3" />
                        <flux:error name="editNote" />
                    </flux:field>

                    <div class="flex justify-end gap-3 pt-2">
                        <flux:button type="button" variant="ghost" wire:click="$set('showLineModal', false)">{{ __('Annuler') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
                    </div>
                </form>
            @endif
        </flux:modal>
    @endcan

    <livewire:customer-form />
</div>

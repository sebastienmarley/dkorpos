<div class="sm:p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <flux:heading level="1" size="xl">{{ __('Services') }}</flux:heading>
            <flux:tooltip :content="__('Services vendus aux clients : rendus par le magasin ou par des fournisseurs')">
                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
            </flux:tooltip>
        </div>
        @can('services.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">{{ __('Ajouter') }}</flux:button>
        @endcan
    </div>

    {{-- Filtres : la page s'ouvre sur les services rendus par le magasin. Jusqu'à md, la ligne est pleine. --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="w-full sm:w-auto sm:flex-1 lg:w-72 lg:flex-none">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Rechercher un service…')" />
        </div>

        <div class="w-full sm:w-56">
            <flux:select wire:model.live="providerFilter">
                <flux:select.option value="{{ \App\Livewire\Catalog\Services::PROVIDER_INTERNAL }}">{{ __('Interne') }}</flux:select.option>
                @foreach ($providers as $provider)
                    <flux:select.option :value="$provider->id">{{ $provider->name }}</flux:select.option>
                @endforeach
                <flux:select.option value="">{{ __('Tous les fournisseurs') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Service') }}</flux:table.column>
                <flux:table.column>{{ __('Rendu par') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Prix') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @php
                    $canEditServices = auth()->user()->can('services.edit');
                @endphp
                @forelse ($services as $service)
                    <x-table.row-link :key="$service->id" :action="$canEditServices ? '$wire.openEdit('.$service->id.')' : null" @class(['opacity-50' => ! $service->is_active])>
                        <flux:table.cell variant="strong">
                            {{ $service->name }}
                            @unless ($service->is_active)
                                <flux:badge size="sm" class="ms-2">{{ __('Inactif') }}</flux:badge>
                            @endunless
                            @if ($service->isSystem())
                                <flux:badge size="sm" color="zinc" icon="lock-closed" class="ms-2" :title="__('Géré par l\'application')"><span class="max-sm:hidden">{{ __('Géré par l\'application') }}</span></flux:badge>
                            @endif
                            @if ($service->description_template)
                                <div class="max-w-md truncate text-xs font-normal text-zinc-400">{{ $service->description_template }}</div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($service->is_internal)
                                <flux:badge size="sm" color="blue">{{ __('Interne') }}</flux:badge>
                            @else
                                {{ $service->suppliers->pluck('name')->join(', ') ?: '—' }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            @if ($service->is_internal)
                                {{ ($service->selling_price ?? \App\Support\Money::zero())->format() }}
                            @elseif ($service->suppliers->isNotEmpty())
                                @php($prices = $service->suppliers->map(fn ($supplier) => $supplier->pivot->selling_price)->sortBy(fn ($price) => $price->cents())->values())
                                {{ $prices->first()->equals($prices->last()) ? $prices->first()->format() : rtrim($prices->first()->format(), ' $').' – '.$prices->last()->format() }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                    </x-table.row-link>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="wrench" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucun service pour ces critères.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-2xl">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier le service') : __('Nouveau service') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" :placeholder="__('Installation, Livraison, Main-d\'œuvre…')" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description modèle') }}</flux:label>
                <flux:input wire:model="descriptionTemplate" :placeholder="__('Installation de {produit}, raccordement inclus')" />
                <flux:description>{{ __('Proposée à chaque vente et modifiable. {produit} est remplacé par le produit visé.') }}</flux:description>
                <flux:error name="descriptionTemplate" />
            </flux:field>

            <div class="flex flex-wrap gap-6">
                <flux:checkbox wire:model.live="isInternal" :label="__('Service interne (rendu par le magasin)')" :disabled="$this->isEditingSystemService()" />
                <flux:checkbox wire:model="isTaxable" :label="__('Taxable (TPS/TVQ)')" />
                <flux:checkbox wire:model="isActive" :label="__('Actif')" :disabled="$this->isEditingSystemService()" />
            </div>
            @if ($this->isEditingSystemService())
                <flux:text class="text-sm text-zinc-500">{{ __('Service géré par l\'application : il reste interne et actif. Son prix est proposé à chaque expédition.') }}</flux:text>
            @endif

            {{-- Clés : le prix d'un service interne et les fournisseurs d'un service externe ne partagent pas leurs champs. --}}
            @if ($isInternal)
                <flux:field class="max-w-xs" wire:key="internal-price">
                    <flux:label>{{ __('Prix vendant') }}</flux:label>
                    <flux:input wire:model="sellingPrice" inputmode="decimal" />
                    <flux:error name="sellingPrice" />
                </flux:field>
            @else
                <div wire:key="supplier-offers">
                    <flux:heading size="sm" class="mb-2">{{ __('Fournisseurs') }}</flux:heading>

                    <div class="space-y-2">
                        @foreach ($offers as $index => $offer)
                            <div wire:key="offer-{{ $index }}" class="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                                <div class="max-sm:basis-full sm:flex-1">
                                    <flux:select wire:model="offers.{{ $index }}.supplier_id">
                                        <flux:select.option value="">{{ __('Fournisseur…') }}</flux:select.option>
                                        @foreach ($this->getServiceSuppliers() as $supplier)
                                            <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="offers.{{ $index }}.supplier_id" />
                                </div>
                                <div class="max-sm:flex-1 sm:w-28">
                                    <flux:input wire:model="offers.{{ $index }}.cost" inputmode="decimal" :placeholder="__('Coût')" />
                                    <flux:error name="offers.{{ $index }}.cost" />
                                </div>
                                <div class="max-sm:flex-1 sm:w-28">
                                    <flux:input wire:model="offers.{{ $index }}.selling_price" inputmode="decimal" :placeholder="__('Prix vendant')" />
                                    <flux:error name="offers.{{ $index }}.selling_price" />
                                </div>
                                <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeOffer({{ $index }})" :label="__('Retirer')" />
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-1 flex items-center justify-between text-xs text-zinc-400">
                        <span>{{ __('Coût du fournisseur · prix vendant au client') }}</span>
                        <flux:button type="button" size="sm" variant="ghost" icon="plus" wire:click="addOffer">{{ __('Ajouter un fournisseur') }}</flux:button>
                    </div>
                    <flux:error name="offers" />
                </div>
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

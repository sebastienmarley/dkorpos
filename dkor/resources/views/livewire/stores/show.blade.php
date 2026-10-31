<div class="sm:p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('stores.index')" wire:navigate size="sm">
            {{ __('Retour') }}
        </flux:button>

        <div class="min-w-0 flex-1">
            <flux:heading level="1" size="xl">{{ $store->name }}</flux:heading>
            <div class="mt-1 flex items-center gap-2">
                <flux:badge :color="$store->type->color()" size="sm">
                    {{ $store->type->label() }}
                </flux:badge>
                @if (! $store->is_active)
                    <flux:badge color="zinc" size="sm">{{ __('Inactif') }}</flux:badge>
                @endif
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('activeTab') }">
        <div class="flex overflow-x-auto overflow-y-hidden border-b border-zinc-200 [scrollbar-width:none] dark:border-zinc-700">
            @foreach ($this->getTabs() as $key => $label)
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px shrink-0 px-4 py-3 text-sm font-medium whitespace-nowrap transition-colors"
                >{{ $label }}</button>
            @endforeach
        </div>

        @can('stores.view')
        {{-- Identification --}}
        <div x-show="tab === 'identification'" x-cloak>
            <form wire:submit="saveIdentification" class="mt-6 max-w-2xl space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Nom de l\'emplacement') }}</flux:label>
                        <flux:input wire:model="name" type="text" required />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Type') }}</flux:label>
                        <flux:select wire:model="type">
                            @foreach ($this->getStoreTypes() as $storeType)
                                <flux:select.option :value="$storeType->value">{{ $storeType->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="type" />
                    </flux:field>
                </div>

                <flux:field>
                    <div class="flex items-center gap-1.5">
                        <flux:label>{{ __('Province (taxes)') }}</flux:label>
                        <flux:tooltip :content="__('Les taxes de la province s\'appliquent aux ventes du magasin.')">
                            <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                        </flux:tooltip>
                    </div>
                    <flux:select wire:model="province">
                        @foreach ($provinces as $provinceOption)
                            <flux:select.option :value="$provinceOption->value">{{ $provinceOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="province" />
                </flux:field>

                <div>
                    <flux:heading size="sm" class="mb-4">{{ __('Adresse') }}</flux:heading>
                    <x-address-input prefix="address" :current-country="$address['country'] ?? 'CA'" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-phone-input wire:model="phone" label="{{ __('Téléphone') }}" name="phone" />

                    <flux:field>
                        <flux:label>{{ __('Courriel') }}</flux:label>
                        <flux:input wire:model="email" type="email" />
                        <flux:error name="email" />
                    </flux:field>
                </div>

                <div class="flex justify-end pt-2">
                    @can('stores.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- Comptabilité --}}
        <div x-show="tab === 'accounting'" x-cloak>
            <form wire:submit="saveAccounting" class="mt-6 max-w-2xl space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    @forelse ($taxNumbers as $index => $taxNumber)
                        <flux:field wire:key="tax-number-{{ $taxNumber['name'] }}">
                            <flux:label>{{ __('Numéro de :tax', ['tax' => $taxNumber['name']]) }}</flux:label>
                            <flux:input wire:model="taxNumbers.{{ $index }}.number" type="text" />
                            <flux:error name="taxNumbers.{{ $index }}.number" />
                        </flux:field>
                    @empty
                        <flux:text class="text-sm text-zinc-500 sm:col-span-2">
                            {{ __('Aucune taxe en vigueur pour cette province. Ajoutez-les dans Comptabilité > Taxes.') }}
                        </flux:text>
                    @endforelse

                    <flux:field>
                        <flux:label>{{ __('Compte bancaire') }}</flux:label>
                        <flux:input wire:model="bankAccount" type="text" />
                        <flux:error name="bankAccount" />
                    </flux:field>
                </div>

                <div>
                    <div class="mb-4 flex items-center gap-1.5">
                        <flux:heading size="sm">{{ __('Début de l\'accumulation') }}</flux:heading>
                        <flux:tooltip :content="__('Jour de l\'année où commence l\'accumulation, sans année.')">
                            <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                        </flux:tooltip>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        @foreach (['vacation' => __('Vacances'), 'sick' => __('Maladie')] as $kind => $kindLabel)
                            <flux:field>
                                <flux:label>{{ $kindLabel }}</flux:label>
                                <div class="flex gap-2">
                                    <flux:select wire:model="{{ $kind }}AccrualMonth" class="flex-1">
                                        <flux:select.option value="">{{ __('Mois') }}</flux:select.option>
                                        @foreach ($months as $number => $monthName)
                                            <flux:select.option value="{{ $number }}">{{ $monthName }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:select wire:model="{{ $kind }}AccrualDay" class="w-24">
                                        <flux:select.option value="">{{ __('Jour') }}</flux:select.option>
                                        @foreach (range(1, 31) as $dayNumber)
                                            <flux:select.option value="{{ $dayNumber }}">{{ $dayNumber }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                <flux:error name="{{ $kind }}AccrualMonth" />
                                <flux:error name="{{ $kind }}AccrualDay" />
                            </flux:field>
                        @endforeach
                    </div>
                </div>

                <div>
                    <div class="mb-4 flex items-center gap-1.5">
                        <flux:heading size="sm">{{ __('Jours de maladie payés') }}</flux:heading>
                        <flux:tooltip :content="__('Maximum de jours de maladie payés disponibles par employé.')">
                            <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                        </flux:tooltip>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Temps plein') }}</flux:label>
                            <flux:input wire:model="sickDaysFullTime" type="number" step="1" min="0" max="365" />
                            <flux:error name="sickDaysFullTime" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Temps partiel') }}</flux:label>
                            <flux:input wire:model="sickDaysPartTime" type="number" step="1" min="0" max="365" />
                            <flux:error name="sickDaysPartTime" />
                        </flux:field>
                    </div>

                </div>

                <div>
                    <flux:heading size="sm" class="mb-4">{{ __('Ventes') }}</flux:heading>

                    <div class="grid max-w-lg gap-4 sm:grid-cols-2">
                        <flux:field>
                            <div class="flex items-center gap-1.5">
                                <flux:label>{{ __('Frais d\'annulation (%)') }}</flux:label>
                                <flux:tooltip :content="__('Frais facturés au client qui annule un article déjà commandé au fournisseur sans attendre sa confirmation (en % du prix vendant).')">
                                    <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                                </flux:tooltip>
                            </div>
                            <flux:input wire:model="cancellationFeePercent" type="number" step="0.01" min="0" max="100" />
                            <flux:error name="cancellationFeePercent" />
                        </flux:field>

                        <flux:field>
                            <div class="flex items-center gap-1.5">
                                <flux:label>{{ __('Dépôt sur mesure (%)') }}</flux:label>
                                <flux:tooltip :content="__('Exigé sur les articles sur mesure non encore remis.')">
                                    <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                                </flux:tooltip>
                            </div>
                            <flux:input wire:model="customDepositPercent" type="number" step="0.01" min="0" max="100" />
                            <flux:error name="customDepositPercent" />
                        </flux:field>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    @can('stores.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- Heures d'ouverture --}}
        <div x-show="tab === 'hours'" x-cloak>
            <form wire:submit="saveOpeningHours" class="mt-6 max-w-2xl space-y-4">
                @foreach (['monday' => __('Lundi'), 'tuesday' => __('Mardi'), 'wednesday' => __('Mercredi'), 'thursday' => __('Jeudi'), 'friday' => __('Vendredi'), 'saturday' => __('Samedi'), 'sunday' => __('Dimanche')] as $day => $dayLabel)
                    <div class="grid grid-cols-[8rem_1fr] items-start gap-4 sm:grid-cols-[8rem_6rem_1fr]" wire:key="hours-{{ $day }}">
                        <flux:checkbox wire:model.live="openingHours.{{ $day }}.open" :label="$dayLabel" />

                        @if ($openingHours[$day]['open'])
                            <div class="col-span-2 flex items-start gap-2 sm:col-span-2">
                                <flux:field>
                                    <flux:input wire:model="openingHours.{{ $day }}.from" type="time" />
                                    <flux:error name="openingHours.{{ $day }}.from" />
                                </flux:field>
                                <span class="pt-2 text-zinc-400">{{ __('à') }}</span>
                                <flux:field>
                                    <flux:input wire:model="openingHours.{{ $day }}.to" type="time" />
                                    <flux:error name="openingHours.{{ $day }}.to" />
                                </flux:field>
                            </div>
                        @else
                            <flux:text class="col-span-2 text-zinc-400">{{ __('Fermé') }}</flux:text>
                        @endif
                    </div>
                @endforeach

                <div class="flex justify-end pt-2">
                    @can('stores.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- Livraison --}}
        <div x-show="tab === 'delivery'" x-cloak>
            <div class="mt-6 max-w-3xl space-y-4">
                <div class="flex items-start justify-between gap-4">
                    <flux:text class="text-zinc-500">
                        {{ __('Chaque camion actif part avec un livreur à l\'horaire et fait au plus sa capacité de livraisons par demi-journée (AM ou PM).') }}
                        {{ trans_choice(':count livreur actif est rattaché à ce magasin.|:count livreurs actifs sont rattachés à ce magasin.', $driversCount) }}
                    </flux:text>
                    @can('stores.edit')
                        <flux:button variant="primary" icon="plus" wire:click="openTruckModal">{{ __('Ajouter un camion') }}</flux:button>
                    @endcan
                </div>

                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Camion') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Livraisons par demi-journée') }}</flux:table.column>
                            <flux:table.column />
                        </flux:table.columns>

                        <flux:table.rows>
                            @forelse ($this->getTrucks() as $truck)
                                <flux:table.row :key="'truck-'.$truck->id" @class(['opacity-50' => ! $truck->is_active])>
                                    <flux:table.cell variant="strong">
                                        {{ $truck->name }}
                                        @unless ($truck->is_active)
                                            <flux:badge size="sm" class="ms-2">{{ __('Inactif') }}</flux:badge>
                                        @endunless
                                    </flux:table.cell>
                                    <flux:table.cell align="end">{{ $truck->capacity_per_period }}</flux:table.cell>
                                    <flux:table.cell class="text-right">
                                        @can('stores.edit')
                                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openTruckModal({{ $truck->id }})" />
                                        @endcan
                                    </flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="3" class="py-12 text-center">
                                        <div class="flex flex-col items-center gap-2">
                                            <flux:icon name="truck" class="h-8 w-8 text-zinc-300" />
                                            <flux:text class="text-zinc-400">{{ __('Aucun camion : ce magasin ne peut pas planifier de livraison.') }}</flux:text>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </div>
        </div>

        {{-- Expédition --}}
        <div x-show="tab === 'shipping'" x-cloak>
            <form wire:submit="saveShippingLimits" class="mt-6 max-w-2xl space-y-4">
                <flux:text class="text-zinc-500">
                    {{ __('Taille d\'un article qui peut être expédié par un fournisseur d\'expédition. Un article plus grand, plus lourd ou sans dimensions est signalé à la demande d\'expédition, sans la bloquer. Laissez vide pour ne pas vérifier.') }}
                </flux:text>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <div class="flex items-center gap-1.5">
                            <flux:label>{{ __('Plus grande dimension maximale') }}</flux:label>
                            <flux:tooltip :content="__('Longueur, largeur ou hauteur, dans l\'unité des fiches produits.')">
                                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                            </flux:tooltip>
                        </div>
                        <flux:input wire:model="shippingMaxLength" inputmode="decimal" />
                        <flux:error name="shippingMaxLength" />
                    </flux:field>

                    <flux:field>
                        <div class="flex items-center gap-1.5">
                            <flux:label>{{ __('Poids maximal') }}</flux:label>
                            <flux:tooltip :content="__('Dans l\'unité des fiches produits.')">
                                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                            </flux:tooltip>
                        </div>
                        <flux:input wire:model="shippingMaxWeight" inputmode="decimal" />
                        <flux:error name="shippingMaxWeight" />
                    </flux:field>
                </div>

                <div class="flex justify-end pt-2">
                    @can('stores.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- Paramètres --}}
        <div x-show="tab === 'parameters'" x-cloak>
            <form wire:submit="saveParameters" class="mt-6 max-w-2xl space-y-6">
                <flux:field>
                    <flux:label>{{ __('Emplacement de l\'entrepôt') }}</flux:label>
                    <flux:select wire:model="warehouseStoreId">
                        <flux:select.option value="">{{ __('— Aucun —') }}</flux:select.option>
                        @foreach ($physicalStores as $physicalStore)
                            <flux:select.option value="{{ $physicalStore->id }}">{{ $physicalStore->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="warehouseStoreId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Entrepôt d\'expédition') }}</flux:label>
                    <flux:select wire:model="shippingWarehouseId">
                        <flux:select.option value="">{{ __('— Aucun —') }}</flux:select.option>
                        @foreach ($physicalStores as $physicalStore)
                            <flux:select.option value="{{ $physicalStore->id }}">{{ $physicalStore->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="shippingWarehouseId" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="isActive" :label="__('Actif')" />
                    <flux:error name="isActive" />
                </flux:field>

                <div class="flex justify-end pt-2">
                    @can('stores.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>
        @endcan

        @can('inventory.supervise')
            <div x-show="tab === 'locations'" x-cloak>
                <livewire:stores.locations :store="$store" />
            </div>
        @endcan
    </div>

    <flux:modal wire:model="showTruckModal" class="w-full max-w-md">
        <flux:heading>{{ $editingTruckId ? __('Modifier le camion') : __('Nouveau camion') }}</flux:heading>

        <form wire:submit="saveTruck" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="truckName" :placeholder="__('Camion 1, Cube 16 pi…')" required autofocus />
                <flux:error name="truckName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Livraisons par demi-journée') }}</flux:label>
                <flux:input wire:model="truckCapacity" type="number" min="1" max="50" required />
                <flux:error name="truckCapacity" />
            </flux:field>

            <flux:checkbox wire:model="truckIsActive" :label="__('Actif')" />

            <div class="flex justify-end gap-2 pt-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Annuler') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

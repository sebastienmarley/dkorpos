<div class="p-6">
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
        <div class="flex border-b border-zinc-200 dark:border-zinc-700">
            @foreach (['identification' => __('Identification'), 'accounting' => __('Comptabilité'), 'hours' => __('Heures d\'ouverture'), 'parameters' => __('Paramètres')] as $key => $label)
                <button
                    type="button"
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ $label }}</button>
            @endforeach
        </div>

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
                    <flux:field>
                        <flux:label>{{ __('Numéro de TPS') }}</flux:label>
                        <flux:input wire:model="gstNumber" type="text" />
                        <flux:error name="gstNumber" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Numéro de TVQ') }}</flux:label>
                        <flux:input wire:model="qstNumber" type="text" />
                        <flux:error name="qstNumber" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Compte bancaire') }}</flux:label>
                        <flux:input wire:model="bankAccount" type="text" />
                        <flux:error name="bankAccount" />
                    </flux:field>
                </div>

                <div>
                    <flux:heading size="sm" class="mb-1">{{ __('Début de l\'accumulation') }}</flux:heading>
                    <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Jour de l\'année où commence l\'accumulation, sans année.') }}</flux:text>

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
                    <flux:heading size="sm" class="mb-1">{{ __('Jours de maladie payés') }}</flux:heading>
                    <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Maximum de jours de maladie payés disponibles par employé.') }}</flux:text>

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
                    <flux:heading size="sm" class="mb-1">{{ __('Ventes') }}</flux:heading>
                    <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Frais facturés au client qui annule un article déjà commandé au fournisseur sans attendre sa confirmation (en % du prix vendant).') }}</flux:text>

                    <flux:field class="max-w-xs">
                        <flux:label>{{ __('Frais d\'annulation (%)') }}</flux:label>
                        <flux:input wire:model="cancellationFeePercent" type="number" step="0.01" min="0" max="100" />
                        <flux:error name="cancellationFeePercent" />
                    </flux:field>
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
    </div>
</div>

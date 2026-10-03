<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('suppliers.index')" wire:navigate size="sm">
            {{ __('Retour') }}
        </flux:button>

        <div class="min-w-0 flex-1">
            <flux:heading level="1" size="xl">{{ $supplier->name }}</flux:heading>
            <div class="mt-1 flex items-center gap-2">
                <flux:badge :color="$supplier->type->color()" size="sm">
                    {{ $supplier->type->label() }}
                </flux:badge>
                @if (! $supplier->is_active)
                    <flux:badge color="zinc" size="sm">{{ __('Inactif') }}</flux:badge>
                @endif
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('activeTab') }">
        <div class="flex border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                @click="tab = 'identification'"
                :class="tab === 'identification' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
            >{{ __('Identification') }}</button>

            <button
                type="button"
                @click="tab = 'accounting'"
                :class="tab === 'accounting' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
            >{{ __('Comptabilité') }}</button>

            @if ($supplier->type === \App\Enums\SupplierType::Product)
                <button
                    type="button"
                    @click="tab = 'transport'"
                    :class="tab === 'transport' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ __('Transport') }}</button>
            @endif

            @if ($supplier->type !== \App\Enums\SupplierType::Shipping)
                <button
                    type="button"
                    @click="tab = 'parameters'"
                    :class="tab === 'parameters' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ __('Info commande') }}</button>
            @endif
        </div>

        {{-- Identification --}}
        <div x-show="tab === 'identification'" x-cloak>
            <form wire:submit="saveIdentification" class="mt-6 max-w-2xl space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Type') }}</flux:label>
                        <flux:select wire:model="type">
                            @foreach ($this->getSupplierTypes() as $supplierType)
                                <flux:select.option :value="$supplierType->value">{{ $supplierType->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="type" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Nom') }}</flux:label>
                        <flux:input wire:model="name" type="text" required />
                        <flux:error name="name" />
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

                <flux:field>
                    <flux:checkbox wire:model="isActive" :label="__('Actif')" />
                    <flux:error name="isActive" />
                </flux:field>

                <div class="flex justify-end pt-2">
                    @can('suppliers.edit')
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
                        <flux:label>{{ __('Numéro de compte') }}</flux:label>
                        <flux:input wire:model="accountNumber" type="text" />
                        <flux:error name="accountNumber" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Compte bancaire') }}</flux:label>
                        <flux:input wire:model="bankAccount" type="text" />
                        <flux:error name="bankAccount" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Devise') }}</flux:label>
                        <flux:select wire:model="currencyId">
                            <flux:select.option value="">{{ __('— Aucune —') }}</flux:select.option>
                            @foreach ($currencies as $currency)
                                <flux:select.option value="{{ $currency->id }}">{{ $currency->code }} — {{ $currency->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="currencyId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Escompte paiement rapide (%)') }}</flux:label>
                        <flux:input wire:model="earlyPaymentDiscountPercent" type="number" step="0.01" min="0" max="100" />
                        <flux:error name="earlyPaymentDiscountPercent" />
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model.live="earlyPaymentNextMonth" :label="__('Mois suivant')" />
                        <flux:description>{{ __('Coché : l\'escompte est valide jusqu\'à un jour fixe du mois suivant la facturation (ex.: 2 % le 10 du mois suivant).') }}</flux:description>
                        <flux:error name="earlyPaymentNextMonth" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ $earlyPaymentNextMonth ? __('Jour du mois suivant') : __('Règle de l\'escompte (jours)') }}</flux:label>
                        <flux:input wire:model="earlyPaymentDiscountDays" type="number" step="1" min="{{ $earlyPaymentNextMonth ? 1 : 0 }}" max="{{ $earlyPaymentNextMonth ? 31 : 365 }}" placeholder="{{ $earlyPaymentNextMonth ? __('Ex.: le 10') : __('Ex.: 15 jours') }}" />
                        <flux:description>{{ $earlyPaymentNextMonth ? __('Jour du mois suivant limite pour profiter de l\'escompte.') : __('Délai pour profiter de l\'escompte à partir de la date de facturation.') }}</flux:description>
                        <flux:error name="earlyPaymentDiscountDays" />
                    </flux:field>
                </div>

                <div>
                    <div class="mb-4 flex items-center justify-between">
                        <flux:heading size="sm">{{ __('Adresse de paiement') }}</flux:heading>
                        <flux:checkbox
                            wire:model.live="sameAsMainAddress"
                            :label="__('Même que l\'adresse principale')"
                            class="text-sm"
                        />
                    </div>

                    <div @class(['pointer-events-none opacity-50' => $sameAsMainAddress])>
                        <x-address-input prefix="paymentAddress" :current-country="$paymentAddress['country'] ?? 'CA'" />
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    @can('suppliers.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        @if ($supplier->type === \App\Enums\SupplierType::Product)
            {{-- Transport --}}
            <div x-show="tab === 'transport'" x-cloak>
                <form wire:submit="saveTransport" class="mt-6 max-w-2xl space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Montant prepaid') }}</flux:label>
                        <flux:input wire:model="prepaidAmount" type="number" step="0.01" min="0" />
                        <flux:description>{{ __('Obligatoire sauf si le transport est en collect.') }}</flux:description>
                        <flux:error name="prepaidAmount" />
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model.live="collect" :label="__('Collect')" />
                        <flux:error name="collect" />
                    </flux:field>

                    @if ($collect)
                        <flux:field>
                            <flux:label>{{ __('Fournisseur d\'expédition par défaut') }}</flux:label>
                            <flux:select wire:model="defaultShippingSupplierId">
                                <flux:select.option value="">{{ __('— Aucun —') }}</flux:select.option>
                                @foreach ($shippingSuppliers as $shippingSupplier)
                                    <flux:select.option value="{{ $shippingSupplier->id }}">{{ $shippingSupplier->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="defaultShippingSupplierId" />
                        </flux:field>
                    @endif

                    <div class="flex justify-end pt-2">
                        @can('suppliers.edit')
                            <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                        @endcan
                    </div>
                </form>
            </div>
        @endif

        @if ($supplier->type !== \App\Enums\SupplierType::Shipping)
            {{-- Info commande --}}
            <div x-show="tab === 'parameters'" x-cloak>
                <form wire:submit="saveParameters" class="mt-6 max-w-2xl space-y-4">
                    <flux:field>
                        <flux:label>{{ __('Courriel de commande') }}</flux:label>
                        <flux:input wire:model="orderEmail" type="email" />
                        <flux:error name="orderEmail" />
                    </flux:field>

                    <div>
                        <flux:heading size="sm" class="mb-4">{{ __('Multiplicateur de prix') }}</flux:heading>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Base') }}</flux:label>
                                <flux:input wire:model.live.debounce.300ms="baseMultiplier" type="number" step="0.0001" min="0" />
                                <flux:error name="baseMultiplier" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Taux de change') }}</flux:label>
                                <flux:input :value="$supplier->exchangeRate()" type="number" disabled />
                                <flux:description>{{ $supplier->currency ? __('Taux de la devise :code.', ['code' => $supplier->currency->code]) : __('Aucune devise sélectionnée (Comptabilité).') }}</flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Frais de douanes') }}</flux:label>
                                <flux:input wire:model.live.debounce.300ms="customsFee" type="number" step="0.0001" min="0" />
                                <flux:error name="customsFee" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Frais de transport') }}</flux:label>
                                <flux:input wire:model.live.debounce.300ms="shippingFee" type="number" step="0.0001" min="0" />
                                <flux:error name="shippingFee" />
                            </flux:field>
                        </div>
                        <flux:text class="mt-3">
                            {{ __('Multiplicateur calculé') }} : <strong>{{ number_format($this->computedMultiplier, 4) }}</strong>
                            — {{ __('le prix de vente est calculé en multipliant le coût par ce facteur.') }}
                        </flux:text>
                    </div>

                    <flux:field>
                        <flux:checkbox wire:model="orderable" :label="__('Commandable')" :disabled="! $supplier->is_active" />
                        <flux:error name="orderable" />
                    </flux:field>

                    <div class="flex justify-end pt-2">
                        @can('suppliers.edit')
                            <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                        @endcan
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>

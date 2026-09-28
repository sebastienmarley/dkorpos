<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('suppliers.index')" wire:navigate size="sm">
            {{ __('Retour') }}
        </flux:button>

        <div class="min-w-0 flex-1">
            <flux:heading level="1" size="xl">{{ $supplier->name }}</flux:heading>
            <div class="mt-1 flex items-center gap-2">
                <flux:badge :color="$supplier->type->value === 'service' ? 'blue' : 'green'" size="sm">
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

            <button
                type="button"
                @click="tab = 'parameters'"
                :class="tab === 'parameters' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
            >{{ __('Paramètres') }}</button>
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

                <div class="flex justify-end pt-2">
                    <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
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
                    <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                </div>
            </form>
        </div>

        {{-- Paramètres --}}
        <div x-show="tab === 'parameters'" x-cloak>
            <form wire:submit="saveParameters" class="mt-6 max-w-2xl space-y-4">
                <flux:field>
                    <flux:label>{{ __('Courriel de commande') }}</flux:label>
                    <flux:input wire:model="orderEmail" type="email" />
                    <flux:error name="orderEmail" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Multiplicateur de prix') }}</flux:label>
                    <flux:input wire:model="priceMultiplier" type="number" step="0.0001" min="0.0001" />
                    <flux:description>{{ __('Le prix de vente est calculé en multipliant le coût par ce facteur.') }}</flux:description>
                    <flux:error name="priceMultiplier" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="orderable" :label="__('Commandable')" />
                    <flux:error name="orderable" />
                </flux:field>

                <flux:field>
                    <flux:checkbox wire:model="isActive" :label="__('Actif')" />
                    <flux:error name="isActive" />
                </flux:field>

                <div class="flex justify-end pt-2">
                    <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</div>

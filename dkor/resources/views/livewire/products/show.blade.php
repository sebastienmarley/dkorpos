<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('products.index')" wire:navigate size="sm">
                {{ __('Retour') }}
            </flux:button>

            <div class="min-w-0">
                <flux:heading level="1" size="xl">{{ $product->model }}</flux:heading>
                <div class="mt-1 flex items-center gap-2">
                    <flux:text class="text-zinc-500">{{ $product->supplier->name }}</flux:text>
                    @if ($product->is_discontinued)
                        <flux:badge color="red" size="sm">{{ __('Discontinué') }}</flux:badge>
                    @endif
                    @if ($product->is_non_orderable)
                        <flux:badge color="zinc" size="sm">{{ __('Non commandable') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>

        {{-- Prix de vente calculé --}}
        <div class="flex-shrink-0 rounded-xl border border-zinc-200 bg-zinc-50 px-5 py-3 text-right dark:border-zinc-700 dark:bg-zinc-800">
            <flux:text class="text-xs text-zinc-400">{{ __('Prix de vente') }}</flux:text>
            <div class="mt-0.5 text-2xl font-semibold text-zinc-900 dark:text-white">
                @php $sp = $this->getSellingPrice(); @endphp
                {{ number_format($sp, fmod($sp, 1.0) > 0 ? 2 : 0) }} $
            </div>
        </div>
    </div>

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('activeTab') }">
        <div class="flex border-b border-zinc-200 dark:border-zinc-700">
            <button
                type="button"
                @click="tab = 'general'"
                :class="tab === 'general' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
            >{{ __('Général') }}</button>

            <button
                type="button"
                @click="tab = 'description'"
                :class="tab === 'description' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
            >{{ __('Description') }}</button>

            <button
                type="button"
                @click="tab = 'photos'"
                :class="tab === 'photos' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
            >{{ __('Photos') }}</button>
        </div>

        {{-- Général --}}
        <div x-show="tab === 'general'" x-cloak>
            <form wire:submit="saveGeneral" class="mt-6 max-w-2xl space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Fournisseur') }}</flux:label>
                        <flux:select wire:model.live="supplierId">
                            <flux:select.option value="">{{ __('Sélectionner…') }}</flux:select.option>
                            @foreach ($this->getSuppliers() as $supplier)
                                <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="supplierId" />
                    </flux:field>

                    <flux:field>
                        <div class="flex items-center gap-1.5">
                            <flux:label>{{ __('Modèle fournisseur') }}</flux:label>
                            <flux:tooltip content="{{ __('Référence utilisée par le fournisseur.') }}">
                                <flux:icon name="information-circle" class="h-4 w-4 text-zinc-400" />
                            </flux:tooltip>
                        </div>
                        <flux:input wire:model="supplierModel" type="text" />
                        <flux:error name="supplierModel" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Modèle') }}</flux:label>
                    <flux:input wire:model="model" type="text" required />
                    <input type="hidden" wire:model="cleanModel" />
                    <flux:error name="model" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Département') }}</flux:label>
                        <flux:select wire:model.live="departmentId">
                            <flux:select.option value="">{{ __('Aucun') }}</flux:select.option>
                            @foreach ($this->getDepartments() as $department)
                                <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="departmentId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Catégorie') }}</flux:label>
                        <flux:select wire:model="categoryId">
                            <flux:select.option value="">{{ __('Aucune') }}</flux:select.option>
                            @foreach ($this->getCategories() as $category)
                                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="categoryId" />
                    </flux:field>
                </div>

                <x-cost-input wire:model.live="cost" label="{{ __('Coût') }}" name="cost" />

                <div class="flex gap-6">
                    <flux:field>
                        <flux:checkbox wire:model="isDiscontinued" :label="__('Discontinué')" />
                        <flux:error name="isDiscontinued" />
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model="isNonOrderable" :label="__('Non commandable')" />
                        <flux:error name="isNonOrderable" />
                    </flux:field>
                </div>

                <div class="flex justify-end pt-2">
                    @can('products.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- Description --}}
        <div x-show="tab === 'description'" x-cloak>
            <form wire:submit="saveDescription" class="mt-6 max-w-2xl space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Couleur') }}</flux:label>
                        <flux:select wire:model="colorId">
                            <flux:select.option value="">{{ __('Aucune') }}</flux:select.option>
                            @foreach ($this->getColors() as $color)
                                <flux:select.option :value="$color->id">
                                    {{ $color->name }}{{ $color->hex_code ? ' ('.$color->hex_code.')' : '' }}
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="colorId" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Collection') }}</flux:label>
                        <flux:input wire:model="collection" type="text" />
                        <flux:error name="collection" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" rows="8" />
                    <flux:error name="description" />
                </flux:field>

                <div>
                    <flux:heading size="sm" class="mb-4">{{ __('Dimensions') }}</flux:heading>
                    <div class="grid gap-4 sm:grid-cols-4">
                        <flux:field>
                            <flux:label>{{ __('Longueur') }}</flux:label>
                            <flux:input wire:model="length" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="length" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Largeur') }}</flux:label>
                            <flux:input wire:model="width" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="width" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Hauteur') }}</flux:label>
                            <flux:input wire:model="height" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="height" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Poids') }}</flux:label>
                            <flux:input wire:model="weight" type="number" step="0.01" min="0" placeholder="0.00" />
                            <flux:error name="weight" />
                        </flux:field>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    @can('products.edit')
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    @endcan
                </div>
            </form>
        </div>

        {{-- Photos --}}
        <div x-show="tab === 'photos'" x-cloak>
            <div class="mt-6 max-w-2xl">
                <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 bg-zinc-50 py-16 dark:border-zinc-600 dark:bg-zinc-800/50">
                    <flux:icon name="photo" class="mb-3 h-12 w-12 text-zinc-300 dark:text-zinc-600" />
                    <flux:heading level="3" class="text-zinc-500">{{ __('Gestion des photos') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-400">{{ __('La gestion des images sera disponible prochainement.') }}</flux:text>
                </div>
            </div>
        </div>
    </div>
</div>

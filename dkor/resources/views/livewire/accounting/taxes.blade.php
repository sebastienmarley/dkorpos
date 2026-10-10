<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Taxes') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Taxes par province et historique des taux') }}</flux:text>
        </div>
        @can('taxes.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Ajouter') }}
            </flux:button>
        @endcan
    </div>

    {{-- Filtres --}}
    <div class="mb-4 flex flex-wrap items-end gap-4">
        <flux:field class="w-64">
            <flux:label>{{ __('Province') }}</flux:label>
            <flux:select wire:model.live="filterProvince">
                <flux:select.option value="">{{ __('Toutes') }}</flux:select.option>
                @foreach ($provinces as $provinceOption)
                    <flux:select.option :value="$provinceOption->value">{{ $provinceOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field class="w-52">
            <flux:label>{{ __('En vigueur le') }}</flux:label>
            <flux:input wire:model.live="filterDate" type="date" />
        </flux:field>

        @if ($filterDate !== '' || $filterProvince !== '')
            <flux:button variant="ghost" wire:click="$set('filterDate', ''); $set('filterProvince', '')">{{ __('Réinitialiser') }}</flux:button>
        @endif
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Province') }}</flux:table.column>
                <flux:table.column>{{ __('Taxe') }}</flux:table.column>
                <flux:table.column>{{ __('Taux') }}</flux:table.column>
                <flux:table.column>{{ __('Application') }}</flux:table.column>
                <flux:table.column>{{ __('Début') }}</flux:table.column>
                <flux:table.column>{{ __('Fin') }}</flux:table.column>
                <flux:table.column>{{ __('Statut') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($taxes as $tax)
                    @php
                        $status = $tax->end_date->lt($today) ? 'expired' : ($tax->start_date->gt($today) ? 'upcoming' : 'active');
                    @endphp
                    <flux:table.row :key="$tax->id" @class(['opacity-50' => $status === 'expired'])>
                        <flux:table.cell>{{ $tax->province->label() }}</flux:table.cell>
                        <flux:table.cell variant="strong">{{ $tax->name }}</flux:table.cell>
                        <flux:table.cell>{{ rtrim(rtrim($tax->rate, '0'), '.') }} %</flux:table.cell>
                        <flux:table.cell>{{ $tax->is_compound ? __('En cascade') : __('Individuelle') }}</flux:table.cell>
                        <flux:table.cell>{{ $tax->start_date->toDateString() }}</flux:table.cell>
                        <flux:table.cell>{{ $tax->end_date->toDateString() }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($status === 'expired')
                                <flux:badge size="sm">{{ __('Expirée') }}</flux:badge>
                            @elseif ($status === 'upcoming')
                                <flux:badge size="sm" color="blue">{{ __('À venir') }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="green">{{ __('En vigueur') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            @if ($status !== 'expired' && auth()->user()->can('taxes.edit'))
                                <flux:button variant="ghost" size="sm" icon="archive-box" :title="__('Expirer')" wire:click="openExpire({{ $tax->id }})" />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="8" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="receipt-percent" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune taxe.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:text class="mt-3 text-sm text-zinc-400">
        {{ __('Le taux d\'une taxe sauvegardée ne peut pas être modifié ni supprimé : faites-la expirer, puis créez-en une nouvelle. Une taxe « en cascade » se calcule sur le montant plus les taxes individuelles.') }}
    </flux:text>

    {{-- Nouvelle taxe --}}
    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <flux:heading class="mb-1">{{ __('Nouvelle taxe') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Province') }}</flux:label>
                <flux:select wire:model="province">
                    @foreach ($provinces as $provinceOption)
                        <flux:select.option :value="$provinceOption->value">{{ $provinceOption->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="province" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model="name" type="text" placeholder="TPS" required autofocus />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Taux (%)') }}</flux:label>
                    <flux:input wire:model="rate" type="number" step="0.001" min="0" max="100" placeholder="5" required />
                    <flux:error name="rate" />
                </flux:field>
            </div>

            <flux:field>
                <flux:checkbox wire:model="isCompound" :label="__('En cascade (calculée sur le montant plus les taxes individuelles)')" />
                <flux:error name="isCompound" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Date de début') }}</flux:label>
                    <flux:input wire:model="startDate" type="date" required />
                    <flux:error name="startDate" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date de fin') }}</flux:label>
                    <flux:input wire:model="endDate" type="date" required />
                    <flux:error name="endDate" />
                </flux:field>
            </div>

            <flux:text class="text-sm text-zinc-500">{{ __('Une fois sauvegardé, le taux ne pourra plus être modifié.') }}</flux:text>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Expiration --}}
    <flux:modal wire:model="showExpireModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ __('Faire expirer la taxe') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500">{{ __('La taxe s\'applique jusqu\'à cette date incluse. Créez ensuite la nouvelle taxe avec le lendemain comme début.') }}</flux:text>

        <form wire:submit="expire" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Dernier jour de la taxe') }}</flux:label>
                <flux:input wire:model="expireDate" type="date" required />
                <flux:error name="expireDate" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showExpireModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Expirer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

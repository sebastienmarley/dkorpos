<div class="p-6">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button variant="ghost" icon="arrow-left" :href="route('catalog.price-lists')" wire:navigate size="sm">
                {{ __('Retour') }}
            </flux:button>

            <div class="min-w-0">
                <flux:heading level="1" size="xl">{{ $priceList->supplier->name }}</flux:heading>
                <div class="mt-1">
                    @if ($priceList->isActive())
                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">{{ __('Archivée') }}</flux:badge>
                    @endif
                </div>
            </div>
        </div>

        @can('price_lists.edit')
            @if ($priceList->isActive())
                <flux:button variant="danger" icon="archive-box" wire:click="archive" wire:confirm="{{ __('Archiver cette liste de prix ?') }}">
                    {{ __('Archiver') }}
                </flux:button>
            @endif
        @endcan
    </div>

    <form wire:submit="save" class="max-w-xl space-y-4">
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>{{ __('Date de début') }}</flux:label>
                <flux:input wire:model="startsOn" type="date" required :disabled="! $priceList->isActive() || $startDateLocked" />
                @if ($startDateLocked && $priceList->isActive())
                    <flux:description>{{ __('La liste est en cours : la date de début est verrouillée.') }}</flux:description>
                @endif
                <flux:error name="startsOn" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Date de fin') }}</flux:label>
                <flux:input wire:model="endsOn" type="date" required :disabled="! $priceList->isActive()" />
                <flux:error name="endsOn" />
            </flux:field>
        </div>

        @can('price_lists.edit')
            @if ($priceList->isActive())
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                </div>
            @endif
        @endcan
    </form>

    <div class="mt-10 mb-3 flex max-w-xl items-center justify-between">
        <flux:heading size="lg">{{ __('Listes') }}</flux:heading>
        @can('price_lists.edit')
            @if ($priceList->isActive())
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openAddList">{{ __('Ajouter une liste') }}</flux:button>
            @endif
        @endcan
    </div>

    <div class="max-w-xl divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
        @forelse ($lists as $list)
            <div class="flex items-center justify-between gap-4 px-4 py-3" wire:key="list-{{ $list->id }}">
                <div class="min-w-0">
                    <div class="font-medium text-zinc-900 dark:text-white">{{ $list->name }}</div>
                    <flux:text class="text-sm text-zinc-500">
                        {{ trans_choice(':count produit|:count produits', $list->items_count) }}
                        @if ($list->discount_percent > 0)
                            · {{ __('Escompte') }} {{ rtrim(rtrim(number_format($list->discount_percent, 2), '0'), '.') }} %
                        @endif
                    </flux:text>
                </div>
                @can('price_lists.edit')
                    @if ($priceList->isActive())
                        <div class="flex items-center gap-2">
                            <flux:button size="sm" icon="arrow-up-tray" wire:click="openImport({{ $list->id }})">{{ __('Importer') }}</flux:button>
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteList({{ $list->id }})" wire:confirm="{{ __('Supprimer cette liste et tous ses produits importés ?') }}" />
                        </div>
                    @endif
                @endcan
            </div>
        @empty
            <div class="px-4 py-8 text-center">
                <flux:text class="text-zinc-400">{{ __('Aucune liste.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal wire:model="showListModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ __('Nouvelle liste') }}</flux:heading>

        <form wire:submit="addList" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="listName" type="text" required autofocus />
                <flux:error name="listName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Escompte (%)') }}</flux:label>
                <flux:input wire:model="listDiscount" type="number" step="0.01" min="0" max="100" />
                <flux:description>{{ __('Appliqué sur le coût des produits mis à jour à l\'importation.') }}</flux:description>
                <flux:error name="listDiscount" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showListModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showImportModal" class="w-full max-w-md">
        <flux:heading class="mb-1">{{ __('Importer un fichier CSV') }}</flux:heading>
        <flux:text class="text-sm text-zinc-500">
            Modele;cout;IMAP;UPC;collection;description;longueur;largeur;hauteur;poids
        </flux:text>

        <form wire:submit="import" class="mt-6 space-y-4">
            <flux:field>
                <input type="file" wire:model="file" accept=".csv,.txt" class="block w-full text-sm" />
                <flux:error name="file" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showImportModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="file,import">{{ __('Importer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    @if ($history->isNotEmpty())
        <flux:heading size="lg" class="mt-10 mb-3">{{ __('Autres listes du fournisseur') }}</flux:heading>
        <div class="max-w-xl overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Début') }}</flux:table.column>
                    <flux:table.column>{{ __('Fin') }}</flux:table.column>
                    <flux:table.column>{{ __('Statut') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($history as $list)
                        <flux:table.row :key="$list->id">
                            <flux:table.cell>
                                <a href="{{ route('catalog.price-lists.show', $list) }}" wire:navigate class="hover:underline">{{ $list->starts_on->toDateString() }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $list->ends_on->toDateString() }}</flux:table.cell>
                            <flux:table.cell>{{ $list->isActive() ? __('Active') : __('Archivée') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>

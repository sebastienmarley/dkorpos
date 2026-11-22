<div class="sm:p-6">
    @php
        $canEditList = auth()->user()->can('price_lists.edit') && $priceList->isActive();
        $card = 'rounded-xl border border-zinc-200 bg-white p-4 shadow-sm sm:p-6 dark:border-zinc-700 dark:bg-zinc-900';
    @endphp

    {{-- En-tête --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3">
            <flux:button variant="ghost" icon="arrow-left" :href="route('catalog.price-lists')" wire:navigate size="sm" :aria-label="__('Retour')" />

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading level="1" size="xl">{{ $priceList->supplier->name }}</flux:heading>
                    @if (! $priceList->isActive())
                        <flux:badge color="zinc" size="sm">{{ __('Archivée') }}</flux:badge>
                    @elseif ($priceList->isUpcoming())
                        <flux:badge color="blue" size="sm">{{ __('À venir') }}</flux:badge>
                    @else
                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                    @endif
                </div>
                <flux:text class="mt-1 text-zinc-500">{{ __('Liste de prix') }}</flux:text>
            </div>
        </div>

        @if ($canEditList)
            <flux:button icon="archive-box" wire:click="archive" wire:confirm="{{ __('Archiver cette liste de prix ? Ses listes ne pourront plus être modifiées ni importées.') }}">
                {{ __('Archiver') }}
            </flux:button>
        @endif
    </div>

    <div class="space-y-6">
        {{-- Période --}}
        <form wire:submit="save" class="{{ $card }}">
            <flux:heading size="lg" class="mb-4">{{ __('Période') }}</flux:heading>

            <div class="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto]">
                <flux:field>
                    <flux:label class="gap-1.5">
                        {{ __('Date de début') }}
                        @if ($startDateLocked && $priceList->isActive())
                            <flux:tooltip :content="__('La liste est en cours : la date de début est verrouillée.')">
                                <flux:icon name="lock-closed" variant="mini" class="text-zinc-400" />
                            </flux:tooltip>
                        @endif
                    </flux:label>
                    <flux:input wire:model="startsOn" type="date" required :disabled="! $priceList->isActive() || $startDateLocked" />
                    <flux:error name="startsOn" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date de fin') }}</flux:label>
                    <flux:input wire:model="endsOn" type="date" required :disabled="! $priceList->isActive()" />
                    <flux:error name="endsOn" />
                </flux:field>

                @if ($canEditList)
                    <div class="flex justify-end sm:col-span-2 lg:col-span-1 lg:pt-7">
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder les dates') }}</flux:button>
                    </div>
                @endif
            </div>
        </form>

        {{-- Listes du fournisseur pour cette période : chacune reçoit un fichier CSV. --}}
        <section class="{{ $card }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1.5">
                    <flux:heading size="lg">{{ __('Listes') }}</flux:heading>
                    <flux:tooltip :content="__('Chaque liste reçoit un fichier CSV du fournisseur. Un modèle ne peut être que dans une seule liste.')">
                        <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                    </flux:tooltip>
                </div>
                @if ($canEditList)
                    <flux:button size="sm" variant="primary" icon="plus" wire:click="openAddList">{{ __('Ajouter une liste') }}</flux:button>
                @endif
            </div>

            @if ($lists->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Nom') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Produits') }}</flux:table.column>
                        <flux:table.column align="end" class="max-sm:hidden">{{ __('Escompte') }}</flux:table.column>
                        @if ($canEditList)
                            <flux:table.column />
                        @endif
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($lists as $list)
                            <flux:table.row :key="'list-'.$list->id">
                                <flux:table.cell variant="strong">{{ $list->name }}</flux:table.cell>
                                <flux:table.cell align="end" @class(['tabular-nums', 'text-zinc-400' => $list->items_count === 0])>{{ number_format($list->items_count, 0, ',', ' ') }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums max-sm:hidden">
                                    {{ $list->discount_percent > 0 ? rtrim(rtrim(number_format($list->discount_percent, 2, ',', ' '), '0'), ',').' %' : '—' }}
                                </flux:table.cell>
                                @if ($canEditList)
                                    <flux:table.cell align="end">
                                        <div class="flex items-center justify-end gap-1">
                                            <flux:button size="sm" icon="arrow-up-tray" wire:click="openImport({{ $list->id }})">
                                                {{ $list->items_count > 0 ? __('Remplacer') : __('Importer') }}
                                            </flux:button>
                                            <flux:tooltip :content="__('Supprimer la liste')">
                                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteList({{ $list->id }})" wire:confirm="{{ __('Supprimer cette liste et tous ses produits importés ?') }}" :aria-label="__('Supprimer la liste')" />
                                            </flux:tooltip>
                                        </div>
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <div class="flex flex-col items-center gap-2 py-8">
                    <flux:icon name="document-text" class="h-8 w-8 text-zinc-300" />
                    <flux:text class="text-zinc-400">{{ __('Aucune liste.') }}</flux:text>
                </div>
            @endif
        </section>

        @if ($history->isNotEmpty())
            <section class="{{ $card }}">
                <flux:heading size="lg" class="mb-4">{{ __('Autres listes de prix du fournisseur') }}</flux:heading>

                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Début') }}</flux:table.column>
                        <flux:table.column>{{ __('Fin') }}</flux:table.column>
                        <flux:table.column>{{ __('Statut') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($history as $other)
                            <x-table.row-link :key="'history-'.$other->id" :href="route('catalog.price-lists.show', $other)">
                                <flux:table.cell class="tabular-nums">
                                    <a href="{{ route('catalog.price-lists.show', $other) }}" wire:navigate>{{ $other->starts_on->toDateString() }}</a>
                                </flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $other->ends_on->toDateString() }}</flux:table.cell>
                                <flux:table.cell>
                                    @if (! $other->isActive())
                                        <flux:badge color="zinc" size="sm">{{ __('Archivée') }}</flux:badge>
                                    @elseif ($other->isUpcoming())
                                        <flux:badge color="blue" size="sm">{{ __('À venir') }}</flux:badge>
                                    @else
                                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                            </x-table.row-link>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </section>
        @endif
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
                <flux:label class="gap-1.5">
                    {{ __('Escompte (%)') }}
                    <flux:tooltip :content="__('Appliqué sur le coût des produits mis à jour à l\'importation.')">
                        <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
                    </flux:tooltip>
                </flux:label>
                <flux:input wire:model="listDiscount" type="number" step="0.01" min="0" max="100" />
                <flux:error name="listDiscount" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showListModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showImportModal" class="w-full max-w-md">
        @php
            $importingList = $lists->firstWhere('id', $importingId);
        @endphp
        <div class="flex items-center gap-1.5">
            <flux:heading>{{ __('Importer un fichier CSV') }}</flux:heading>
            <flux:tooltip :content="__('Le fichier remplace les produits déjà importés dans cette liste.')">
                <flux:icon name="information-circle" variant="mini" class="text-zinc-400" />
            </flux:tooltip>
        </div>
        @if ($importingList)
            <flux:text class="mt-1 text-zinc-500">{{ $importingList->name }}</flux:text>
        @endif

        <form wire:submit="import" class="mt-6 space-y-4">
            <flux:field>
                <input type="file" wire:model="file" accept=".csv,.txt" class="block w-full cursor-pointer rounded-lg border border-zinc-200 text-sm text-zinc-600 file:me-3 file:cursor-pointer file:border-0 file:border-e file:border-zinc-200 file:bg-zinc-100 file:px-4 file:py-2 file:font-medium file:text-zinc-800 hover:file:bg-zinc-200 dark:border-zinc-600 dark:text-zinc-300 dark:file:border-zinc-600 dark:file:bg-zinc-700 dark:file:text-white dark:hover:file:bg-zinc-600" />
                <flux:error name="file" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showImportModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="file,import">{{ __('Importer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

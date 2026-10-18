<div class="p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('inventory.counts.show', $count)" wire:navigate class="text-sm">← {{ __('Prise d\'inventaire du :date', ['date' => $count->counted_on->format('Y-m-d')]) }}</flux:link>
            <flux:heading level="1" size="xl" class="mt-1">{{ __('Feuilles de compte') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Chaque portée se compte deux fois (A et B) par deux personnes différentes. Les produits en désaccord sont recomptés par une troisième.') }}</flux:text>
        </div>

        @if ($count->isInProgress())
            <flux:button variant="primary" icon="document-duplicate" wire:click="prepare">{{ __('Préparer les feuilles') }}</flux:button>
        @endif
    </div>

    @if ($unknownScans->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6">
            <flux:callout.heading>{{ trans_choice(':count code inconnu à associer|:count codes inconnus à associer', $unknownScans->count()) }}</flux:callout.heading>
            <flux:callout.text>
                <div class="mt-2 space-y-1">
                    @foreach ($unknownScans as $scan)
                        <div class="flex items-center gap-3" wire:key="unknown-{{ $scan->id }}">
                            <span class="font-mono">{{ $scan->code }}</span>
                            <span class="text-zinc-500">× {{ $scan->quantity }} · {{ $scan->sheet->areaLabel() }} ({{ $scan->sheet->pass->label() }})</span>
                            @if ($count->isInProgress())
                                <flux:button size="xs" wire:click="openAssociate({{ $scan->id }})">{{ __('Associer') }}</flux:button>
                            @endif
                        </div>
                    @endforeach
                </div>
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="space-y-6">
        @forelse ($scopes as $scopeKey => $scope)
            <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900" wire:key="scope-{{ $scopeKey }}">
                <div class="mb-3 flex items-center justify-between">
                    <flux:heading size="lg">{{ $scope['label'] }}</flux:heading>
                    @if ($scope['reconciled'])
                        <flux:badge color="green">{{ __('Réconciliée') }}</flux:badge>
                    @endif
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach ($scope['sheets'] as $sheet)
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700" wire:key="sheet-{{ $sheet->id }}">
                            <div class="mb-2 flex items-center justify-between">
                                <flux:text class="font-medium">{{ $sheet->pass->label() }}</flux:text>
                                <flux:badge size="sm" :color="$sheet->state->color()">{{ $sheet->state->label() }}</flux:badge>
                            </div>
                            @if ($count->isInProgress() && ! $sheet->isDone())
                                <flux:select size="sm" wire:model.live="assignments.{{ $sheet->id }}">
                                    <flux:select.option value="">{{ __('Attribuer à…') }}</flux:select.option>
                                    @foreach ($counters as $counter)
                                        <flux:select.option :value="$counter->id">{{ $counter->fullName() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:text class="text-sm">{{ $sheet->assignee?->fullName() ?? '—' }}</flux:text>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($scope['comparison'] !== null)
                    @php($disagreements = collect($scope['comparison'])->reject(fn ($row) => $row['agreed']))
                    <flux:text class="mt-4 text-sm text-zinc-500">
                        {{ trans_choice(':count produit concorde|:count produits concordent', count($scope['comparison']) - $disagreements->count()) }}
                        · {{ trans_choice(':count en désaccord|:count en désaccord', $disagreements->count()) }}
                    </flux:text>

                    @if ($disagreements->isNotEmpty())
                        <div class="mt-2 overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                            <flux:table>
                                <flux:table.columns>
                                    <flux:table.column>{{ __('Produit') }}</flux:table.column>
                                    <flux:table.column align="end">{{ __('A') }}</flux:table.column>
                                    <flux:table.column align="end">{{ __('B') }}</flux:table.column>
                                    <flux:table.column align="end">{{ __('Recompte') }}</flux:table.column>
                                </flux:table.columns>
                                <flux:table.rows>
                                    @foreach ($disagreements as $row)
                                        <flux:table.row :key="$scopeKey.'-'.$row['product_id']">
                                            <flux:table.cell>{{ $products[$row['product_id']]?->display_name }}</flux:table.cell>
                                            <flux:table.cell align="end">{{ $row['a'] }}</flux:table.cell>
                                            <flux:table.cell align="end">{{ $row['b'] }}</flux:table.cell>
                                            <flux:table.cell align="end">{{ $row['recount'] ?? '—' }}</flux:table.cell>
                                        </flux:table.row>
                                    @endforeach
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    @endif

                    @if ($count->isInProgress() && ! $scope['reconciled'])
                        <div class="mt-4 flex flex-wrap items-end gap-3">
                            @if ($disagreements->isNotEmpty() && ! $scope['hasRecount'])
                                <div class="w-64">
                                    <flux:select size="sm" wire:model="recountCounters.{{ $scopeKey }}" :label="__('Compteur du recompte')">
                                        <flux:select.option value="">{{ __('Choisir…') }}</flux:select.option>
                                        @foreach ($counters as $counter)
                                            <flux:select.option :value="$counter->id">{{ $counter->fullName() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                <flux:button size="sm" wire:click="createRecount('{{ $scopeKey }}')">{{ __('Créer le recompte') }}</flux:button>
                            @endif
                            @if ($scope['hasRecount'])
                                <flux:checkbox wire:model="acceptRecount.{{ $scopeKey }}" :label="__('Retenir le recompte même s\'il ne concorde ni avec A ni avec B')" />
                            @endif
                            <flux:button size="sm" variant="primary" wire:click="reconcile('{{ $scopeKey }}')">{{ __('Réconcilier') }}</flux:button>
                        </div>
                    @endif
                @else
                    <flux:text class="mt-4 text-sm text-zinc-400">{{ __('La comparaison s\'affiche quand les comptes A et B sont terminés.') }}</flux:text>
                @endif
            </div>
        @empty
            <div class="rounded-xl border border-zinc-200 p-12 text-center dark:border-zinc-700">
                <flux:text class="text-zinc-400">{{ __('Aucune feuille : préparez-les pour compter par comptes doubles. Le stock a une feuille par zone active.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal wire:model="showAssociate" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Associer le code à un produit') }}</flux:heading>
        <flux:text class="text-zinc-500">{{ __('Le code sera ajouté aux codes UPC du produit, et sa quantité à la feuille.') }}</flux:text>

        <div class="mt-6 space-y-4">
            <flux:input wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Rechercher un produit…')" />

            <div class="max-h-72 divide-y divide-zinc-200 overflow-y-auto rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                @forelse ($productResults as $result)
                    <button type="button" wire:key="associate-{{ $result->id }}" wire:click="associate({{ $result->id }})"
                        class="flex w-full items-center justify-between px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800">
                        <span>{{ $result->display_name }}</span>
                    </button>
                @empty
                    <div class="p-4 text-center"><flux:text class="text-zinc-400">{{ __('Aucun produit trouvé.') }}</flux:text></div>
                @endforelse
            </div>
        </div>
    </flux:modal>
</div>

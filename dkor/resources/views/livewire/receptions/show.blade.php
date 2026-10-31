@php
    $inProgress = $reception->isInProgress();
@endphp

<div class="sm:p-6">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:link :href="route('receptions.index')" wire:navigate class="text-sm">← {{ __('Réceptions') }}</flux:link>
            <div class="mt-1 flex items-center gap-3">
                <flux:heading level="1" size="xl">{{ $reception->number }}</flux:heading>
                <flux:badge :color="$reception->status->color()" size="sm">{{ $reception->status->label() }}</flux:badge>
            </div>
            <flux:text class="mt-1 text-zinc-500">
                <flux:link :href="route('suppliers.show', $reception->supplier)" wire:navigate>{{ $reception->supplier->name }}</flux:link>
                · {{ ($inProgress ? $reception->received_at : ($reception->completed_at ?? $reception->received_at))->format('Y-m-d H:i') }}
                @if ($reception->receiver)
                    · {{ __('par :name', ['name' => $reception->receiver->firstname.' '.$reception->receiver->lastname]) }}
                @endif
                @if (! $inProgress && $reception->reference)
                    · {{ __('Bordereau') }} {{ $reception->reference }}
                @endif
            </flux:text>
        </div>

        @if ($inProgress)
            @can('receptions.create')
                <div class="flex flex-wrap gap-2">
                    <flux:button variant="ghost" icon="trash" wire:click="discard" wire:confirm="{{ __('Abandonner cette réception? Rien n\'est enregistré dans l\'inventaire.') }}">{{ __('Abandonner') }}</flux:button>
                    @php
                        $uncheckedCount = $groups->reject(fn ($lines) => $lines->every(fn ($line) => $line->checked_at !== null))->count();
                    @endphp
                    <flux:button variant="primary" icon="check" wire:click="complete" wire:confirm="{{ __('Terminer la réception? Les quantités entreront en inventaire.') }}" :disabled="$uncheckedCount > 0">
                        {{ $uncheckedCount > 0 ? trans_choice('Reste :count produit à vérifier|Reste :count produits à vérifier', $uncheckedCount) : __('Terminer la réception') }}
                    </flux:button>
                </div>
            @endcan
        @endif
    </div>

    @if ($inProgress)
        <flux:callout icon="information-circle" class="mb-6">
            <flux:callout.text>{{ __('Comparez ce qui est reçu au bordereau du fournisseur : balayez chaque article, ou touchez un produit pour saisir sa quantité, déclarer un manquant ou le ranger. Rien n\'entre en inventaire avant de terminer la réception.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($reception->shortLines()->isNotEmpty() || $reception->overshipLines()->isNotEmpty())
        <flux:callout icon="exclamation-triangle" color="amber" class="mb-6">
            <flux:callout.text>
                {{ __('Erreur d\'expédition : :short article(s) manquant(s), :over article(s) reçu(s) en trop. Le fournisseur en a été avisé; le crédit des manquants est demandé à la facturation.', [
                    'short' => $reception->shortLines()->sum('quantity_short'),
                    'over' => $reception->overshipLines()->sum('quantity'),
                ]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($inProgress)
        @can('receptions.create')
            <form wire:submit="scanUpc" class="mb-4">
                <flux:input wire:model="upcScan" icon="qr-code" autocomplete="off" autofocus :placeholder="__('Balayer le code UPC d\'un article reçu…')" />
            </form>
        @endcan

        <div class="grid gap-3 sm:grid-cols-2">
            @forelse ($groups as $groupKey => $groupLines)
                @php
                    $first = $groupLines->first();
                    $groupExpected = $groupLines->sum('quantity_expected');
                    $groupReceived = $groupLines->sum('quantity');
                    $groupShort = $groupLines->sum('quantity_short');
                    $groupDamaged = $groupLines->sum('quantity_damaged');
                    $isChecked = $groupLines->every(fn ($line) => $line->checked_at !== null);
                    $matches = $groupReceived + $groupShort === $groupExpected;
                    $customerCount = $groupLines->filter(fn ($line) => $line->isForCustomer())->sum('quantity_expected');
                @endphp
                <button
                    type="button"
                    wire:key="group-{{ $groupKey }}"
                    @can('receptions.create') wire:click="openGroup('{{ $groupKey }}')" @else disabled @endcan
                    @class([
                        'flex w-full items-start justify-between gap-3 rounded-xl border bg-white p-4 text-left shadow-sm transition hover:bg-zinc-50 dark:bg-zinc-900 dark:hover:bg-zinc-800',
                        'border-zinc-200 dark:border-zinc-700' => ! $isChecked,
                        'border-green-400 dark:border-green-600' => $isChecked && $matches,
                        'border-amber-400 dark:border-amber-600' => $isChecked && ! $matches,
                    ])
                >
                    <div class="min-w-0">
                        <div class="font-medium text-zinc-900 dark:text-white">{{ $first->label }}</div>
                        <div class="mt-0.5 text-xs text-zinc-500">{{ $groupLines->map(fn ($line) => $line->orderLine->order->number)->unique()->join(', ') }}</div>
                        <div class="mt-2 flex flex-wrap gap-1">
                            @if ($customerCount > 0)
                                <flux:badge color="blue" size="sm" icon="user">{{ trans_choice(':count pour client|:count pour clients', $customerCount) }}</flux:badge>
                            @endif
                            @if ($first->orderLine->is_overship)
                                <flux:badge color="purple" size="sm">{{ __('Reçu en trop') }}</flux:badge>
                            @endif
                            @foreach ($groupLines as $line)
                                @if ($shipment = $line->orderLine->activeShipment())
                                    <flux:badge color="amber" size="sm" icon="paper-airplane">{{ __('Pour expédition — commande #:order', ['order' => $shipment->customer_order_id]) }}</flux:badge>
                                @endif
                            @endforeach
                            @if ($groupShort > 0)
                                <flux:badge color="red" size="sm">{{ __(':count manquant(s)', ['count' => $groupShort]) }}</flux:badge>
                            @endif
                            @if ($groupDamaged > 0)
                                <flux:badge color="amber" size="sm">{{ __(':count endommagé(s)', ['count' => $groupDamaged]) }}</flux:badge>
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="text-lg font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $groupReceived }} / {{ $groupExpected }}</div>
                        @if ($isChecked)
                            <flux:icon :name="$matches ? 'check-circle' : 'exclamation-circle'" :class="'ms-auto mt-1 size-5 '.($matches ? 'text-green-500' : 'text-amber-500')" />
                        @else
                            <div class="mt-1 text-xs text-zinc-400">{{ __('À vérifier') }}</div>
                        @endif
                    </div>
                </button>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center sm:col-span-2 dark:border-zinc-600">
                    <flux:text class="text-zinc-400">{{ __('Aucun article dans cette réception.') }}</flux:text>
                </div>
            @endforelse
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Commande') }}</flux:table.column>
                    <flux:table.column>{{ __('Produit') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Quantité reçue') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('dont endommagé') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Manquant') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Renversée') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($reception->lines as $line)
                        <flux:table.row :key="$line->id">
                            <flux:table.cell>
                                <flux:link :href="route('supplier-orders.show', $line->orderLine->order)" wire:navigate>{{ $line->orderLine->order->number }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell variant="strong">
                                {{ $line->orderLine->label }}
                                @if ($line->orderLine->is_overship)
                                    <flux:badge color="purple" size="sm" class="ms-1">{{ __('Reçu en trop') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($line->quantity_damaged > 0)
                                    <flux:badge color="amber" size="sm">{{ $line->quantity_damaged }}</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($line->quantity_short > 0)
                                    <flux:badge color="red" size="sm">{{ $line->quantity_short }}</flux:badge>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $line->quantity_reversed ?: '—' }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="py-8 text-center">
                                <flux:text class="text-zinc-400">{{ __('Aucun article dans cette réception.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @endif

    @if (! $inProgress)
        @php
            $customerLines = $reception->lines->map(fn ($line) => $line->orderLine->customerOrderLine)->filter(fn ($customerLine) => $customerLine !== null && ! $customerLine->isService() && $customerLine->quantity_reserved > 0)->unique('id');
        @endphp
        @if ($customerLines->isNotEmpty())
            @can('receptions.create')
                <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:heading size="lg">{{ __('Ranger les articles réservés aux clients') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Notez où chaque article est rangé : le livreur, l\'entrepôt ou le vendeur sauront où le chercher.') }}</flux:text>
                    <div class="mt-4 space-y-4">
                        @foreach ($customerLines as $customerLine)
                            <div wire:key="place-{{ $customerLine->id }}" class="border-t border-zinc-100 pt-3 first:border-0 first:pt-0 dark:border-zinc-800">
                                <div class="mb-2 text-sm font-medium">
                                    {{ __(':label — commande #:order (:customer), :count réservé(s)', ['label' => $customerLine->label, 'order' => $customerLine->customer_order_id, 'customer' => $customerLine->order->customer->lastname, 'count' => $customerLine->quantity_reserved]) }}
                                </div>
                                <livewire:line-placement :line-id="$customerLine->id" :key="'reception-placement-'.$customerLine->id" />
                            </div>
                        @endforeach
                    </div>
                </div>
            @endcan
        @endif
    @endif

    @if ($inProgress)
        @can('receptions.create')
            <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Ajouter un article reçu en trop') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Un produit du fournisseur reçu sans être commandé (ou en plus de la commande). Il entre en inventaire à coût nul; la comptabilité ajustera son coût si le fournisseur le facture.') }}</flux:text>

                <div class="mt-4 flex flex-wrap items-start gap-3">
                    <div class="min-w-64 flex-1">
                        <flux:input wire:model.live.debounce.300ms="overshipSearch" icon="magnifying-glass" :placeholder="__('Modèle du produit…')" />
                    </div>
                    <div class="w-28">
                        <flux:input wire:model="overshipQuantity" type="number" min="1" step="1" :placeholder="__('Quantité')" />
                        <flux:error name="overshipQuantity" />
                    </div>
                    @php
                        $receptionOrders = $this->receptionOrders();
                    @endphp
                    @if ($receptionOrders->count() > 1)
                        <div class="w-full sm:w-48">
                            <flux:select wire:model="overshipOrderId">
                                @foreach ($receptionOrders as $receptionOrder)
                                    <flux:select.option :value="$receptionOrder->id">{{ $receptionOrder->number }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    @endif
                </div>

                @if (mb_strlen(trim($overshipSearch)) >= 2)
                    <div class="mt-2 divide-y divide-zinc-200 overflow-hidden rounded-lg border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
                        @forelse ($this->getOvershipResults() as $result)
                            <button type="button" wire:key="overship-{{ $result->id }}" wire:click="addOvership({{ $result->id }})" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $result->display_name }}</span>
                                <flux:icon name="plus" class="h-4 w-4 flex-shrink-0 text-zinc-400" />
                            </button>
                        @empty
                            <div class="px-3 py-2 text-sm text-zinc-400">{{ __('Aucun produit de ce fournisseur ne correspond.') }}</div>
                        @endforelse
                    </div>
                @endif
            </div>
        @endcan
    @endif

    <div class="mt-6 rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        @if ($inProgress)
            @can('receptions.create')
                <form wire:submit="saveDetails" class="space-y-4">
                    <flux:field>
                        <flux:label>{{ __('N° de bordereau') }}</flux:label>
                        <flux:input wire:model="reference" type="text" />
                        <flux:error name="reference" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Notes') }}</flux:label>
                        <flux:textarea wire:model="notes" rows="3" />
                        <flux:error name="notes" />
                    </flux:field>
                    <flux:button type="submit">{{ __('Sauvegarder') }}</flux:button>
                </form>
            @endcan
        @elseif ($reception->notes)
            <flux:heading size="lg">{{ __('Notes') }}</flux:heading>
            <flux:text class="mt-2 whitespace-pre-line">{{ $reception->notes }}</flux:text>
        @else
            <flux:text class="text-zinc-400">{{ __('Aucune note.') }}</flux:text>
        @endif
    </div>
    {{-- Réception d'un produit --}}
    @if ($inProgress)
        @can('receptions.create')
            <flux:modal wire:model="showGroupModal" class="w-full max-w-lg" x-on:close="$wire.closeGroup()">
                @if ($openLines)
                    @php
                        $first = $openLines->first();
                        $expected = $openLines->sum('quantity_expected');
                        $isOvership = $first->orderLine->is_overship;
                        $remaining = $expected - (int) $received - (int) $short;
                        $customerLines = $openLines->map(fn ($line) => $line->orderLine->customerOrderLine)->filter(fn ($customerLine) => $customerLine !== null && ! $customerLine->isService());
                    @endphp
                    <flux:heading size="lg">{{ $first->label }}</flux:heading>
                    <flux:text class="mt-1 text-zinc-500">
                        {{ $openLines->map(fn ($line) => $line->orderLine->order->number)->unique()->join(', ') }}
                        · {{ __('Attendu : :count', ['count' => $expected]) }}
                    </flux:text>

                    <form wire:submit="saveGroup" class="mt-6 space-y-4">
                        <div class="grid gap-4 sm:grid-cols-3">
                            <flux:field>
                                <flux:label>{{ __('Reçu') }}</flux:label>
                                <flux:input wire:model.live.debounce.300ms="received" type="number" min="0" step="1" inputmode="numeric" />
                            </flux:field>
                            @if ($first->product_id)
                                <flux:field>
                                    <flux:label>{{ __('dont endommagé') }}</flux:label>
                                    <flux:input wire:model="damaged" type="number" min="0" step="1" inputmode="numeric" />
                                </flux:field>
                            @endif
                            @unless ($isOvership)
                                <flux:field>
                                    <flux:label>{{ __('Manquant') }}</flux:label>
                                    <flux:input wire:model.live.debounce.300ms="short" type="number" min="0" step="1" inputmode="numeric" />
                                </flux:field>
                            @endunless
                        </div>
                        <flux:error name="received" />
                        <flux:error name="damaged" />
                        <flux:error name="short" />

                        @unless ($isOvership)
                            <flux:text class="text-sm text-zinc-500">
                                @if ($remaining > 0)
                                    {{ __(':count restera en commande chez le fournisseur.', ['count' => $remaining]) }}
                                @elseif ($remaining === 0)
                                    {{ __('Tout ce qui est attendu est reçu ou déclaré manquant.') }}
                                @endif
                                {{ __('Un manquant est un article annoncé sur le bordereau mais absent : le fournisseur sera avisé et un crédit lui sera demandé.') }}
                            </flux:text>
                        @endunless

                        <div class="flex flex-wrap justify-between gap-2 pt-2">
                            <flux:button type="button" variant="ghost" icon="trash" wire:click="removeGroup" wire:confirm="{{ __('Retirer ce produit de la réception?') }}">{{ __('Retirer') }}</flux:button>
                            <div class="flex gap-2">
                                <flux:button type="button" variant="ghost" wire:click="closeGroup">{{ __('Annuler') }}</flux:button>
                                <flux:button type="submit" variant="primary">{{ __('Confirmer') }}</flux:button>
                            </div>
                        </div>
                    </form>

                    @if ($customerLines->isNotEmpty())
                        <div class="mt-6 space-y-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                            <flux:heading>{{ __('Ranger pour les clients') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500">{{ __('Les articles reçus servent d\'abord les clients. Notez où chacun est rangé.') }}</flux:text>
                            @foreach ($customerLines as $customerLine)
                                <div wire:key="group-place-{{ $customerLine->id }}">
                                    <div class="mb-2 text-sm font-medium">
                                        {{ __('Commande #:order (:customer) — :count', ['order' => $customerLine->customer_order_id, 'customer' => $customerLine->order->customer->firstname.' '.$customerLine->order->customer->lastname, 'count' => $customerLine->quantity]) }}
                                    </div>
                                    <livewire:line-placement :line-id="$customerLine->id" :key="'reception-group-placement-'.$customerLine->id" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </flux:modal>
        @endcan
    @endif
</div>

<div class="sm:p-6">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <flux:heading level="1" size="xl">{{ __('Journal général') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Écritures manuelles pour ce qui n\'a pas de document dans l\'application : soldes d\'ouverture, dépôts des processeurs de paiement, virements, frais bancaires, paie. Une écriture ne se modifie pas : elle se renverse.') }}</flux:text>
        </div>
        @can('ledger.journal')
            <flux:button variant="primary" icon="plus" wire:click="openEntry">{{ __('Nouvelle écriture') }}</flux:button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$entries">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Compte') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Débit') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Crédit') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($entries as $entry)
                    @foreach ($entry->ledgerEntry?->lines ?? [] as $line)
                        <flux:table.row :key="'journal-'.$entry->id.'-'.$line->id">
                            <flux:table.cell>{{ $loop->first ? $entry->entry_date->format('Y-m-d') : '' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($loop->first)
                                    {{ $entry->description }}
                                    <flux:text class="text-xs text-zinc-400">
                                        {{ collect([$entry->document_number, $entry->store?->name, $entry->creator ? $entry->creator->firstname.' '.$entry->creator->lastname : null])->filter()->implode(' · ') }}
                                    </flux:text>
                                    @if ($entry->reversal)
                                        <flux:badge size="sm" color="zinc">{{ __('Renversée le :date', ['date' => $entry->reversal->entry_date->format('Y-m-d')]) }}</flux:badge>
                                    @endif
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $line->account->code }} — {{ $line->account->name }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->debit->isZero() ? '' : $line->debit->format() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->credit->isZero() ? '' : $line->credit->format() }}</flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($loop->first && ! $entry->reversal && ! $entry->reverses_id)
                                    @can('ledger.journal')
                                        <flux:button size="xs" variant="ghost" icon="arrow-uturn-left" wire:click="reverse({{ $entry->id }})" wire:confirm="{{ __('Renverser cette écriture à la date d\'aujourd\'hui ?') }}">{{ __('Renverser') }}</flux:button>
                                    @endcan
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="pencil-square" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune écriture manuelle.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showEntry" class="w-full max-w-3xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading>{{ __('Nouvelle écriture') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="entryDate" :label="__('Date')" required />
                <flux:input wire:model="documentNumber" :label="__('Pièce justificative')" :placeholder="__('Relevé, bordereau…')" />
                <flux:input wire:model="description" :label="__('Description')" class="sm:col-span-2" required />
                <flux:select wire:model="storeId" :label="__('Magasin')">
                    <flux:select.option value="">{{ __('Aucun (bureau chef)') }}</flux:select.option>
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="space-y-2">
                @foreach ($lines as $index => $line)
                    <div class="flex flex-wrap items-end gap-2" wire:key="journal-line-{{ $index }}">
                        <div class="min-w-0 flex-1">
                            <flux:select wire:model="lines.{{ $index }}.account_id" :label="$loop->first ? __('Compte') : null">
                                <flux:select.option value="">{{ __('Choisir un compte') }}</flux:select.option>
                                @foreach ($accounts as $account)
                                    <flux:select.option :value="$account->id">{{ $account->code }} — {{ $account->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:input wire:model.live.debounce.400ms="lines.{{ $index }}.debit" :label="$loop->first ? __('Débit') : null" inputmode="decimal" class="w-28" />
                        <flux:input wire:model.live.debounce.400ms="lines.{{ $index }}.credit" :label="$loop->first ? __('Crédit') : null" inputmode="decimal" class="w-28" />
                        <flux:button type="button" variant="ghost" icon="x-mark" wire:click="removeLine({{ $index }})" :disabled="count($lines) <= 2" />
                    </div>
                @endforeach

                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:button type="button" size="sm" icon="plus" wire:click="addLine">{{ __('Ajouter une ligne') }}</flux:button>
                    <flux:text @class(['text-sm', 'text-red-600' => ! $totals['debit']->equals($totals['credit'])])>
                        {{ __('Débits :debit · Crédits :credit', ['debit' => $totals['debit']->format(), 'credit' => $totals['credit']->format()]) }}
                    </flux:text>
                </div>

                <flux:error name="lines" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showEntry', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Passer l\'écriture') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

<div class="sm:p-6">
    <div class="mb-6">
        <flux:heading level="1" size="xl">{{ __('États comptables') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Balance de vérification, grand livre d\'un compte et taxes à remettre sur une période, pour un magasin ou l\'ensemble. Les soldes sont en débits moins crédits.') }}</flux:text>
    </div>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <flux:radio.group wire:model.live="tab" variant="segmented">
            <flux:radio value="balance" :label="__('Balance')" />
            <flux:radio value="account" :label="__('Compte')" />
            <flux:radio value="taxes" :label="__('Taxes')" />
            <flux:radio value="closing" :label="__('Clôture')" />
        </flux:radio.group>

        @if ($tab !== 'closing')
            <flux:input type="date" wire:model.live="from" :label="__('Du')" class="w-40" />
            <flux:input type="date" wire:model.live="to" :label="__('Au')" class="w-40" />
            <div class="w-full sm:w-56">
                <flux:select wire:model.live="storeFilter" :label="__('Magasin')">
                    <flux:select.option value="">{{ __('Tous les magasins') }}</flux:select.option>
                    @foreach ($stores as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button icon="arrow-down-tray" wire:click="exportBalance">{{ __('Balance CSV') }}</flux:button>
            <flux:button icon="arrow-down-tray" wire:click="exportEntries">{{ __('Écritures CSV') }}</flux:button>
        @endif
    </div>

    @if ($closedThrough && $tab !== 'closing')
        <flux:text class="mb-4 text-sm text-zinc-500">{{ __('Grand livre clôturé jusqu\'au :date.', ['date' => $closedThrough->format('Y-m-d')]) }}</flux:text>
    @endif

    @if ($tab === 'balance')
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Compte') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Ouverture') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Débits') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Crédits') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Clôture') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($balance as $row)
                        <flux:table.row :key="'balance-'.$row['account']->id">
                            <flux:table.cell>
                                <flux:link class="cursor-pointer" wire:click="showAccount({{ $row['account']->id }})">{{ $row['account']->code }} — {{ $row['account']->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell align="end">{{ $row['opening']->format() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['debit']->format() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $row['credit']->format() }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">{{ $row['closing']->format() }}</flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-12 text-center">
                                <flux:text class="text-zinc-400">{{ __('Aucune écriture.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse

                    @if ($balance->isNotEmpty())
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Total') }}</flux:table.cell>
                            <flux:table.cell />
                            <flux:table.cell align="end" variant="strong">{{ $totals['debit']->format() }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">{{ $totals['credit']->format() }}</flux:table.cell>
                            <flux:table.cell />
                        </flux:table.row>
                    @endif
                </flux:table.rows>
            </flux:table>
        </div>
    @elseif ($tab === 'account')
        <div class="mb-4 w-full sm:w-96">
            <flux:select wire:model.live="accountId" :label="__('Compte')">
                <flux:select.option value="">{{ __('Choisir un compte') }}</flux:select.option>
                @foreach ($accounts as $option)
                    <flux:select.option :value="$option->id">{{ $option->code }} — {{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($accountLedger)
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Débit') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Crédit') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Solde') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell>{{ $report->from->format('Y-m-d') }}</flux:table.cell>
                            <flux:table.cell variant="strong">{{ __('Solde d\'ouverture') }}</flux:table.cell>
                            <flux:table.cell />
                            <flux:table.cell />
                            <flux:table.cell align="end" variant="strong">{{ $accountLedger['opening']->format() }}</flux:table.cell>
                        </flux:table.row>

                        @foreach ($accountLedger['lines'] as $row)
                            <flux:table.row :key="'line-'.$row['line']->id">
                                <flux:table.cell>{{ $row['line']->entry->posted_on->format('Y-m-d') }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($url = $row['line']->entry->sourceUrl())
                                        <flux:link :href="$url" wire:navigate>{{ $row['line']->entry->description }}</flux:link>
                                    @else
                                        {{ $row['line']->entry->description }}
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">{{ $row['line']->debit->isZero() ? '' : $row['line']->debit->format() }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['line']->credit->isZero() ? '' : $row['line']->credit->format() }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['balance']->format() }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach

                        <flux:table.row>
                            <flux:table.cell>{{ $report->to->format('Y-m-d') }}</flux:table.cell>
                            <flux:table.cell variant="strong">{{ __('Solde de clôture') }}</flux:table.cell>
                            <flux:table.cell />
                            <flux:table.cell />
                            <flux:table.cell align="end" variant="strong">{{ $accountLedger['closing']->format() }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </div>
        @endif
    @elseif ($tab === 'taxes')
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Taxe') }}</flux:table.column>
                    <flux:table.column>{{ __('Numéro d\'inscription') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Perçues') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Payées (CTI/RTI)') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Net à remettre') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($taxes as $name => $tax)
                        <flux:table.row :key="'tax-'.$name">
                            <flux:table.cell variant="strong">{{ $name }}</flux:table.cell>
                            <flux:table.cell>{{ implode(', ', $tax['registrations']) }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $tax['collected']->format() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $tax['paid']->format() }}</flux:table.cell>
                            <flux:table.cell align="end" variant="strong">
                                {{ $tax['net']->abs()->format() }}
                                <flux:text class="text-xs text-zinc-400">{{ $tax['net']->isNegative() ? __('à recevoir') : __('à remettre') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="py-12 text-center">
                                <flux:text class="text-zinc-400">{{ __('Aucune taxe sur la période.') }}</flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    @else
        <div class="max-w-2xl space-y-6">
            <flux:text>
                {{ $closedThrough ? __('Le grand livre est clôturé jusqu\'au :date inclusivement.', ['date' => $closedThrough->format('Y-m-d')]) : __('Aucune période n\'est clôturée.') }}
                {{ __('Un document daté dans une période clôturée reste permis : son écriture est reportée au premier jour ouvert.') }}
            </flux:text>

            @can('ledger.close')
                <form wire:submit="closePeriod" class="flex flex-wrap items-end gap-3">
                    <flux:field>
                        <flux:label>{{ __('Clôturer jusqu\'au') }}</flux:label>
                        <flux:input type="date" wire:model="closeThrough" />
                        <flux:error name="closeThrough" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" icon="lock-closed">{{ __('Clôturer') }}</flux:button>
                </form>

                @if ($closedThrough)
                    <form wire:submit="reopenPeriod" class="flex flex-wrap items-end gap-3">
                        <flux:field>
                            <flux:label>{{ __('Rouvrir à partir du') }}</flux:label>
                            <flux:input type="date" wire:model="reopenFrom" />
                            <flux:error name="reopenFrom" />
                        </flux:field>
                        <flux:button type="submit" icon="lock-open">{{ __('Rouvrir') }}</flux:button>
                    </form>
                @endif
            @endcan

            @if ($closings->isNotEmpty())
                <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Le') }}</flux:table.column>
                            <flux:table.column>{{ __('Clôturé jusqu\'au') }}</flux:table.column>
                            <flux:table.column>{{ __('Par') }}</flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($closings as $closing)
                                <flux:table.row :key="'closing-'.$closing->id">
                                    <flux:table.cell>{{ $closing->created_at?->format('Y-m-d H:i') }}</flux:table.cell>
                                    <flux:table.cell>{{ $closing->closed_through?->format('Y-m-d') ?? __('Tout ouvert') }}</flux:table.cell>
                                    <flux:table.cell>{{ $closing->user ? $closing->user->firstname.' '.$closing->user->lastname : '' }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endif
        </div>
    @endif
</div>

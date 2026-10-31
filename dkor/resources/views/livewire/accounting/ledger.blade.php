<div class="sm:p-6">
    <div class="mb-6">
        <flux:heading level="1" size="xl">{{ __('Grand livre') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Écritures équilibrées, passées par les ajustements d\'inventaire. Une écriture ne se modifie pas : une correction passe une écriture inverse.') }}</flux:text>
    </div>

    <div class="mb-4 w-full sm:w-72">
        <flux:select wire:model.live="accountFilter">
            <flux:select.option value="">{{ __('Tous les comptes') }}</flux:select.option>
            @foreach ($accounts as $account)
                <flux:select.option :value="$account->id">{{ $account->code }} — {{ $account->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table :paginate="$entries">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Compte') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Débit') }}</flux:table.column>
                <flux:table.column align="end">{{ __('Crédit') }}</flux:table.column>
                <flux:table.column>{{ __('Par') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($entries as $entry)
                    @foreach ($entry->lines as $line)
                        <flux:table.row :key="$entry->id.'-'.$line->id">
                            <flux:table.cell>{{ $loop->first ? $entry->posted_on->format('Y-m-d') : '' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($loop->first)
                                    {{ $entry->description }}
                                    @if ($entry->reference instanceof \App\Models\InventoryAdjustment)
                                        <flux:text class="text-xs text-zinc-400">{{ $entry->reference->note }}</flux:text>
                                    @endif
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $line->account->code }} — {{ $line->account->name }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->debit->isZero() ? '' : $line->debit->format() }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $line->credit->isZero() ? '' : $line->credit->format() }}</flux:table.cell>
                            <flux:table.cell>{{ $loop->first && $entry->user ? $entry->user->firstname.' '.$entry->user->lastname : '' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="book-open" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucune écriture.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:heading size="lg" class="mb-3 mt-10">{{ __('Comptes') }}</flux:heading>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Code') }}</flux:table.column>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($accounts as $account)
                    <flux:table.row :key="'account-'.$account->id">
                        <flux:table.cell variant="strong">{{ $account->code }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $account->name }}
                            @if ($account->isSystem())
                                <flux:badge size="sm" color="zinc">{{ __('Système') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $account->type->label() }}</flux:table.cell>
                        <flux:table.cell align="end">
                            @can('ledger.edit')
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editAccount({{ $account->id }})">{{ __('Modifier') }}</flux:button>
                            @endcan
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showAccount" class="w-full max-w-md">
        <form wire:submit="saveAccount" class="space-y-4">
            <flux:heading>{{ __('Modifier le compte') }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __('Le code et le nom suivent votre plan comptable; le rôle du compte dans l\'application ne change pas.') }}</flux:text>

            <flux:input wire:model="code" :label="__('Code')" required />
            <flux:input wire:model="name" :label="__('Nom')" required />

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showAccount', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ __('Enregistrer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Modes de paiement') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Modes de paiement acceptés des clients') }}</flux:text>
        </div>
        @can('payment_methods.create')
            <flux:button variant="primary" icon="plus" wire:click="openCreate">
                {{ __('Ajouter') }}
            </flux:button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($paymentMethods as $paymentMethod)
                    <flux:table.row :key="$paymentMethod->id" @class(['opacity-50' => ! $paymentMethod->is_active])>
                        <flux:table.cell variant="strong">
                            {{ $paymentMethod->name }}
                            @unless ($paymentMethod->is_active)
                                <flux:badge size="sm" class="ms-2">{{ __('Inactif') }}</flux:badge>
                            @endunless
                            @if ($paymentMethod->isSystem())
                                <flux:badge size="sm" color="zinc" icon="lock-closed" class="ms-2">{{ __('Géré par le système') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-right">
                            @if (! $paymentMethod->isSystem() && auth()->user()->can('payment_methods.edit'))
                                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $paymentMethod->id }})" />
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    :icon="$paymentMethod->is_active ? 'archive-box' : 'arrow-uturn-left'"
                                    :title="$paymentMethod->is_active ? __('Désactiver') : __('Réactiver')"
                                    wire:click="toggleActive({{ $paymentMethod->id }})"
                                />
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="2" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="credit-card" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucun mode de paiement.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier le mode de paiement') : __('Nouveau mode de paiement') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="name" type="text" placeholder="Visa" required autofocus />
                <flux:error name="name" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Permissions') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Clés utilisées par les accès de l\'application. Elles sont attribuées aux rôles et, au besoin, directement aux utilisateurs.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('Ajouter') }}
        </flux:button>
    </div>

    <div class="space-y-6">
        @foreach ($groups as $group => $permissions)
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-4 py-2 dark:border-zinc-700">
                    <flux:heading size="sm">{{ \Illuminate\Support\Str::headline($group) }}</flux:heading>
                </div>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Permission') }}</flux:table.column>
                        <flux:table.column>{{ __('Clé') }}</flux:table.column>
                        <flux:table.column>{{ __('Rôles') }}</flux:table.column>
                        <flux:table.column />
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($permissions as $permission)
                            <flux:table.row :key="$permission->id">
                                <flux:table.cell variant="strong">
                                    {{ $permission->displayName() }}
                                    @if ($permission->description)
                                        <div class="text-xs font-normal text-zinc-500">{{ $permission->description }}</div>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell><code class="text-xs">{{ $permission->name }}</code></flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($permission->roles->sortByDesc('level') as $role)
                                            <flux:badge size="sm" color="zinc">{{ $role->displayName() }}</flux:badge>
                                        @endforeach
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell class="text-right">
                                    <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $permission->id }})" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        @endforeach
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-md">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier la permission') : __('Nouvelle permission') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Clé') }}</flux:label>
                <flux:input wire:model="name" type="text" placeholder="products.update" class="font-mono" :disabled="(bool) $editingId" />
                @if ($editingId)
                    <flux:description>{{ __('La clé ne peut pas être modifiée : elle est utilisée dans le code.') }}</flux:description>
                @endif
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Libellé') }}</flux:label>
                <flux:input wire:model="label" type="text" required />
                <flux:error name="label" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:input wire:model="description" type="text" />
                <flux:error name="description" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

<div class="p-6">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Rôles') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Chaque employé a un rôle. Le niveau détermine quels rôles il peut gérer.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="openCreate">
            {{ __('Ajouter') }}
        </flux:button>
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Rôle') }}</flux:table.column>
                <flux:table.column>{{ __('Clé') }}</flux:table.column>
                <flux:table.column>{{ __('Niveau') }}</flux:table.column>
                <flux:table.column>{{ __('Permissions') }}</flux:table.column>
                <flux:table.column>{{ __('Employés') }}</flux:table.column>
                <flux:table.column />
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($roles as $role)
                    <flux:table.row :key="$role->id">
                        <flux:table.cell variant="strong">{{ $role->displayName() }}</flux:table.cell>
                        <flux:table.cell><code class="text-xs">{{ $role->name }}</code></flux:table.cell>
                        <flux:table.cell>{{ $role->level }}</flux:table.cell>
                        <flux:table.cell>{{ $role->permissions_count }}</flux:table.cell>
                        <flux:table.cell>{{ $role->users_count }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            @can('update', $role)
                                <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEdit({{ $role->id }})" />
                            @endcan
                            @if ($role->users_count === 0)
                                @can('delete', $role)
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        wire:click="delete({{ $role->id }})"
                                        wire:confirm="{{ __('Supprimer ce rôle ?') }}"
                                    />
                                @endcan
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </div>

    <flux:modal wire:model="showModal" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ $editingId ? __('Modifier le rôle') : __('Nouveau rôle') }}</flux:heading>

        <form wire:submit="save" class="mt-6 space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Libellé') }}</flux:label>
                    <flux:input wire:model="label" type="text" required autofocus />
                    <flux:error name="label" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Clé') }}</flux:label>
                    <flux:input wire:model="name" type="text" class="font-mono" :disabled="(bool) $editingId" required />
                    <flux:error name="name" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Niveau hiérarchique') }}</flux:label>
                <flux:input wire:model="level" type="number" min="0" />
                <flux:description>{{ __('Un employé ne gère que les rôles de niveau inférieur ou égal au sien.') }}</flux:description>
                <flux:error name="level" />
            </flux:field>

            <div>
                <flux:heading size="sm" class="mb-2">{{ __('Permissions de base') }}</flux:heading>
                @php($grantable = $this->grantablePermissions())
                <div class="max-h-72 space-y-4 overflow-y-auto pr-1">
                    @foreach ($permissionGroups as $group => $groupPermissions)
                        <div>
                            <flux:text class="mb-1 text-xs font-medium uppercase text-zinc-500">{{ \Illuminate\Support\Str::headline($group) }}</flux:text>
                            <div class="space-y-2">
                                @foreach ($groupPermissions as $permission)
                                    <flux:checkbox
                                        wire:model="permissions"
                                        :value="$permission->name"
                                        :label="$permission->displayName()"
                                        :disabled="! in_array($permission->name, $grantable, true)"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <flux:error name="permissions" />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>

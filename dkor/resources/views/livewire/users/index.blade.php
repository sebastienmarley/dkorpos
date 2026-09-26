<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Utilisateurs') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Employés actifs') }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
            {{ __('Ajouter un utilisateur') }}
        </flux:button>
    </div>

    {{-- Filtre par rôle --}}
    @if ($roles->isNotEmpty())
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <flux:text class="text-sm text-zinc-500">{{ __('Filtrer par rôle :') }}</flux:text>

            <flux:button
                size="sm"
                :variant="$sortRole === '' ? 'primary' : 'filled'"
                wire:click="sortByRole('')"
            >
                {{ __('Tous') }}
            </flux:button>

            @foreach ($roles as $r)
                <flux:button
                    size="sm"
                    :variant="$sortRole === $r ? 'primary' : 'filled'"
                    wire:click="sortByRole('{{ $r }}')"
                >
                    {{ ucfirst($r) }}
                </flux:button>
            @endforeach
        </div>
    @endif

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Rôle') }}</flux:table.column>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __("Nom d'utilisateur") }}</flux:table.column>
                <flux:table.column>{{ __('Courriel') }}</flux:table.column>
                <flux:table.column>{{ __('Depuis') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            @if ($user->role === 'admin')
                                <flux:badge color="violet" size="sm">{{ __('Admin') }}</flux:badge>
                            @else
                                <flux:badge color="blue" size="sm">{{ ucfirst($user->role) }}</flux:badge>
                            @endif
                        </flux:table.cell>

                        <flux:table.cell variant="strong">
                            <div class="flex items-center gap-3">
                                <flux:avatar
                                    :name="$user->fullName()"
                                    :initials="$user->initials()"
                                    size="sm"
                                />
                                {{ $user->fullName() }}
                            </div>
                        </flux:table.cell>

                        <flux:table.cell>{{ $user->username }}</flux:table.cell>

                        <flux:table.cell>{{ $user->email }}</flux:table.cell>

                        <flux:table.cell>
                            {{ $user->first_day ? $user->first_day->translatedFormat('j M Y') : '—' }}
                        </flux:table.cell>

                        <flux:table.cell align="end">
                            <flux:button
                                size="sm"
                                variant="ghost"
                                icon="pencil-square"
                                wire:click="openEditModal({{ $user->id }})"
                            >
                                {{ __('Modifier') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="users" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucun utilisateur actif trouvé.') }}</flux:text>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Compteur --}}
    @if ($users->isNotEmpty())
        <flux:text class="mt-3 text-sm text-zinc-400">
            {{ trans_choice(':count utilisateur actif|:count utilisateurs actifs', $users->count()) }}
            @if (filled($sortRole))
                · {{ __('filtrés par rôle :') }} <strong>{{ ucfirst($sortRole) }}</strong>
            @endif
        </flux:text>
    @endif

    {{-- Modal de création --}}
    <flux:modal wire:model="showCreateModal" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Nouvel utilisateur') }}</flux:heading>
        <flux:text class="mb-6 mt-1 text-zinc-500">{{ __('Le courriel et le mot de passe temporaire seront générés automatiquement.') }}</flux:text>

        @if ($showDuplicatePrompt && $existingUser)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-medium">{{ __('Un utilisateur avec le nom « :username » existe déjà.', ['username' => $username]) }}</p>
                <p class="mt-1">{{ __("S'agit-il d'un nouvel employé ou d'un employé de retour ?") }}</p>
                <div class="mt-3">
                    <flux:button size="sm" variant="primary" wire:click="confirmNewEmployee">
                        {{ __('Nouvel employé') }}
                    </flux:button>
                </div>
            </div>
        @endif

        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Prénom') }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="firstname" type="text" required autofocus />
                    <flux:error name="firstname" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model.live.debounce.400ms="lastname" type="text" required />
                    <flux:error name="lastname" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Rôle') }}</flux:label>
                <flux:select wire:model="role">
                    <flux:select.option value="user">{{ __('Utilisateur') }}</flux:select.option>
                    <flux:select.option value="admin">{{ __('Admin') }}</flux:select.option>
                </flux:select>
                <flux:error name="role" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Premier jour') }}</flux:label>
                <flux:input wire:model="first_day" type="date" />
                <flux:error name="first_day" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Courriel personnel') }}</flux:label>
                <flux:input wire:model="personalEmail" type="email" placeholder="prenom.nom@exemple.com" />
                <flux:error name="personalEmail" />
            </flux:field>

            {{-- Aperçu des informations générées --}}
            @if (filled($username))
                <div class="space-y-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __("Nom d'utilisateur") }}</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $username }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-500">{{ __('Courriel') }}</span>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $generatedEmail }}</span>
                    </div>
                </div>
                <flux:error name="generatedEmail" />
            @endif

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCreateModal', false)">
                    {{ __('Annuler') }}
                </flux:button>
                <flux:button type="submit" variant="primary">
                    {{ __('Créer') }}
                </flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal de modification --}}
    <flux:modal wire:model="showEditModal" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Modifier l\'utilisateur') }}</flux:heading>
        <flux:text class="mb-6 mt-1 text-zinc-500">{{ __("Le nom d'utilisateur et le courriel système ne peuvent pas être modifiés.") }}</flux:text>

        {{-- Info en lecture seule --}}
        @if (filled($editUsername))
            <div class="mb-4 space-y-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500">{{ __("Nom d'utilisateur") }}</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $editUsername }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-zinc-500">{{ __('Courriel') }}</span>
                    <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $editEmail }}</span>
                </div>
            </div>
        @endif

        <form wire:submit="update" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Prénom') }}</flux:label>
                    <flux:input wire:model="editFirstname" type="text" required autofocus />
                    <flux:error name="editFirstname" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nom') }}</flux:label>
                    <flux:input wire:model="editLastname" type="text" required />
                    <flux:error name="editLastname" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Rôle') }}</flux:label>
                <flux:select wire:model="editRole">
                    <flux:select.option value="user">{{ __('Utilisateur') }}</flux:select.option>
                    <flux:select.option value="admin">{{ __('Admin') }}</flux:select.option>
                </flux:select>
                <flux:error name="editRole" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Premier jour') }}</flux:label>
                    <flux:input wire:model="editFirstDay" type="date" />
                    <flux:error name="editFirstDay" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Dernier jour') }}</flux:label>
                    <flux:input wire:model="editLastDay" type="date" />
                    <flux:error name="editLastDay" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Courriel personnel') }}</flux:label>
                <flux:input wire:model="editPersonalEmail" type="email" placeholder="prenom.nom@exemple.com" />
                <flux:error name="editPersonalEmail" />
            </flux:field>

            <div class="flex items-center justify-between pt-2">
                <flux:switch wire:model="editIsActive" :label="__('Actif')" />
                <div class="flex gap-3">
                    <flux:button type="button" variant="ghost" wire:click="$set('showEditModal', false)">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Sauvegarder') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

</div>
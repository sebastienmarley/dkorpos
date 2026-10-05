<div class="p-6">
    @if (session('toast-error'))
        <div x-data x-init="$nextTick(() => $flux.toast({ text: @js(session('toast-error')), variant: 'danger' }))"></div>
    @endif

    {{-- En-tête --}}
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Utilisateurs') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ match ($statusFilter) { 'inactive' => __('Employés inactifs'), 'all' => __('Tous les employés'), default => __('Employés actifs') } }}</flux:text>
        </div>

        @can('create', \App\Models\User::class)
            <flux:button variant="primary" icon="plus" wire:click="openCreateModal">
                {{ __('Ajouter un utilisateur') }}
            </flux:button>
        @endcan
    </div>

    {{-- Recherche et filtres --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div class="w-64">
            <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" :placeholder="__('Rechercher un employé…')" />
        </div>

        <div class="w-56">
            <flux:select wire:model.live="sortRole">
                <flux:select.option value="">{{ __('Tous les rôles') }}</flux:select.option>
                @foreach ($this->visibleRoles() as $roleOption)
                    <flux:select.option :value="$roleOption->name">{{ $roleOption->displayName() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="w-44">
            <flux:select wire:model.live="statusFilter">
                <flux:select.option value="active">{{ __('Actifs') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactifs') }}</flux:select.option>
                <flux:select.option value="all">{{ __('Tous') }}</flux:select.option>
            </flux:select>
        </div>
    </div>

    {{-- Tableau --}}
    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Nom') }}</flux:table.column>
                <flux:table.column>{{ __('Position') }}</flux:table.column>
                <flux:table.column>{{ __('Rôle') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell variant="strong">
                            @if (auth()->user()->can('update', $user) || auth()->user()->can('viewHr', $user))
                                <flux:link :href="route('users.show', $user)" wire:navigate>
                                    {{ $user->fullName() }}
                                </flux:link>
                            @else
                                {{ $user->fullName() }}
                            @endif
                        </flux:table.cell>

                        <flux:table.cell>{{ $user->position?->name ?? '—' }}</flux:table.cell>

                        <flux:table.cell>
                            <flux:badge :color="($user->role?->level ?? 0) >= 100 ? 'violet' : 'blue'" size="sm">
                                {{ $user->role?->displayName() }}
                            </flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3" class="py-12 text-center">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="users" class="h-8 w-8 text-zinc-300" />
                                <flux:text class="text-zinc-400">{{ __('Aucun utilisateur trouvé.') }}</flux:text>
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
            {{ trans_choice(':count utilisateur|:count utilisateurs', $users->count()) }}
            @if (filled($sortRole))
                · {{ __('filtrés par rôle :') }} <strong>{{ $this->visibleRoles()->firstWhere('name', $sortRole)?->displayName() }}</strong>
            @endif
        </flux:text>
    @endif

    {{-- Identifiants du nouvel utilisateur --}}
    <flux:modal wire:model="showCredentialsModal" class="w-full max-w-md" x-on:close="$wire.closeCredentialsModal()">
        <flux:heading class="mb-1">{{ __('Utilisateur créé') }}</flux:heading>
        <flux:text class="mb-4 mt-1 text-zinc-500">{{ __('Notez ce mot de passe temporaire, il ne sera plus affiché.') }}</flux:text>

        <div class="space-y-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-800">
            <div class="flex items-center justify-between">
                <span class="text-zinc-500">{{ __('Courriel') }}</span>
                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $createdEmail }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="text-zinc-500">{{ __('Mot de passe') }}</span>
                <code class="font-mono font-semibold tracking-wider text-zinc-800 dark:text-zinc-100">{{ $createdPassword }}</code>
            </div>
        </div>

        <div class="flex justify-end gap-3 pt-4">
            <flux:button
                type="button"
                variant="ghost"
                icon="clipboard"
                x-on:click="navigator.clipboard.writeText(@js($createdPassword))"
            >
                {{ __('Copier') }}
            </flux:button>
            <flux:button type="button" variant="primary" wire:click="closeCredentialsModal">
                {{ __('Fermer') }}
            </flux:button>
        </div>
    </flux:modal>

    {{-- Modal de création --}}
    <flux:modal wire:model="showCreateModal" class="w-full max-w-lg">
        <flux:heading class="mb-1">{{ __('Nouvel utilisateur') }}</flux:heading>
        <flux:text class="mb-6 mt-1 text-zinc-500">{{ __('Le courriel et le mot de passe temporaire seront générés automatiquement.') }}</flux:text>

        @if ($inactiveMatchIds !== [] && $employeeChoice === null)
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                <p class="font-medium">
                    {{ trans_choice('Un employé inactif porte déjà ce nom.|:count employés inactifs portent déjà ce nom.', count($inactiveMatchIds)) }}
                </p>
                <p class="mt-1">{{ __("S'agit-il d'un nouvel employé ou d'un retour d'employé ?") }}</p>
                <div class="mt-3 flex gap-2">
                    <flux:button size="sm" variant="primary" wire:click="confirmNewEmployee">
                        {{ __('Nouvel employé') }}
                    </flux:button>
                    <flux:button size="sm" wire:click="confirmReturningEmployee">
                        {{ __('Retour d\'un employé') }}
                    </flux:button>
                </div>
            </div>
        @endif

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
                    @foreach ($this->assignableRoles() as $roleOption)
                        <flux:select.option :value="$roleOption->name">{{ $roleOption->displayName() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="role" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Position') }}</flux:label>
                <flux:select wire:model="positionId">
                    <flux:select.option value="">{{ __('Aucune') }}</flux:select.option>
                    @foreach ($this->positions() as $position)
                        <flux:select.option :value="$position->id">{{ $position->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="positionId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Magasin') }}</flux:label>
                <flux:select wire:model="storeId" required>
                    <flux:select.option value="">{{ __('Choisir un magasin') }}</flux:select.option>
                    @foreach ($this->stores() as $store)
                        <flux:select.option :value="$store->id">{{ $store->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="storeId" />
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

            <x-phone-input wire:model="cellphone" label="{{ __('Cellulaire') }}" name="cellphone" />

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
</div>

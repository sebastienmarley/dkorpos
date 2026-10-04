<div class="p-6">
    {{-- En-tête --}}
    <div class="mb-6 flex items-center gap-4">
        <flux:button variant="ghost" icon="arrow-left" :href="route('users.index')" wire:navigate size="sm">
            {{ __('Retour') }}
        </flux:button>

        <div class="min-w-0 flex-1">
            <flux:heading level="1" size="xl">{{ $user->fullName() }}</flux:heading>
            <div class="mt-1 flex items-center gap-2">
                <flux:badge :color="($user->role?->level ?? 0) >= 100 ? 'violet' : 'blue'" size="sm">
                    {{ $user->role?->displayName() }}
                </flux:badge>
                @if (! $user->is_active)
                    <flux:badge color="zinc" size="sm">{{ __('Inactif') }}</flux:badge>
                @endif
            </div>
        </div>
    </div>

    @if (! $user->is_active)
        @can('update', $user)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            {{ __('Cet employé est inactif. Pour le réactiver, mettez à jour son identification, puis cochez « Actif » dans l\'onglet Compte et sauvegardez.') }}
        </div>
        @endcan
    @endif

    {{-- Onglets --}}
    <div x-data="{ tab: @entangle('activeTab') }">
        <div class="flex border-b border-zinc-200 dark:border-zinc-700">
            @can('update', $user)
                <button
                    type="button"
                    @click="tab = 'identification'"
                    :class="tab === 'identification' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ __('Identification') }}</button>
            @endcan

            @can('viewHr', $user)
                <button
                    type="button"
                    @click="tab = 'hr'"
                    :class="tab === 'hr' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ __('RH') }}</button>
            @endcan

            @can('update', $user)
                <button
                    type="button"
                    @click="tab = 'address'"
                    :class="tab === 'address' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ __('Adresse') }}</button>
            @endcan

            @can('update', $user)
                <button
                    type="button"
                    @click="tab = 'account'"
                    :class="tab === 'account' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ __('Compte') }}</button>
            @endcan

            @can('assignPermissions', $user)
                <button
                    type="button"
                    @click="tab = 'access'"
                    :class="tab === 'access' ? 'border-b-2 border-zinc-900 text-zinc-900 dark:border-white dark:text-white' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                    class="-mb-px px-4 py-3 text-sm font-medium transition-colors"
                >{{ __('Accès') }}</button>
            @endcan
        </div>

        @can('update', $user)
            {{-- Identification --}}
            <div x-show="tab === 'identification'">
                <form wire:submit="saveIdentification" class="mt-6 max-w-2xl space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Prénom') }}</flux:label>
                            <flux:input wire:model="firstname" type="text" required />
                            <flux:error name="firstname" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Nom') }}</flux:label>
                            <flux:input wire:model="lastname" type="text" required />
                            <flux:error name="lastname" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Rôle') }}</flux:label>
                        <flux:select wire:model="role">
                            @foreach ($this->getRoles() as $roleOption)
                                <flux:select.option :value="$roleOption->name">{{ $roleOption->displayName() }}</flux:select.option>
                            @endforeach
                            @unless ($this->getRoles()->contains('name', $role))
                                <flux:select.option :value="$role">{{ $user->role?->displayName() }}</flux:select.option>
                            @endunless
                        </flux:select>
                        <flux:error name="role" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Position') }}</flux:label>
                        <flux:select wire:model="positionId">
                            <flux:select.option value="">{{ __('Aucune') }}</flux:select.option>
                            @foreach ($this->getPositions() as $position)
                                <flux:select.option :value="$position->id">{{ $position->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="positionId" />
                    </flux:field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Premier jour') }}</flux:label>
                            <flux:input wire:model="firstDay" type="date" required />
                            <flux:error name="firstDay" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Dernier jour') }}</flux:label>
                            <flux:input wire:model="lastDay" type="date" />
                            <flux:error name="lastDay" />
                        </flux:field>
                    </div>

                    <flux:field>
                        <flux:label>{{ __('Courriel personnel') }}</flux:label>
                        <flux:input wire:model="personalEmail" type="email" placeholder="prenom.nom@exemple.com" />
                        <flux:error name="personalEmail" />
                    </flux:field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-phone-input wire:model="phone" label="{{ __('Téléphone') }}" name="phone" />
                        <x-phone-input wire:model="cellphone" label="{{ __('Cellulaire') }}" name="cellphone" />
                    </div>

                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    </div>
                </form>
            </div>
        @endcan

        @can('viewHr', $user)
            {{-- RH --}}
            <div x-show="tab === 'hr'" x-cloak>
                <form wire:submit="saveHr" class="mt-6 max-w-2xl space-y-6">
                    <fieldset @disabled(auth()->user()->cannot('editHr', $user)) class="space-y-6">
                    <flux:field variant="inline">
                        <flux:checkbox wire:model="isFullTime" :label="__('Temps plein')" />
                        <flux:error name="isFullTime" />
                    </flux:field>

                    <div class="space-y-4">
                        <flux:field variant="inline">
                            <flux:checkbox wire:model.live="hasGroupInsurance" :label="__('Assurances collectives')" />
                            <flux:error name="hasGroupInsurance" />
                        </flux:field>

                        @if ($hasGroupInsurance)
                            <flux:field>
                                <flux:label>{{ __('Type d\'assurance') }}</flux:label>
                                <flux:select wire:model="insurancePlan" required>
                                    <flux:select.option value="">{{ __('Choisir…') }}</flux:select.option>
                                    @foreach ($this->getInsurancePlans() as $plan)
                                        <flux:select.option :value="$plan->value">{{ $plan->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="insurancePlan" />
                            </flux:field>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <flux:field variant="inline">
                            <flux:checkbox wire:model.live="isSalaried" :label="__('Salarié')" />
                            <flux:error name="isSalaried" />
                        </flux:field>

                        @if ($isSalaried)
                            <flux:field>
                                <flux:label>{{ __('Salaire hebdomadaire') }}</flux:label>
                                <flux:input wire:model="weeklySalary" type="text" inputmode="decimal" autocomplete="off" icon="currency-dollar" x-data x-on:input.capture="$el.value = $el.value.replace(/,/g, '.').replace(/[^\d.]/g, '').replace(/^(\d*\.?)(.*)$/, (m, a, b) => a + b.replace(/\./g, '')).replace(/(\.\d{2}).*/, '$1')" />
                                <flux:error name="weeklySalary" />
                            </flux:field>
                        @else
                            <flux:field>
                                <flux:label>{{ __('Taux horaire') }}</flux:label>
                                <flux:input wire:model="hourlyRate" type="text" inputmode="decimal" autocomplete="off" icon="currency-dollar" x-data x-on:input.capture="$el.value = $el.value.replace(/,/g, '.').replace(/[^\d.]/g, '').replace(/^(\d*\.?)(.*)$/, (m, a, b) => a + b.replace(/\./g, '')).replace(/(\.\d{2}).*/, '$1')" />
                                <flux:error name="hourlyRate" />
                            </flux:field>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <flux:field variant="inline">
                            <flux:checkbox wire:model.live="hasCommission" :label="__('Avec commission')" />
                            <flux:error name="hasCommission" />
                        </flux:field>

                        @if ($hasCommission)
                            <flux:field>
                                <flux:label>{{ __('Commission (%)') }}</flux:label>
                                <flux:input wire:model="commissionRate" type="text" inputmode="decimal" autocomplete="off" x-data x-on:input.capture="$el.value = $el.value.replace(/,/g, '.').replace(/[^\d.]/g, '').replace(/^(\d*\.?)(.*)$/, (m, a, b) => a + b.replace(/\./g, '')).replace(/(\.\d{2}).*/, '$1')" />
                                <flux:error name="commissionRate" />
                            </flux:field>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <flux:field variant="inline">
                            <flux:checkbox wire:model.live="hasBonus" :label="__('Avec primes')" />
                            <flux:error name="hasBonus" />
                        </flux:field>

                        @if ($hasBonus)
                            <div class="grid gap-4 sm:grid-cols-3">
                                <flux:field>
                                    <flux:label>{{ __('Ventes hebdo avant prime ($)') }}</flux:label>
                                    <flux:input wire:model="weeklySalesTarget" type="text" inputmode="numeric" autocomplete="off" icon="currency-dollar" x-data x-on:input.capture="$el.value = $el.value.replace(/\D/g, '')" />
                                    <flux:error name="weeklySalesTarget" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Prime ($)') }}</flux:label>
                                    <flux:input wire:model="bonusAmount" type="text" inputmode="numeric" autocomplete="off" icon="currency-dollar" x-data x-on:input.capture="$el.value = $el.value.replace(/\D/g, '')" />
                                    <flux:error name="bonusAmount" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Par tranche de ($)') }}</flux:label>
                                    <flux:input wire:model="bonusStep" type="text" inputmode="numeric" autocomplete="off" icon="currency-dollar" x-data x-on:input.capture="$el.value = $el.value.replace(/\D/g, '')" />
                                    <flux:error name="bonusStep" />
                                </flux:field>
                            </div>
                            <flux:text class="text-sm text-zinc-500">
                                {{ __('Une prime est versée pour chaque tranche dépassant l\'objectif de ventes hebdomadaires.') }}
                            </flux:text>
                        @endif
                    </div>

                    <div class="space-y-4">
                        <flux:heading size="sm">{{ __('Vacances') }}</flux:heading>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Jours de vacances cumulés') }}</flux:label>
                                <flux:input :value="$vacationDaysAccrued" readonly disabled />
                                <flux:description>
                                    {{ __('Calculé depuis le :date selon l\'ancienneté et l\'horaire.', ['date' => \Illuminate\Support\Carbon::parse($vacationReferenceStart)->translatedFormat('j F Y')]) }}
                                </flux:description>
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Heures par jour de vacances') }}</flux:label>
                                <flux:input wire:model="vacationHoursPerDay" type="text" inputmode="decimal" autocomplete="off" :placeholder="config('vacations.default_hours_per_day')" x-data x-on:input.capture="$el.value = $el.value.replace(/,/g, '.').replace(/[^\d.]/g, '').replace(/^(\d*\.?)(.*)$/, (m, a, b) => a + b.replace(/\./g, '')).replace(/(\.\d{2}).*/, '$1')" />
                                <flux:description>{{ __('Vide : :hours h par défaut.', ['hours' => config('vacations.default_hours_per_day')]) }}</flux:description>
                                <flux:error name="vacationHoursPerDay" />
                            </flux:field>
                        </div>
                    </div>

                    </fieldset>

                    @can('editHr', $user)
                        <div class="flex justify-end pt-2">
                            <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                        </div>
                    @endcan
                </form>
            </div>
        @endcan

        @can('update', $user)
            {{-- Adresse --}}
            <div x-show="tab === 'address'" x-cloak>
                <form wire:submit="saveAddress" class="mt-6 max-w-2xl space-y-6">
                    <x-address-input prefix="address" :current-country="$address['country'] ?? 'CA'" />

                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    </div>
                </form>
            </div>
        @endcan

        @can('update', $user)
            {{-- Compte --}}
            <div x-show="tab === 'account'" x-cloak>
                <div class="mt-6 max-w-2xl space-y-6">
                    <div class="space-y-1.5 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">{{ __("Nom d'utilisateur") }}</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $user->username }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-zinc-500">{{ __('Courriel') }}</span>
                            <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $user->email }}</span>
                        </div>
                    </div>

                    <form wire:submit="saveAccount" class="space-y-4">
                        <flux:field>
                            <flux:checkbox wire:model="isActive" :label="__('Actif')" />
                            <flux:error name="isActive" />
                        </flux:field>

                        <div class="flex justify-end">
                            <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                        </div>
                    </form>

                    {{-- Réinitialisation du mot de passe --}}
                    <div class="border-t border-zinc-200 pt-5 dark:border-zinc-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <flux:heading size="sm">{{ __('Mot de passe') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500">{{ __('Générer un nouveau mot de passe sécuritaire') }}</flux:text>
                            </div>
                            <flux:button
                                type="button"
                                variant="danger"
                                icon="key"
                                wire:click="resetPassword"
                                wire:confirm="{{ __('Réinitialiser le mot de passe de cet utilisateur ?') }}"
                            >
                                {{ __('Réinitialiser') }}
                            </flux:button>
                        </div>

                        @if ($generatedPassword)
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
                                <div class="mb-2 flex items-center gap-2">
                                    <flux:icon name="exclamation-triangle" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                    <flux:text class="text-sm font-medium text-amber-800 dark:text-amber-300">
                                        {{ __('Notez ce mot de passe, il ne sera plus affiché.') }}
                                    </flux:text>
                                </div>
                                <div class="flex items-center justify-between rounded-md border border-amber-300 bg-white px-3 py-2 dark:border-amber-600 dark:bg-zinc-900">
                                    <code class="text-base font-mono font-semibold tracking-wider text-zinc-800 dark:text-zinc-100">{{ $generatedPassword }}</code>
                                    <flux:button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        icon="clipboard"
                                        x-on:click="navigator.clipboard.writeText(@js($generatedPassword))"
                                    >
                                        {{ __('Copier') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endcan

        {{-- Accès --}}
        @can('assignPermissions', $user)
            <div x-show="tab === 'access'" x-cloak>
                <form wire:submit="saveAccess" class="mt-6 max-w-2xl space-y-6">
                    <flux:text class="text-sm text-zinc-500">
                        {{ __('Les permissions du rôle sont cochées par défaut. Cochez pour ajouter une permission, décochez pour en retirer une, même si elle vient du rôle. Vous ne pouvez modifier que les permissions que vous possédez.') }}
                    </flux:text>

                    @php
                        $fromRole = $this->permissionsFromRole();
                        $grantable = $this->grantablePermissions();
                        $denied = $user->deniedPermissionNames();
                    @endphp

                    @foreach ($this->getPermissions()->groupBy(fn ($permission) => $permission->group()) as $group => $permissions)
                        <div>
                            <flux:heading size="sm" class="mb-2">{{ \Illuminate\Support\Str::headline($group) }}</flux:heading>
                            <div class="space-y-2">
                                @foreach ($permissions as $permission)
                                    @php
                                        $viaRole = in_array($permission->name, $fromRole, true);
                                        $isDenied = $denied->contains($permission->name);
                                    @endphp
                                    <flux:checkbox
                                        wire:model="selectedPermissions"
                                        :value="$permission->name"
                                        :label="$permission->displayName()"
                                        :description="$viaRole ? ($isDenied ? __('Retirée à cet utilisateur (fournie par le rôle)') : __('Fournie par le rôle')) : $permission->description"
                                        :disabled="! in_array($permission->name, $grantable, true)"
                                    />
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-end pt-2">
                        <flux:button type="submit" variant="primary">{{ __('Sauvegarder') }}</flux:button>
                    </div>
                </form>
            </div>
        @endcan
    </div>
</div>

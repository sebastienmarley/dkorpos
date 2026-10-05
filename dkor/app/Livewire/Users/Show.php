<?php

namespace App\Livewire\Users;

use App\Actions\CalculateVacationBalance;
use App\Actions\PurgeSchedulesAfterLastDay;
use App\Enums\InsurancePlan;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    public User $user;

    #[Url(as: 'tab')]
    public string $activeTab = 'identification';

    // Identification
    public string $firstname = '';

    public string $lastname = '';

    public string $role = 'salesman';

    public ?int $positionId = null;

    public ?int $storeId = null;

    public ?string $firstDay = null;

    public ?string $lastDay = null;

    public ?string $personalEmail = null;

    public ?string $phone = null;

    public ?string $cellphone = null;

    // RH
    public bool $isFullTime = false;

    public bool $hasGroupInsurance = false;

    public ?string $insurancePlan = null;

    public bool $isSalaried = false;

    public ?string $hourlyRate = null;

    public ?string $weeklySalary = null;

    public bool $hasCommission = false;

    public ?string $commissionRate = null;

    public bool $hasBonus = false;

    public ?string $weeklySalesTarget = null;

    public ?string $bonusAmount = null;

    public ?string $bonusStep = null;

    public ?string $hoursPerDay = null;

    /** Jours de vacances cumulés dans l'année de référence (calculés, lecture seule). */
    public ?string $vacationDaysAccrued = null;

    public ?string $vacationReferenceStart = null;

    // Adresse
    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $address = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    // Compte
    public bool $isActive = true;

    public ?string $generatedPassword = null;

    // Accès : permissions effectives (celles du rôle, plus les ajouts, moins les retraits)
    /** @var array<int, string> */
    public array $selectedPermissions = [];

    public function mount(User $user): void
    {
        $viewer = Auth::user();

        if ($viewer->is($user) && $viewer->cannot('update', $user) && $viewer->can('users.edit')) {
            session()->flash('toast-error', __('Vous ne pouvez pas modifier votre propre fiche.'));
            $this->redirectRoute('users.index', navigate: true);

            return;
        }

        abort_unless($viewer->canSeeRole($user->role), 404);

        abort_unless($viewer->can('update', $user) || $viewer->can('viewHr', $user), 403);

        $this->user = $user;

        if ($viewer->cannot('update', $user)) {
            $this->activeTab = 'hr';
        }

        $this->fillFromModel();
    }

    public function saveIdentification(): void
    {
        $this->authorize('update', $this->user);

        $validated = $this->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:'.$this->allowedRoleNames()->implode(',')],
            'positionId' => ['nullable', 'integer', 'exists:positions,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'firstDay' => ['required', 'date'],
            'lastDay' => ['nullable', 'date', 'after_or_equal:firstDay'],
            'personalEmail' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'cellphone' => ['required', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
        ]);

        $this->user->fill([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'position_id' => $validated['positionId'],
            'store_id' => $validated['storeId'],
            'first_day' => $validated['firstDay'],
            'last_day' => filled($validated['lastDay']) ? $validated['lastDay'] : null,
            'personal_email' => filled($validated['personalEmail']) ? $validated['personalEmail'] : null,
            'phone' => filled($validated['phone']) ? $validated['phone'] : null,
            'cellphone' => $validated['cellphone'],
        ])->markModifiedBy(Auth::user())->save();

        if ($this->user->role?->name !== $validated['role']) {
            $this->user->syncRoles($validated['role']);
        }

        (new PurgeSchedulesAfterLastDay)->execute($this->user);

        // Le premier et le dernier jour changent l'ancienneté et la période d'acquisition des vacances.
        app(CalculateVacationBalance::class)->refresh($this->user);

        Flux::toast(text: __('Identification sauvegardée.'), variant: 'success');
    }

    public function updatedHasGroupInsurance(bool $value): void
    {
        if (! $value) {
            $this->insurancePlan = null;
        }
    }

    public function updatedHasCommission(bool $value): void
    {
        if (! $value) {
            $this->commissionRate = null;
        }
    }

    public function updatedHasBonus(bool $value): void
    {
        if (! $value) {
            $this->reset(['weeklySalesTarget', 'bonusAmount', 'bonusStep']);
        }
    }

    public function saveHr(): void
    {
        $this->authorize('editHr', $this->user);

        $validated = $this->validate([
            'isFullTime' => ['boolean'],
            'hasGroupInsurance' => ['boolean'],
            'insurancePlan' => [Rule::requiredIf($this->hasGroupInsurance), 'nullable', Rule::enum(InsurancePlan::class)],
            'isSalaried' => ['boolean'],
            'hourlyRate' => ['nullable', 'numeric', 'regex:/^\d{1,4}(\.\d{1,2})?$/', 'gt:0'],
            'weeklySalary' => ['nullable', 'numeric', 'regex:/^\d{1,5}(\.\d{1,2})?$/', 'gt:0'],
            'hasCommission' => ['boolean'],
            'commissionRate' => [Rule::requiredIf($this->hasCommission), 'nullable', 'numeric', 'regex:/^\d{1,3}(\.\d{1,2})?$/', 'gt:0', 'max:100'],
            'hasBonus' => ['boolean'],
            'weeklySalesTarget' => [Rule::requiredIf($this->hasBonus), 'nullable', 'integer', 'regex:/^\d{1,7}$/', 'gt:0'],
            'bonusAmount' => [Rule::requiredIf($this->hasBonus), 'nullable', 'integer', 'regex:/^\d{1,7}$/', 'gt:0'],
            'bonusStep' => [Rule::requiredIf($this->hasBonus), 'nullable', 'integer', 'regex:/^\d{1,7}$/', 'gt:0'],
            'hoursPerDay' => ['nullable', 'numeric', 'regex:/^\d{1,2}(\.\d{1,2})?$/', 'gt:0', 'max:24'],
        ], [
            'hoursPerDay.*' => __('Entrez un nombre d\'heures entre 0 et 24, avec au plus 2 décimales.'),
            'hourlyRate.regex' => __('Entrez un montant valide avec au plus 2 décimales (chiffres seulement).'),
            'weeklySalary.regex' => __('Entrez un montant valide avec au plus 2 décimales (chiffres seulement).'),
            'commissionRate.regex' => __('Entrez un pourcentage valide avec au plus 2 décimales (chiffres seulement).'),
            'commissionRate.gt' => __('La commission doit être supérieure à 0.'),
            'weeklySalesTarget.*' => __('Entrez un montant entier supérieur à 0 (chiffres seulement).'),
            'bonusAmount.*' => __('Entrez un montant entier supérieur à 0 (chiffres seulement).'),
            'bonusStep.*' => __('Entrez un montant entier supérieur à 0 (chiffres seulement).'),
            'hourlyRate.gt' => __('Le taux horaire doit être supérieur à 0.'),
            'weeklySalary.gt' => __('Le salaire hebdomadaire doit être supérieur à 0.'),
        ]);

        $this->user->fill([
            'is_full_time' => $validated['isFullTime'],
            'has_group_insurance' => $validated['hasGroupInsurance'],
            'insurance_plan' => $validated['hasGroupInsurance'] ? $validated['insurancePlan'] : null,
            'is_salaried' => $validated['isSalaried'],
            // Taux horaire et salaire hebdomadaire sont mutuellement exclusifs.
            'hourly_rate' => $validated['isSalaried'] || blank($validated['hourlyRate']) ? null : $validated['hourlyRate'],
            'weekly_salary' => ! $validated['isSalaried'] || blank($validated['weeklySalary']) ? null : $validated['weeklySalary'],
            'has_commission' => $validated['hasCommission'],
            'commission_rate' => $validated['hasCommission'] ? $validated['commissionRate'] : null,
            'has_bonus' => $validated['hasBonus'],
            'weekly_sales_target' => $validated['hasBonus'] ? (int) $validated['weeklySalesTarget'] : null,
            'bonus_amount' => $validated['hasBonus'] ? (int) $validated['bonusAmount'] : null,
            'bonus_step' => $validated['hasBonus'] ? (int) $validated['bonusStep'] : null,
            'hours_per_day' => filled($validated['hoursPerDay']) ? $validated['hoursPerDay'] : null,
        ])->markModifiedBy(Auth::user())->save();

        app(CalculateVacationBalance::class)->refresh($this->user);

        $this->fillFromModel();

        Flux::toast(text: __('Informations RH sauvegardées.'), variant: 'success');
    }

    /** @return array<int, InsurancePlan> */
    public function getInsurancePlans(): array
    {
        return InsurancePlan::cases();
    }

    public function updatedAddressCountry(): void
    {
        $this->address['province'] = '';
    }

    public function saveAddress(): void
    {
        $this->authorize('update', $this->user);

        $this->validate([
            'address.civic' => ['nullable', 'string', 'max:20'],
            'address.apartment' => ['nullable', 'string', 'max:20'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.province' => ['nullable', 'string', 'size:2'],
            'address.country' => ['nullable', 'string', 'in:CA,US'],
            'address.postal_code' => ['nullable', 'string', 'max:6'],
        ]);

        $this->user->fill([
            'address_civic' => filled($this->address['civic']) ? $this->address['civic'] : null,
            'address_apartment' => filled($this->address['apartment']) ? $this->address['apartment'] : null,
            'address_street' => filled($this->address['street']) ? $this->address['street'] : null,
            'address_city' => filled($this->address['city']) ? $this->address['city'] : null,
            'address_province' => filled($this->address['province']) ? $this->address['province'] : null,
            'address_country' => filled($this->address['country']) ? $this->address['country'] : null,
            'address_postal_code' => filled($this->address['postal_code']) ? $this->address['postal_code'] : null,
        ])->markModifiedBy(Auth::user())->save();

        Flux::toast(text: __('Adresse sauvegardée.'), variant: 'success');
    }

    public function saveAccount(): void
    {
        $this->authorize('update', $this->user);

        $this->user->is_active = $this->isActive;
        $this->user->markModifiedBy(Auth::user())->save();

        Flux::toast(text: __('Compte sauvegardé.'), variant: 'success');
    }

    public function resetPassword(): void
    {
        $this->authorize('update', $this->user);

        $plainPassword = Str::password(12);

        $this->user->password = Hash::make($plainPassword);
        $this->user->markModifiedBy(Auth::user())->save();

        $this->generatedPassword = $plainPassword;
    }

    public function saveAccess(): void
    {
        $this->authorize('assignPermissions', $this->user);

        $actor = Auth::user();
        $grantable = $actor->getAllPermissions()->pluck('name');
        $fromRole = $this->user->getPermissionsViaRoles()->pluck('name');
        $selected = collect($this->selectedPermissions);

        $direct = $this->user->getDirectPermissions()->pluck('name');
        $denied = $this->user->deniedPermissionNames();

        // On ne touche qu'aux permissions que l'acteur possède lui-même : les autres restent telles quelles.
        foreach (Permission::pluck('name') as $name) {
            if (! $grantable->contains($name)) {
                continue;
            }

            $direct = $direct->reject(fn (string $n) => $n === $name);
            $denied = $denied->reject(fn (string $n) => $n === $name);

            if ($selected->contains($name)) {
                if (! $fromRole->contains($name)) {
                    $direct->push($name);
                }
            } elseif ($fromRole->contains($name)) {
                $denied->push($name);
            }
        }

        $this->user->syncPermissions($direct->unique()->values()->all());
        $this->user->syncDeniedPermissions($denied->unique()->values()->all());
        $this->user->markModifiedBy($actor)->save();

        $this->fillFromModel();

        Flux::toast(text: __('Accès sauvegardés.'), variant: 'success');
    }

    /**
     * Rôles offerts dans le formulaire : ceux que l'utilisateur connecté peut attribuer.
     *
     * @return Collection<int, Role>
     */
    public function getRoles(): Collection
    {
        return Auth::user()->assignableRoles();
    }

    /** @return Collection<int, Store> */
    public function getStores(): Collection
    {
        return Store::query()->where('is_active', true)->orWhere('id', $this->user->store_id)->orderBy('name')->get();
    }

    /** @return Collection<int, Position> */
    public function getPositions(): Collection
    {
        return Position::query()->orderBy('name')->get();
    }

    /** @return Collection<int, Permission> */
    public function getPermissions(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    /**
     * Noms des permissions que l'utilisateur affiché reçoit déjà de son rôle.
     *
     * @return array<int, string>
     */
    public function permissionsFromRole(): array
    {
        return $this->user->getPermissionsViaRoles()->pluck('name')->all();
    }

    /**
     * Noms des permissions que l'utilisateur connecté peut accorder (celles qu'il possède).
     *
     * @return array<int, string>
     */
    public function grantablePermissions(): array
    {
        return Auth::user()->getAllPermissions()->pluck('name')->all();
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function allowedRoleNames(): \Illuminate\Support\Collection
    {
        return $this->getRoles()->pluck('name')->push($this->user->role?->name)->filter()->unique();
    }

    public function render(): View
    {
        return view('livewire.users.show')
            ->layout('layouts.app', ['title' => $this->user->fullName()]);
    }

    private function fillFromModel(): void
    {
        $this->firstname = $this->user->firstname;
        $this->lastname = $this->user->lastname;
        $this->role = (string) $this->user->role?->name;
        $this->positionId = $this->user->position_id;
        $this->storeId = $this->user->store_id;
        $this->selectedPermissions = $this->user->getAllPermissions()->pluck('name')->all();
        $this->firstDay = $this->user->first_day?->format('Y-m-d');
        $this->lastDay = $this->user->last_day?->format('Y-m-d');
        $this->personalEmail = $this->user->personal_email;
        $this->phone = $this->user->phone;
        $this->cellphone = $this->user->cellphone;
        // Les informations RH ne sont chargées (donc envoyées au navigateur) que pour qui a le droit de les voir.
        if (Auth::user()->can('viewHr', $this->user)) {
            $this->isFullTime = $this->user->is_full_time;
            $this->hasGroupInsurance = $this->user->has_group_insurance;
            $this->insurancePlan = $this->user->insurance_plan?->value;
            $this->isSalaried = $this->user->is_salaried;
            $this->hourlyRate = $this->user->hourly_rate;
            $this->weeklySalary = $this->user->weekly_salary;
            $this->hasCommission = $this->user->has_commission;
            $this->commissionRate = $this->user->commission_rate;
            $this->hasBonus = $this->user->has_bonus;
            $this->weeklySalesTarget = $this->user->weekly_sales_target === null ? null : (string) $this->user->weekly_sales_target;
            $this->bonusAmount = $this->user->bonus_amount === null ? null : (string) $this->user->bonus_amount;
            $this->bonusStep = $this->user->bonus_step === null ? null : (string) $this->user->bonus_step;
            $this->hoursPerDay = $this->user->hours_per_day;

            $balance = app(CalculateVacationBalance::class)->calculate($this->user);
            $this->vacationDaysAccrued = number_format($balance['days_accrued'], 2, '.', '');
            $this->vacationReferenceStart = $balance['reference_start']->toDateString();
        }

        $this->address = [
            'civic' => $this->user->address_civic ?? '',
            'apartment' => $this->user->address_apartment ?? '',
            'street' => $this->user->address_street ?? '',
            'city' => $this->user->address_city ?? '',
            'province' => $this->user->address_province ?? '',
            'country' => $this->user->address_country ?? 'CA',
            'postal_code' => $this->user->address_postal_code ?? '',
        ];
        $this->isActive = $this->user->is_active;
    }
}

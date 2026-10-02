<?php

namespace App\Livewire\Users;

use App\Actions\PurgeSchedulesAfterLastDay;
use App\Models\Permission;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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

    public ?string $firstDay = null;

    public ?string $lastDay = null;

    public ?string $personalEmail = null;

    public ?string $phone = null;

    public ?string $cellphone = null;

    // Adresse
    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $address = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    // Compte
    public bool $isActive = true;

    public ?string $generatedPassword = null;

    // Accès : permissions supplémentaires (en plus de celles du rôle)
    /** @var array<int, string> */
    public array $extraPermissions = [];

    public function mount(User $user): void
    {
        $viewer = Auth::user();

        if ($viewer->is($user) && $viewer->cannot('update', $user) && $viewer->can('users.edit')) {
            session()->flash('toast-error', __('Vous ne pouvez pas modifier votre propre fiche.'));
            $this->redirectRoute('users.index', navigate: true);

            return;
        }

        abort_unless($viewer->canSeeRole($user->role), 404);

        $this->authorize('update', $user);

        $this->user = $user;
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

        Flux::toast(text: __('Identification sauvegardée.'), variant: 'success');
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

        $kept = $this->user->getDirectPermissions()->pluck('name')->reject(fn (string $name) => $grantable->contains($name));
        $added = collect($this->extraPermissions)->intersect($grantable)->diff($fromRole);

        $this->user->syncPermissions($kept->merge($added)->unique()->values()->all());
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
        $this->extraPermissions = $this->user->getDirectPermissions()->pluck('name')->all();
        $this->firstDay = $this->user->first_day?->format('Y-m-d');
        $this->lastDay = $this->user->last_day?->format('Y-m-d');
        $this->personalEmail = $this->user->personal_email;
        $this->phone = $this->user->phone;
        $this->cellphone = $this->user->cellphone;
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

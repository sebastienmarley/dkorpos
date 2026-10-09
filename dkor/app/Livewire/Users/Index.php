<?php

namespace App\Livewire\Users;

use App\Models\Position;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public string $sortRole = '';

    public string $search = '';

    /** Valeurs possibles : active, inactive, all. */
    public string $statusFilter = 'active';

    public bool $showCredentialsModal = false;

    public string $createdEmail = '';

    public ?string $createdPassword = null;

    public bool $showCreateModal = false;

    // Champs du formulaire de création
    public string $firstname = '';

    public string $lastname = '';

    public string $role = 'salesman';

    public ?int $positionId = null;

    public ?int $storeId = null;

    public ?string $first_day = null;

    public string $username = '';

    public string $generatedEmail = '';

    public ?string $personalEmail = null;

    public ?string $cellphone = null;

    public bool $showDuplicatePrompt = false;

    /** @var array<int, int> Identifiants des employés inactifs portant le même nom. */
    public array $inactiveMatchIds = [];

    /** Réponse à « nouvel employé ou retour ? » : null tant qu'on n'a pas répondu, sinon « new ». */
    public ?string $employeeChoice = null;

    public ?User $existingUser = null;

    public function updatedFirstname(): void
    {
        $this->employeeChoice = null;
        $this->syncUsername();
    }

    public function updatedLastname(): void
    {
        $this->employeeChoice = null;
        $this->syncUsername();
    }

    public function syncUsername(): void
    {
        if (blank($this->firstname) || blank($this->lastname)) {
            $this->username = '';
            $this->generatedEmail = '';
            $this->showDuplicatePrompt = false;
            $this->existingUser = null;
            $this->inactiveMatchIds = [];

            return;
        }

        $this->inactiveMatchIds = User::inactiveMatchesForName($this->firstname, $this->lastname, Auth::user())->modelKeys();

        $resolved = User::resolveUsernameForEmployee($this->firstname, $this->lastname);
        $this->username = $resolved['username'];
        $this->generatedEmail = $resolved['username'].'@dkor.ca';
        $this->showDuplicatePrompt = $resolved['requiresVerification'] && $this->canSee($resolved['existingUser']);
        $this->existingUser = $this->showDuplicatePrompt ? $resolved['existingUser'] : null;
    }

    public function confirmNewEmployee(): void
    {
        $this->employeeChoice = 'new';
        $this->username = User::generateUniqueUsername($this->firstname, $this->lastname);
        $this->generatedEmail = $this->username.'@dkor.ca';
        $this->showDuplicatePrompt = false;
        $this->existingUser = null;
    }

    /**
     * Retour d'un ancien employé : fiche directe s'il n'y a qu'un seul candidat,
     * sinon liste filtrée sur les inactifs pour choisir le bon.
     */
    public function confirmReturningEmployee(): void
    {
        $matches = User::inactiveMatchesForName($this->firstname, $this->lastname, Auth::user());

        if ($matches->count() === 1) {
            $this->redirectRoute('users.show', ['user' => $matches->first()], navigate: true);

            return;
        }

        $this->search = trim($this->firstname.' '.$this->lastname);
        $this->statusFilter = 'inactive';
        $this->sortRole = '';
        $this->closeCreateModal();
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', User::class);

        $this->reset(['firstname', 'lastname', 'role', 'positionId', 'storeId', 'first_day', 'username', 'generatedEmail', 'personalEmail', 'cellphone', 'showDuplicatePrompt', 'existingUser', 'inactiveMatchIds', 'employeeChoice']);
        $this->role = $this->defaultRole();
        $this->showCreateModal = true;
    }

    public function save(): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:'.$this->assignableRoles()->pluck('name')->implode(',')],
            'positionId' => ['nullable', 'integer', 'exists:positions,id'],
            'storeId' => ['required', 'integer', 'exists:stores,id'],
            'first_day' => ['nullable', 'date'],
            'personalEmail' => ['nullable', 'email', 'max:255'],
            'cellphone' => ['required', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
        ]);

        $this->inactiveMatchIds = User::inactiveMatchesForName($this->firstname, $this->lastname, Auth::user())->modelKeys();

        if ($this->inactiveMatchIds !== [] && $this->employeeChoice !== 'new') {
            return;
        }

        $resolved = User::resolveUsernameForEmployee($this->firstname, $this->lastname);

        if ($resolved['requiresVerification']) {
            $this->showDuplicatePrompt = $this->canSee($resolved['existingUser']);
            $this->existingUser = $this->showDuplicatePrompt ? $resolved['existingUser'] : null;
            $this->username = User::generateUniqueUsername($this->firstname, $this->lastname);
            $this->generatedEmail = $this->username.'@dkor.ca';
            $resolved['username'] = $this->username;
        }

        $email = $resolved['username'].'@dkor.ca';

        if (User::where('email', $email)->exists()) {
            $this->addError('generatedEmail', __("L'adresse courriel :email est déjà utilisée.", ['email' => $email]));

            return;
        }

        $user = new User;
        $user->firstname = $validated['firstname'];
        $user->lastname = $validated['lastname'];
        $user->email = $email;
        $user->position_id = $validated['positionId'];
        $user->store_id = $validated['storeId'];
        $user->is_active = true;
        $user->first_day = $validated['first_day'];
        $user->personal_email = $validated['personalEmail'];
        $user->cellphone = $validated['cellphone'];
        $user->username = $resolved['username'];
        $plainPassword = Str::password(12);
        $user->password = $plainPassword;
        $user->markModifiedBy(Auth::user())->save();
        $user->assignRole($validated['role']);

        $this->closeCreateModal();
        $this->createdEmail = $email;
        $this->createdPassword = $plainPassword;
        $this->showCredentialsModal = true;

        $this->dispatch('user-created');
    }

    private function canSee(?User $user): bool
    {
        return $user !== null && Auth::user()->canSeeRole($user->role);
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->reset(['firstname', 'lastname', 'role', 'positionId', 'storeId', 'first_day', 'username', 'generatedEmail', 'personalEmail', 'cellphone', 'showDuplicatePrompt', 'existingUser', 'inactiveMatchIds', 'employeeChoice']);
        $this->role = $this->defaultRole();
    }

    public function closeCredentialsModal(): void
    {
        $this->reset(['showCredentialsModal', 'createdEmail', 'createdPassword']);
    }

    public function sortByRole(string $role): void
    {
        $this->sortRole = $this->sortRole === $role ? '' : $role;
    }

    /** @return Collection<int, Role> */
    public function assignableRoles(): Collection
    {
        return Auth::user()->assignableRoles();
    }

    /** @return Collection<int, Role> */
    public function visibleRoles(): Collection
    {
        return Auth::user()->visibleRoles();
    }

    /** @return Collection<int, Store> */
    public function stores(): Collection
    {
        return Store::query()->where('is_active', true)->orderBy('name')->get();
    }

    /** @return Collection<int, Position> */
    public function positions(): Collection
    {
        return Position::query()->orderBy('name')->get();
    }

    private function defaultRole(): string
    {
        $assignable = $this->assignableRoles();

        return $assignable->contains('name', 'salesman') ? 'salesman' : (string) $assignable->last()?->name;
    }

    public function render(): View
    {
        $query = User::query()->with(['roles', 'position'])->visibleTo(Auth::user());

        match ($this->statusFilter) {
            'inactive' => $query->where('is_active', false),
            'all' => null,
            default => $query->where('is_active', true),
        };

        if (filled($this->search)) {
            $query->matchingName($this->search);
        }

        if (filled($this->sortRole)) {
            $query->whereHas('roles', fn ($q) => $q->where('roles.name', $this->sortRole));
        }

        $users = $query->orderBy('lastname')->orderBy('firstname')->get();

        return view('livewire.users.index', [
            'users' => $users,
        ])->layout('layouts.app', ['title' => __('Utilisateurs')]);
    }
}

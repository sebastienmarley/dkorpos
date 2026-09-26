<?php

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Component;

class Index extends Component
{
    public string $sortRole = '';

    public bool $showCreateModal = false;

    // Champs du formulaire de création
    public string $firstname = '';

    public string $lastname = '';

    public string $role = 'user';

    public ?string $first_day = null;

    public string $username = '';

    public string $generatedEmail = '';

    public ?string $personalEmail = null;

    public bool $showDuplicatePrompt = false;

    public ?User $existingUser = null;

    // Champs du formulaire de modification
    public bool $showEditModal = false;

    public ?int $editingUserId = null;

    public string $editFirstname = '';

    public string $editLastname = '';

    public string $editRole = 'user';

    public ?string $editFirstDay = null;

    public ?string $editLastDay = null;

    public ?string $editPersonalEmail = null;

    public string $editUsername = '';

    public string $editEmail = '';

    public bool $editIsActive = true;

    public function updatedFirstname(): void
    {
        $this->syncUsername();
    }

    public function updatedLastname(): void
    {
        $this->syncUsername();
    }

    public function syncUsername(): void
    {
        if (blank($this->firstname) || blank($this->lastname)) {
            $this->username = '';
            $this->generatedEmail = '';
            $this->showDuplicatePrompt = false;
            $this->existingUser = null;

            return;
        }

        $resolved = User::resolveUsernameForEmployee($this->firstname, $this->lastname);
        $this->username = $resolved['username'];
        $this->generatedEmail = $resolved['username'].'@dkor.ca';
        $this->existingUser = $resolved['existingUser'];
        $this->showDuplicatePrompt = $resolved['requiresVerification'];
    }

    public function confirmNewEmployee(): void
    {
        $this->username = User::generateUniqueUsername($this->firstname, $this->lastname);
        $this->generatedEmail = $this->username.'@dkor.ca';
        $this->showDuplicatePrompt = false;
        $this->existingUser = null;
    }

    public function openCreateModal(): void
    {
        $this->reset(['firstname', 'lastname', 'role', 'first_day', 'username', 'generatedEmail', 'personalEmail', 'showDuplicatePrompt', 'existingUser']);
        $this->role = 'user';
        $this->showCreateModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'first_day' => ['nullable', 'date'],
            'personalEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $resolved = User::resolveUsernameForEmployee($this->firstname, $this->lastname);

        if ($resolved['requiresVerification']) {
            $this->showDuplicatePrompt = true;
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
        $user->role = $validated['role'];
        $user->is_active = true;
        $user->first_day = $validated['first_day'];
        $user->personal_email = $validated['personalEmail'];
        $user->username = $resolved['username'];
        $user->password = bcrypt('password');
        $user->save();

        $this->showCreateModal = false;
        $this->reset(['firstname', 'lastname', 'role', 'first_day', 'username', 'generatedEmail', 'personalEmail', 'showDuplicatePrompt', 'existingUser']);
        $this->role = 'user';

        $this->dispatch('user-created');
    }

    public function openEditModal(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->editingUserId = $userId;
        $this->editFirstname = $user->firstname;
        $this->editLastname = $user->lastname;
        $this->editRole = $user->role;
        $this->editFirstDay = $user->first_day?->format('Y-m-d');
        $this->editLastDay = $user->last_day?->format('Y-m-d');
        $this->editPersonalEmail = $user->personal_email;
        $this->editUsername = $user->username;
        $this->editEmail = $user->email;
        $this->editIsActive = $user->is_active;
        $this->showEditModal = true;
    }

    public function update(): void
    {
        $validated = $this->validate([
            'editFirstname' => ['required', 'string', 'max:255'],
            'editLastname' => ['required', 'string', 'max:255'],
            'editRole' => ['required', 'string', 'max:255'],
            'editFirstDay' => ['nullable', 'date'],
            'editLastDay' => ['nullable', 'date'],
            'editPersonalEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $user = User::findOrFail($this->editingUserId);
        $user->firstname = $validated['editFirstname'];
        $user->lastname = $validated['editLastname'];
        $user->role = $validated['editRole'];
        $user->first_day = $validated['editFirstDay'];
        $user->last_day = $validated['editLastDay'];
        $user->personal_email = $validated['editPersonalEmail'];
        $user->is_active = $this->editIsActive;
        $user->save();

        $this->showEditModal = false;
        $this->reset(['editingUserId', 'editFirstname', 'editLastname', 'editRole', 'editFirstDay', 'editLastDay', 'editPersonalEmail', 'editUsername', 'editEmail', 'editIsActive']);
        $this->editRole = 'user';
    }

    public function sortByRole(string $role): void
    {
        $this->sortRole = ($this->sortRole === $role) ? '' : $role;
    }

    public function render()
    {
        $query = User::query()->where('is_active', true);

        if (filled($this->sortRole)) {
            $query->where('role', $this->sortRole);
        }

        $users = $query->orderBy('lastname')->orderBy('firstname')->get();

        $roles = User::query()
            ->where('is_active', true)
            ->distinct()
            ->pluck('role')
            ->sort()
            ->values();

        return view('livewire.users.index', [
            'users' => $users,
            'roles' => $roles,
        ])->layout('layouts.app', ['title' => __('Utilisateurs')]);
    }
}

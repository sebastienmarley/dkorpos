<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserForm extends Component
{
    public ?User $user = null;

    public string $firstname = '';

    public string $lastname = '';

    public string $email = '';

    public string $role = 'user';

    public bool $is_active = true;

    public ?string $first_day = null;

    public ?string $last_day = null;

    public string $username = '';

    public bool $showDuplicatePrompt = false;

    public ?User $existingUser = null;

    public function mount(?User $user = null): void
    {
        $this->user = $user;

        if ($this->user) {
            $this->firstname = $this->user->firstname;
            $this->lastname = $this->user->lastname;
            $this->email = $this->user->email;
            $this->role = $this->user->role ?? 'user';
            $this->is_active = (bool) $this->user->is_active;
            $this->first_day = $this->user->first_day?->toDateString();
            $this->last_day = $this->user->last_day?->toDateString();
            $this->username = $this->user->username;
        }
    }

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
        if ($this->user) {
            $this->username = $this->user->username;

            return;
        }

        if (blank($this->firstname) || blank($this->lastname)) {
            $this->username = '';
            $this->showDuplicatePrompt = false;
            $this->existingUser = null;

            return;
        }

        $resolved = User::resolveUsernameForEmployee($this->firstname, $this->lastname);
        $this->username = $resolved['username'];
        $this->existingUser = $resolved['existingUser'];
        $this->showDuplicatePrompt = $resolved['requiresVerification'];
    }

    public function confirmNewEmployee(): void
    {
        $this->username = User::generateUniqueUsername($this->firstname, $this->lastname);
        $this->showDuplicatePrompt = false;
        $this->existingUser = null;
    }

    public function confirmReturningEmployee(): void
    {
        if (! $this->existingUser) {
            return;
        }

        if (! $this->existingUser->is_active) {
            $this->existingUser->forceFill(['is_active' => true])->save();
        }

        $this->redirect(route('users.edit', $this->existingUser), navigate: true);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($this->user?->id),
            ],
            'role' => ['required', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'first_day' => ['nullable', 'date'],
            'last_day' => ['nullable', 'date', 'after_or_equal:first_day'],
        ]);

        if (! $this->user) {
            $resolved = User::resolveUsernameForEmployee($this->firstname, $this->lastname);

            if ($resolved['requiresVerification']) {
                $this->existingUser = $resolved['existingUser'];
                $this->showDuplicatePrompt = true;
                $this->username = $resolved['username'];

                $resolved['username'] = User::generateUniqueUsername($this->firstname, $this->lastname);
                $this->username = $resolved['username'];
            }

            $user = new User();
            $user->firstname = $validated['firstname'];
            $user->lastname = $validated['lastname'];
            $user->email = $validated['email'];
            $user->role = $validated['role'];
            $user->is_active = $validated['is_active'];
            $user->first_day = $validated['first_day'];
            $user->last_day = $validated['last_day'];
            $user->username = $resolved['username'];
            $user->password = bcrypt('password');
            $user->save();

            session()->flash('success', 'User created successfully.');

            $this->redirect(route('users.edit', $user), navigate: true);

            return;
        }

        $this->user->fill([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'],
            'first_day' => $validated['first_day'],
            'last_day' => $validated['last_day'],
        ]);
        $this->user->username = $this->user->username ?: User::generateUniqueUsername($this->user->firstname, $this->user->lastname);
        $this->user->save();

        session()->flash('success', 'User updated successfully.');
    }

    public function render()
    {
        return view('livewire.user-form');
    }
}

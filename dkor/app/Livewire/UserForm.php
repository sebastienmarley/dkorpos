<?php

namespace App\Livewire;

use App\Actions\PurgeSchedulesAfterLastDay;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserForm extends Component
{
    public ?User $user = null;

    public string $firstname = '';

    public string $lastname = '';

    public string $email = '';

    public string $role = 'salesman';

    public bool $is_active = true;

    public ?string $first_day = null;

    public ?string $last_day = null;

    public string $username = '';

    public ?string $phone = null;

    public ?string $cellphone = null;

    public bool $showDuplicatePrompt = false;

    public ?User $existingUser = null;

    public function mount(?User $user = null): void
    {
        $this->user = $user;

        if ($this->user) {
            $this->firstname = $this->user->firstname;
            $this->lastname = $this->user->lastname;
            $this->email = $this->user->email;
            $this->role = (string) $this->user->role?->name;
            $this->is_active = (bool) $this->user->is_active;
            $this->first_day = $this->user->first_day?->toDateString();
            $this->last_day = $this->user->last_day?->toDateString();
            $this->username = $this->user->username;
            $this->phone = $this->user->phone;
            $this->cellphone = $this->user->cellphone;
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

        $this->dispatch('user-saved', id: $this->existingUser->id);
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
            'role' => ['required', 'string', 'exists:roles,name'],
            'is_active' => ['boolean'],
            'first_day' => ['nullable', 'date'],
            'last_day' => ['nullable', 'date', 'after_or_equal:first_day'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'cellphone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
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

            $user = new User;
            $user->firstname = $validated['firstname'];
            $user->lastname = $validated['lastname'];
            $user->email = $validated['email'];
            $user->is_active = $validated['is_active'];
            $user->first_day = $validated['first_day'];
            $user->last_day = $validated['last_day'];
            $user->phone = $validated['phone'];
            $user->cellphone = $validated['cellphone'];
            $user->username = $resolved['username'];
            $user->password = bcrypt('password');
            $user->save();
            $user->assignRole($validated['role']);

            $this->dispatch('user-saved', id: $user->id);

            return;
        }

        $this->user->fill([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
            'first_day' => $validated['first_day'],
            'last_day' => $validated['last_day'],
            'phone' => $validated['phone'],
            'cellphone' => $validated['cellphone'],
        ]);
        $this->user->username = $this->user->username ?: User::generateUniqueUsername($this->user->firstname, $this->user->lastname);
        $this->user->save();
        $this->user->syncRoles($validated['role']);

        (new PurgeSchedulesAfterLastDay)->execute($this->user);

        session()->flash('success', 'User updated successfully.');
    }

    public function render(): View
    {
        return view('livewire.user-form');
    }
}

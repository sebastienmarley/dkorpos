<?php

namespace App\Livewire\Users;

use App\Actions\PurgeSchedulesAfterLastDay;
use App\Enums\RoleType;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
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

    public ?string $firstDay = null;

    public ?string $lastDay = null;

    public ?string $personalEmail = null;

    public ?string $phone = null;

    public ?string $cellphone = null;

    // Compte
    public bool $isActive = true;

    public ?string $generatedPassword = null;

    public function mount(User $user): void
    {
        if (Auth::user()->role === RoleType::Manager && Auth::user()->is($user)) {
            session()->flash('toast-error', __('Vous ne pouvez pas modifier votre propre fiche.'));
            $this->redirectRoute('users.index', navigate: true);

            return;
        }

        abort_unless(in_array($user->role, Auth::user()->visibleRoles(), true), 404);

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
            'role' => ['required', 'string', 'in:'.implode(',', array_column($this->getRoleTypes(), 'value'))],
            'firstDay' => ['required', 'date'],
            'lastDay' => ['nullable', 'date', 'after_or_equal:firstDay'],
            'personalEmail' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'cellphone' => ['required', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
        ]);

        $this->user->fill([
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'role' => $validated['role'],
            'first_day' => $validated['firstDay'],
            'last_day' => filled($validated['lastDay']) ? $validated['lastDay'] : null,
            'personal_email' => $validated['personalEmail'],
            'phone' => filled($validated['phone']) ? $validated['phone'] : null,
            'cellphone' => $validated['cellphone'],
        ])->markModifiedBy(Auth::user())->save();

        (new PurgeSchedulesAfterLastDay)->execute($this->user);

        Flux::toast(text: __('Identification sauvegardée.'), variant: 'success');
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

    /**
     * Rôles offerts dans le formulaire : ceux que l'utilisateur connecté peut attribuer.
     *
     * @return array<int, RoleType>
     */
    public function getRoleTypes(): array
    {
        return Auth::user()->assignableRoles();
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
        $this->role = $this->user->role->value;
        $this->firstDay = $this->user->first_day?->format('Y-m-d');
        $this->lastDay = $this->user->last_day?->format('Y-m-d');
        $this->personalEmail = $this->user->personal_email;
        $this->phone = $this->user->phone;
        $this->cellphone = $this->user->cellphone;
        $this->isActive = $this->user->is_active;
    }
}

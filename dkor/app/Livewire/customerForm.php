<?php

namespace App\Livewire;

use App\Models\customer;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class customerForm extends Component
{
    public bool $showModal = false;

    public ?int $customerId = null;

    public string $firstname = '';

    public string $lastname = '';

    public string $phone = '';

    public string $cellphone = '';

    public string $email = '';

    public string $adress = '';

    #[On('open-customer-create')]
    public function openCreate(): void
    {
        $this->reset(['customerId', 'firstname', 'lastname', 'phone', 'cellphone', 'email', 'adress']);
        $this->showModal = true;
    }

    #[On('open-customer-edit')]
    public function openEdit(int $id): void
    {
        $customer = customer::findOrFail($id);

        $this->customerId = $customer->id;
        $this->firstname = $customer->firstname;
        $this->lastname = $customer->lastname;
        $this->phone = $customer->phone ?? '';
        $this->cellphone = $customer->cellphone ?? '';
        $this->email = $customer->email ?? '';
        $this->adress = $customer->adress ?? '';

        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate([
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'cellphone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique(customer::class, 'email')->ignore($this->customerId),
            ],
            'adress' => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['phone', 'cellphone', 'email', 'adress'] as $field) {
            $validated[$field] = filled($validated[$field]) ? $validated[$field] : null;
        }

        if (! $this->customerId) {
            $customer = customer::create($validated);
        } else {
            $customer = customer::findOrFail($this->customerId);
            $customer->fill($validated)->save();
        }

        $this->showModal = false;
        $this->reset(['customerId', 'firstname', 'lastname', 'phone', 'cellphone', 'email', 'adress']);

        $this->dispatch('customer-saved', id: $customer->id);
    }

    public function render()
    {
        return view('livewire.customer-form');
    }
}

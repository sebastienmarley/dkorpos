<?php

namespace App\Livewire;

use App\Models\customer;
use Illuminate\Contracts\View\View;
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

    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $address = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    #[On('open-customer-create')]
    public function openCreate(): void
    {
        $this->reset(['customerId', 'firstname', 'lastname', 'phone', 'cellphone', 'email']);
        $this->address = ['civic' => '', 'apartment' => '', 'street' => '', 'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => ''];
        $this->resetErrorBag();
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
        $this->address = [
            'civic' => $customer->address_civic ?? '',
            'apartment' => $customer->address_apartment ?? '',
            'street' => $customer->address_street ?? '',
            'city' => $customer->address_city ?? '',
            'province' => $customer->address_province ?? '',
            'country' => $customer->address_country ?? 'CA',
            'postal_code' => $customer->address_postal_code ?? '',
        ];
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function updatedAddressCountry(): void
    {
        $this->address['province'] = '';
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
            'address.civic' => ['nullable', 'string', 'max:20'],
            'address.apartment' => ['nullable', 'string', 'max:20'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.province' => ['nullable', 'string', 'size:2'],
            'address.country' => ['nullable', 'string', 'in:CA,US'],
            'address.postal_code' => ['nullable', 'string', 'max:6'],
        ]);

        $data = [
            'firstname' => $validated['firstname'],
            'lastname' => $validated['lastname'],
            'phone' => filled($this->phone) ? $this->phone : null,
            'cellphone' => filled($this->cellphone) ? $this->cellphone : null,
            'email' => filled($this->email) ? $this->email : null,
            'address_civic' => filled($this->address['civic']) ? $this->address['civic'] : null,
            'address_apartment' => filled($this->address['apartment']) ? $this->address['apartment'] : null,
            'address_street' => filled($this->address['street']) ? $this->address['street'] : null,
            'address_city' => filled($this->address['city']) ? $this->address['city'] : null,
            'address_province' => filled($this->address['province']) ? $this->address['province'] : null,
            'address_country' => filled($this->address['country']) ? $this->address['country'] : null,
            'address_postal_code' => filled($this->address['postal_code']) ? $this->address['postal_code'] : null,
        ];

        if (! $this->customerId) {
            $customer = customer::create($data);
        } else {
            $customer = customer::findOrFail($this->customerId);
            $customer->fill($data)->save();
        }

        $this->showModal = false;
        $this->reset(['customerId', 'firstname', 'lastname', 'phone', 'cellphone', 'email']);
        $this->address = ['civic' => '', 'apartment' => '', 'street' => '', 'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => ''];

        $this->dispatch('customer-saved', id: $customer->id);
    }

    public function render(): View
    {
        return view('livewire.customer-form');
    }
}

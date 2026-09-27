<?php

namespace App\Livewire\Suppliers;

use App\Enums\SupplierType;
use App\Models\Supplier;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Supplier $supplier;

    public string $activeTab = 'identification';

    // Identification
    public string $type = 'product';

    public string $name = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    // Comptabilité
    public string $accountNumber = '';

    public string $bankAccount = '';

    public string $paymentAddress = '';

    // Paramètres
    public string $orderEmail = '';

    public bool $orderable = true;

    public bool $isActive = true;

    public function mount(Supplier $supplier): void
    {
        $this->supplier = $supplier;
        $this->fillFromModel();
    }

    public function saveIdentification(): void
    {
        $validated = $this->validate([
            'type' => ['required', 'in:product,service'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        foreach (['address', 'phone', 'email'] as $field) {
            $validated[$field] = filled($validated[$field]) ? $validated[$field] : null;
        }

        $this->supplier->fill($validated)->save();

        $this->dispatch('toast', message: __('Identification sauvegardée.'), variant: 'success');
    }

    public function saveAccounting(): void
    {
        $validated = $this->validate([
            'accountNumber' => ['nullable', 'string', 'max:255'],
            'bankAccount' => ['nullable', 'string', 'max:255'],
            'paymentAddress' => ['nullable', 'string', 'max:500'],
        ]);

        $this->supplier->fill([
            'account_number' => filled($this->accountNumber) ? $this->accountNumber : null,
            'bank_account' => filled($this->bankAccount) ? $this->bankAccount : null,
            'payment_address' => filled($this->paymentAddress) ? $this->paymentAddress : null,
        ])->save();

        $this->dispatch('toast', message: __('Comptabilité sauvegardée.'), variant: 'success');
    }

    public function saveParameters(): void
    {
        $validated = $this->validate([
            'orderEmail' => ['nullable', 'email', 'max:255'],
            'orderable' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        $this->supplier->fill([
            'order_email' => filled($this->orderEmail) ? $this->orderEmail : null,
            'orderable' => $this->orderable,
            'is_active' => $this->isActive,
        ])->save();

        $this->dispatch('toast', message: __('Paramètres sauvegardés.'), variant: 'success');
    }

    /** @return array<int, SupplierType> */
    public function getSupplierTypes(): array
    {
        return SupplierType::cases();
    }

    private function fillFromModel(): void
    {
        $this->type = $this->supplier->type->value;
        $this->name = $this->supplier->name;
        $this->address = $this->supplier->address ?? '';
        $this->phone = $this->supplier->phone ?? '';
        $this->email = $this->supplier->email ?? '';
        $this->accountNumber = $this->supplier->account_number ?? '';
        $this->bankAccount = (string) ($this->supplier->bank_account ?? '');
        $this->paymentAddress = $this->supplier->payment_address ?? '';
        $this->orderEmail = $this->supplier->order_email ?? '';
        $this->orderable = $this->supplier->orderable;
        $this->isActive = $this->supplier->is_active;
    }

    public function render(): View
    {
        return view('livewire.suppliers.show')
            ->layout('layouts.app', ['title' => $this->supplier->name]);
    }
}

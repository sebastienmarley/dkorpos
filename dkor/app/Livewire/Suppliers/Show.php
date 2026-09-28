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

    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $address = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    public string $phone = '';

    public string $email = '';

    // Comptabilité
    public string $accountNumber = '';

    public string $bankAccount = '';

    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $paymentAddress = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    public bool $sameAsMainAddress = false;

    // Paramètres
    public string $orderEmail = '';

    public string $priceMultiplier = '1';

    public bool $orderable = true;

    public bool $isActive = true;

    public function mount(Supplier $supplier): void
    {
        $this->supplier = $supplier;
        $this->fillFromModel();
    }

    public function updatedSameAsMainAddress(bool $value): void
    {
        if ($value) {
            $this->paymentAddress = $this->address;
        }
    }

    public function updatedAddressCountry(): void
    {
        $this->address['province'] = '';
    }

    public function updatedPaymentAddressCountry(): void
    {
        $this->paymentAddress['province'] = '';
    }

    public function saveIdentification(): void
    {
        $validated = $this->validate([
            'type' => ['required', 'in:product,service'],
            'name' => ['required', 'string', 'max:255'],
            'address.civic' => ['nullable', 'string', 'max:20'],
            'address.apartment' => ['nullable', 'string', 'max:20'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.province' => ['nullable', 'string', 'size:2'],
            'address.country' => ['nullable', 'string', 'in:CA,US'],
            'address.postal_code' => ['nullable', 'string', 'max:6'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $this->supplier->fill([
            'type' => $validated['type'],
            'name' => $validated['name'],
            'address_civic' => filled($this->address['civic']) ? $this->address['civic'] : null,
            'address_apartment' => filled($this->address['apartment']) ? $this->address['apartment'] : null,
            'address_street' => filled($this->address['street']) ? $this->address['street'] : null,
            'address_city' => filled($this->address['city']) ? $this->address['city'] : null,
            'address_province' => filled($this->address['province']) ? $this->address['province'] : null,
            'address_country' => filled($this->address['country']) ? $this->address['country'] : null,
            'address_postal_code' => filled($this->address['postal_code']) ? $this->address['postal_code'] : null,
            'phone' => filled($this->phone) ? $this->phone : null,
            'email' => filled($this->email) ? $this->email : null,
        ])->save();

        $this->dispatch('toast', message: __('Identification sauvegardée.'), variant: 'success');
    }

    public function saveAccounting(): void
    {
        $this->validate([
            'accountNumber' => ['nullable', 'string', 'max:255'],
            'bankAccount' => ['nullable', 'string', 'max:255'],
            'paymentAddress.civic' => ['nullable', 'string', 'max:20'],
            'paymentAddress.apartment' => ['nullable', 'string', 'max:20'],
            'paymentAddress.street' => ['nullable', 'string', 'max:255'],
            'paymentAddress.city' => ['nullable', 'string', 'max:100'],
            'paymentAddress.province' => ['nullable', 'string', 'size:2'],
            'paymentAddress.country' => ['nullable', 'string', 'in:CA,US'],
            'paymentAddress.postal_code' => ['nullable', 'string', 'max:6'],
        ]);

        $this->supplier->fill([
            'account_number' => filled($this->accountNumber) ? $this->accountNumber : null,
            'bank_account' => filled($this->bankAccount) ? $this->bankAccount : null,
            'payment_address_civic' => filled($this->paymentAddress['civic']) ? $this->paymentAddress['civic'] : null,
            'payment_address_apartment' => filled($this->paymentAddress['apartment']) ? $this->paymentAddress['apartment'] : null,
            'payment_address_street' => filled($this->paymentAddress['street']) ? $this->paymentAddress['street'] : null,
            'payment_address_city' => filled($this->paymentAddress['city']) ? $this->paymentAddress['city'] : null,
            'payment_address_province' => filled($this->paymentAddress['province']) ? $this->paymentAddress['province'] : null,
            'payment_address_country' => filled($this->paymentAddress['country']) ? $this->paymentAddress['country'] : null,
            'payment_address_postal_code' => filled($this->paymentAddress['postal_code']) ? $this->paymentAddress['postal_code'] : null,
        ])->save();

        $this->dispatch('toast', message: __('Comptabilité sauvegardée.'), variant: 'success');
    }

    public function saveParameters(): void
    {
        $this->validate([
            'orderEmail' => ['nullable', 'email', 'max:255'],
            'priceMultiplier' => ['required', 'numeric', 'min:0.0001', 'max:9999'],
            'orderable' => ['boolean'],
            'isActive' => ['boolean'],
        ]);

        $this->supplier->fill([
            'order_email' => filled($this->orderEmail) ? $this->orderEmail : null,
            'price_multiplier' => $this->priceMultiplier,
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
        $this->address = [
            'civic' => $this->supplier->address_civic ?? '',
            'apartment' => $this->supplier->address_apartment ?? '',
            'street' => $this->supplier->address_street ?? '',
            'city' => $this->supplier->address_city ?? '',
            'province' => $this->supplier->address_province ?? '',
            'country' => $this->supplier->address_country ?? 'CA',
            'postal_code' => $this->supplier->address_postal_code ?? '',
        ];
        $this->phone = $this->supplier->phone ?? '';
        $this->email = $this->supplier->email ?? '';
        $this->accountNumber = $this->supplier->account_number ?? '';
        $this->bankAccount = (string) ($this->supplier->bank_account ?? '');
        $this->paymentAddress = [
            'civic' => $this->supplier->payment_address_civic ?? '',
            'apartment' => $this->supplier->payment_address_apartment ?? '',
            'street' => $this->supplier->payment_address_street ?? '',
            'city' => $this->supplier->payment_address_city ?? '',
            'province' => $this->supplier->payment_address_province ?? '',
            'country' => $this->supplier->payment_address_country ?? 'CA',
            'postal_code' => $this->supplier->payment_address_postal_code ?? '',
        ];
        $this->sameAsMainAddress = $this->address === $this->paymentAddress
            && filled($this->address['civic']);
        $this->orderEmail = $this->supplier->order_email ?? '';
        $this->priceMultiplier = (string) $this->supplier->price_multiplier;
        $this->orderable = $this->supplier->orderable;
        $this->isActive = $this->supplier->is_active;
    }

    public function render(): View
    {
        return view('livewire.suppliers.show')
            ->layout('layouts.app', ['title' => $this->supplier->name]);
    }
}

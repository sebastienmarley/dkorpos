<?php

namespace App\Livewire\Suppliers;

use App\Enums\SupplierType;
use App\Models\Currency;
use App\Models\Supplier;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
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

    public string $currencyId = '';

    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $paymentAddress = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    public bool $sameAsMainAddress = false;

    // Transport
    public string $prepaidAmount = '0';

    public bool $collect = false;

    public string $defaultShippingSupplierId = '';

    // Info commande
    public string $orderEmail = '';

    public string $baseMultiplier = '2';

    public string $customsFee = '0';

    public string $shippingFee = '0';

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
        $this->authorize('suppliers.edit');

        $validated = $this->validate([
            'type' => ['required', Rule::enum(SupplierType::class)],
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
            'isActive' => ['boolean'],
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
            'is_active' => $this->isActive,
        ])->save();

        $this->orderable = $this->supplier->orderable;

        Flux::toast(text: __('Identification sauvegardée.'), variant: 'success');
    }

    public function saveAccounting(): void
    {
        $this->authorize('suppliers.edit');

        $this->validate([
            'accountNumber' => ['nullable', 'string', 'max:255'],
            'bankAccount' => ['nullable', 'string', 'max:255'],
            'currencyId' => ['nullable', 'integer', 'exists:currencies,id'],
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
            'currency_id' => filled($this->currencyId) ? $this->currencyId : null,
            'payment_address_civic' => filled($this->paymentAddress['civic']) ? $this->paymentAddress['civic'] : null,
            'payment_address_apartment' => filled($this->paymentAddress['apartment']) ? $this->paymentAddress['apartment'] : null,
            'payment_address_street' => filled($this->paymentAddress['street']) ? $this->paymentAddress['street'] : null,
            'payment_address_city' => filled($this->paymentAddress['city']) ? $this->paymentAddress['city'] : null,
            'payment_address_province' => filled($this->paymentAddress['province']) ? $this->paymentAddress['province'] : null,
            'payment_address_country' => filled($this->paymentAddress['country']) ? $this->paymentAddress['country'] : null,
            'payment_address_postal_code' => filled($this->paymentAddress['postal_code']) ? $this->paymentAddress['postal_code'] : null,
        ])->save();

        $this->supplier->unsetRelation('currency');

        Flux::toast(text: __('Comptabilité sauvegardée.'), variant: 'success');
    }

    public function saveTransport(): void
    {
        $this->authorize('suppliers.edit');

        abort_unless($this->supplier->type === SupplierType::Product, 404);

        $this->validate([
            'collect' => ['boolean'],
            'prepaidAmount' => [Rule::requiredIf(! $this->collect), 'nullable', 'numeric', 'min:0', 'max:99999999'],
            'defaultShippingSupplierId' => [
                'nullable',
                'integer',
                Rule::exists('suppliers', 'id')->where('type', SupplierType::Shipping->value),
            ],
        ]);

        $this->supplier->fill([
            'prepaid_amount' => filled($this->prepaidAmount) ? $this->prepaidAmount : null,
            'collect' => $this->collect,
            'default_shipping_supplier_id' => $this->collect && filled($this->defaultShippingSupplierId)
                ? $this->defaultShippingSupplierId
                : null,
        ])->save();

        if (! $this->collect) {
            $this->defaultShippingSupplierId = '';
        }

        Flux::toast(text: __('Transport sauvegardé.'), variant: 'success');
    }

    public function saveParameters(): void
    {
        $this->authorize('suppliers.edit');

        abort_if($this->supplier->type === SupplierType::Shipping, 404);

        $this->validate([
            'orderEmail' => ['nullable', 'email', 'max:255'],
            'baseMultiplier' => ['required', 'numeric', 'min:0', 'max:9999'],
            'customsFee' => ['required', 'numeric', 'min:0', 'max:9999'],
            'shippingFee' => ['required', 'numeric', 'min:0', 'max:9999'],
            'orderable' => ['boolean'],
        ]);

        $this->supplier->fill([
            'order_email' => filled($this->orderEmail) ? $this->orderEmail : null,
            'base_multiplier' => $this->baseMultiplier,
            'customs_fee' => $this->customsFee,
            'shipping_fee' => $this->shippingFee,
            'orderable' => $this->orderable,
        ])->save();

        $this->orderable = $this->supplier->orderable;

        Flux::toast(text: __('Info commande sauvegardée.'), variant: 'success');
    }

    public function getComputedMultiplierProperty(): float
    {
        return round(
            (float) $this->baseMultiplier
            + $this->supplier->exchangeRate()
            + (float) $this->customsFee
            + (float) $this->shippingFee,
            4,
        );
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
        $this->currencyId = (string) ($this->supplier->currency_id ?? '');
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
        $this->prepaidAmount = (string) ($this->supplier->prepaid_amount ?? '');
        $this->collect = $this->supplier->collect;
        $this->defaultShippingSupplierId = (string) ($this->supplier->default_shipping_supplier_id ?? '');
        $this->orderEmail = $this->supplier->order_email ?? '';
        $this->baseMultiplier = (string) $this->supplier->base_multiplier;
        $this->customsFee = (string) $this->supplier->customs_fee;
        $this->shippingFee = (string) $this->supplier->shipping_fee;
        $this->orderable = $this->supplier->orderable;
        $this->isActive = $this->supplier->is_active;
    }

    public function render(): View
    {
        return view('livewire.suppliers.show', [
            'shippingSuppliers' => Supplier::query()
                ->where('type', SupplierType::Shipping)
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->supplier->default_shipping_supplier_id))
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::query()
                ->where('is_archived', false)
                ->orWhere('id', $this->supplier->currency_id)
                ->orderBy('code')
                ->get(),
        ])
            ->layout('layouts.app', ['title' => $this->supplier->name]);
    }
}

<?php

namespace App\Livewire\Accounting;

use App\Models\CustomerPaymentMethod;
use App\Models\MerchantPaymentMethod;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PaymentMethods extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public bool $showMerchantModal = false;

    public ?int $editingMerchantId = null;

    public string $merchantName = '';

    public function openCreate(): void
    {
        $this->authorize('payment_methods.create');

        $this->reset(['editingId', 'name']);
        $this->resetValidation();
        $this->showModal = true;
    }

    public function openEdit(CustomerPaymentMethod $paymentMethod): void
    {
        $this->authorize('payment_methods.edit');
        abort_if($paymentMethod->isSystem(), 403);

        $this->editingId = $paymentMethod->id;
        $this->name = $paymentMethod->name;
        $this->resetValidation();
        $this->showModal = true;
    }

    public function toggleActive(CustomerPaymentMethod $paymentMethod): void
    {
        $this->authorize('payment_methods.edit');
        abort_if($paymentMethod->isSystem(), 403);

        $paymentMethod->update(['is_active' => ! $paymentMethod->is_active]);

        Flux::toast(
            text: $paymentMethod->is_active ? __('Mode de paiement réactivé.') : __('Mode de paiement désactivé.'),
            variant: 'success',
        );
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'payment_methods.edit' : 'payment_methods.create');

        $this->name = trim($this->name);

        $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('customer_payment_methods', 'name')->ignore($this->editingId),
                function (string $attribute, string $value, \Closure $fail): void {
                    $reserved = CustomerPaymentMethod::query()->whereNotNull('code')->whereKeyNot($this->editingId ?? 0)->pluck('name');

                    if ($reserved->contains(fn (string $name): bool => mb_strtolower($name) === mb_strtolower($value))) {
                        $fail(__('Ce nom est réservé à un mode de paiement géré par le système.'));
                    }
                },
            ],
        ]);

        if ($this->editingId) {
            $paymentMethod = CustomerPaymentMethod::findOrFail($this->editingId);
            abort_if($paymentMethod->isSystem(), 403);
            $paymentMethod->update(['name' => $this->name]);
            Flux::toast(text: __('Mode de paiement mis à jour.'), variant: 'success');
        } else {
            CustomerPaymentMethod::create(['name' => $this->name, 'is_active' => true]);
            Flux::toast(text: __('Mode de paiement créé.'), variant: 'success');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name']);
    }

    public function openCreateMerchant(): void
    {
        $this->authorize('payment_methods.create');

        $this->reset(['editingMerchantId', 'merchantName']);
        $this->resetValidation();
        $this->showMerchantModal = true;
    }

    public function openEditMerchant(MerchantPaymentMethod $merchant): void
    {
        $this->authorize('payment_methods.edit');

        $this->editingMerchantId = $merchant->id;
        $this->merchantName = $merchant->name;
        $this->resetValidation();
        $this->showMerchantModal = true;
    }

    public function toggleMerchantActive(MerchantPaymentMethod $merchant): void
    {
        $this->authorize('payment_methods.edit');

        $merchant->update(['is_active' => ! $merchant->is_active]);

        Flux::toast(
            text: $merchant->is_active ? __('Méthode marchande réactivée.') : __('Méthode marchande désactivée.'),
            variant: 'success',
        );
    }

    public function saveMerchant(): void
    {
        $this->authorize($this->editingMerchantId ? 'payment_methods.edit' : 'payment_methods.create');

        $this->merchantName = trim($this->merchantName);

        $this->validate([
            'merchantName' => ['required', 'string', 'max:255', Rule::unique('merchant_payment_methods', 'name')->ignore($this->editingMerchantId)],
        ]);

        if ($this->editingMerchantId) {
            MerchantPaymentMethod::findOrFail($this->editingMerchantId)->update(['name' => $this->merchantName]);
            Flux::toast(text: __('Méthode marchande mise à jour.'), variant: 'success');
        } else {
            MerchantPaymentMethod::create(['name' => $this->merchantName, 'is_active' => true]);
            Flux::toast(text: __('Méthode marchande créée.'), variant: 'success');
        }

        $this->showMerchantModal = false;
        $this->reset(['editingMerchantId', 'merchantName']);
    }

    public function render(): View
    {
        return view('livewire.accounting.payment-methods', [
            'paymentMethods' => CustomerPaymentMethod::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'merchantMethods' => MerchantPaymentMethod::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Modes de paiement')]);
    }
}

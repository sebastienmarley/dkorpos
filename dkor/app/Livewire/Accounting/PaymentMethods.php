<?php

namespace App\Livewire\Accounting;

use App\Models\CustomerPaymentMethod;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PaymentMethods extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

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

    public function render(): View
    {
        return view('livewire.accounting.payment-methods', [
            'paymentMethods' => CustomerPaymentMethod::query()->orderByDesc('is_active')->orderBy('name')->get(),
        ])->layout('layouts.app', ['title' => __('Modes de paiement')]);
    }
}

<?php

namespace App\Livewire\Accounting\Invoices;

use App\Models\SupplierInvoice;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Consultation d'une facture libre (sans réception ni commande). Les factures liées à un document s'ouvrent
 * dans la vue de facturation de ce document.
 */
class Show extends Component
{
    public SupplierInvoice $invoice;

    public function mount(SupplierInvoice $invoice): void
    {
        if ($invoice->reception_id !== null) {
            $this->redirectRoute('accounting.invoices.reception', $invoice->reception_id, navigate: true);

            return;
        }

        if ($invoice->supplier_order_id !== null) {
            $this->redirectRoute('accounting.invoices.order', $invoice->supplier_order_id, navigate: true);

            return;
        }

        $this->invoice = $invoice->load('supplier');
    }

    public function render(): View
    {
        return view('livewire.accounting.invoices.show')
            ->layout('layouts.app', ['title' => __('Facture :number', ['number' => $this->invoice->invoice_number])]);
    }
}

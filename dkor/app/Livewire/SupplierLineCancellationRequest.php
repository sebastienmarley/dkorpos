<?php

namespace App\Livewire;

use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use DomainException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Demande d'annulation d'une ligne de commande fournisseur envoyée (courriel au fournisseur, raison facultative).
 * Utilisable depuis la commande fournisseur (toute ligne) et depuis la commande client (ligne liée à un client).
 * Ouvrir avec l'événement « open-supplier-line-cancellation » (lineId); émet « supplier-line-cancellation-requested ».
 */
class SupplierLineCancellationRequest extends Component
{
    public bool $showModal = false;

    public ?int $lineId = null;

    public string $reason = '';

    #[On('open-supplier-line-cancellation')]
    public function open(int $lineId): void
    {
        $line = SupplierOrderLine::query()->with('customerOrderLine')->findOrFail($lineId);
        $this->authorizeFor($line);

        $this->lineId = $line->id;
        $this->reason = '';
        $this->resetValidation();
        $this->showModal = true;
    }

    public function submit(): void
    {
        $line = SupplierOrderLine::query()->with(['customerOrderLine', 'order.supplier'])->findOrFail($this->lineId);
        $this->authorizeFor($line);

        $this->validate(['reason' => ['nullable', 'string', 'max:255']]);

        try {
            $notified = $line->requestCancellation($this->reason);
        } catch (DomainException $exception) {
            $this->addError('reason', $exception->getMessage());

            return;
        }

        if ($notified) {
            Flux::toast(text: __('Demande d\'annulation envoyée au fournisseur.'), variant: 'success');
        } else {
            Flux::toast(text: SupplierOrder::emailEnabled()
                ? __('Demande enregistrée. Aucun courriel de commande pour ce fournisseur : avisez-le manuellement.')
                : __('Demande enregistrée. Les courriels sont désactivés : avisez le fournisseur manuellement.'), variant: 'warning');
        }

        $this->showModal = false;
        $this->dispatch('supplier-line-cancellation-requested', lineId: $line->id);
    }

    /**
     * Gestion des commandes fournisseurs, ou modification des commandes clients pour une ligne liée à un client.
     */
    private function authorizeFor(SupplierOrderLine $line): void
    {
        abort_unless(
            Gate::allows('supplier_orders.edit') || ($line->customerOrderLine !== null && Gate::allows('customer_orders.edit')),
            403,
        );
    }

    public function render(): View
    {
        return view('livewire.supplier-line-cancellation-request', [
            'line' => $this->lineId ? SupplierOrderLine::query()->with(['order.supplier', 'product'])->find($this->lineId) : null,
        ]);
    }
}

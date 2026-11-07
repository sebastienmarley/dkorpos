<?php

namespace App\Http\Controllers;

use App\Models\SupplierOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Bon de commande fournisseur à imprimer : même gabarit que le courriel envoyé au fournisseur.
 */
class SupplierOrderPrintController extends Controller
{
    public function __invoke(Request $request, SupplierOrder $order): View
    {
        abort_unless($order->isAccessibleTo($request->user()), 403);

        return view('documents.supplier-order', [
            'order' => $order->loadDocument(),
            'forPrint' => true,
        ]);
    }
}

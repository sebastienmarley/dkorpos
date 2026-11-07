<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Commande client à imprimer : même gabarit que le courriel envoyé au client.
 */
class CustomerOrderPrintController extends Controller
{
    public function __invoke(Request $request, CustomerOrder $order): View
    {
        abort_unless($order->isAccessibleTo($request->user()), 403);

        return view('documents.customer-order', [
            'order' => $order->loadDocument(),
            'forPrint' => true,
        ]);
    }
}

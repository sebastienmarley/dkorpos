<?php

namespace App\Http\Controllers;

use App\Models\LabelRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page d'impression des demandes d'étiquettes de la file : une page par étiquette, selon la quantité demandée.
 */
class LabelPrintController extends Controller
{
    public function __invoke(Request $request): View
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $products = LabelRequest::query()
            ->pending()
            ->whereKey($validated['ids'])
            ->with(['product.supplier', 'product.color'])
            ->oldest()
            ->get()
            ->flatMap(fn (LabelRequest $labelRequest) => array_fill(0, $labelRequest->quantity, $labelRequest->product));

        return view('labels.print', [
            'title' => __('Étiquettes à imprimer'),
            'products' => $products,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page d'impression de l'étiquette de prix d'un produit (ouvre la boîte d'impression du navigateur). Avec un magasin
 * (`?store=`) qui liquide le produit, l'étiquette porte « Liquidation » et le prix de liquidation (ADR 0018).
 */
class ProductLabelController extends Controller
{
    public function __invoke(Request $request, Product $product): View
    {
        $storeId = $request->integer('store');

        return view('labels.print', [
            'title' => __('Étiquette — :model', ['model' => $product->model]),
            'products' => collect([$product->load(['supplier', 'color'])]),
            'clearance' => $storeId > 0 ? $product->activeClearanceAt($storeId) : null,
        ]);
    }
}

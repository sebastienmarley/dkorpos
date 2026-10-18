<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

/**
 * Page d'impression de l'étiquette de prix d'un produit (ouvre la boîte d'impression du navigateur).
 */
class ProductLabelController extends Controller
{
    public function __invoke(Product $product): View
    {
        return view('labels.print', [
            'title' => __('Étiquette — :model', ['model' => $product->model]),
            'products' => collect([$product->load(['supplier', 'color'])]),
        ]);
    }
}

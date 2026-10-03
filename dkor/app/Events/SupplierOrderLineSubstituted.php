<?php

namespace App\Events;

use App\Models\SupplierOrderLine;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Un produit commandé a été substitué par un autre. Permet au module des ventes de faire suivre
 * le client rattaché à la ligne d'origine vers la ligne de remplacement.
 */
class SupplierOrderLineSubstituted
{
    use Dispatchable;

    public function __construct(
        public SupplierOrderLine $originalLine,
        public SupplierOrderLine $replacementLine,
    ) {}
}

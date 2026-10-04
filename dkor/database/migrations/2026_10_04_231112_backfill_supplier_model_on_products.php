<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tout produit doit avoir un modèle fournisseur : à défaut, il reprend son modèle.
     */
    public function up(): void
    {
        DB::table('products')
            ->where(fn ($query) => $query->whereNull('supplier_model')->orWhere('supplier_model', ''))
            ->update([
                'supplier_model' => DB::raw('model'),
                'supplier_clean_model' => DB::raw('clean_model'),
            ]);
    }

    public function down(): void
    {
        //
    }
};

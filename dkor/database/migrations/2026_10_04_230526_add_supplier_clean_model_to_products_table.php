<?php

use App\Rules\UniqueCleanProductModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('supplier_clean_model')->nullable()->after('supplier_model')->index();
        });

        DB::table('products')->whereNotNull('supplier_model')->orderBy('id')->each(function (object $product): void {
            DB::table('products')->where('id', $product->id)->update([
                'supplier_clean_model' => UniqueCleanProductModel::clean($product->supplier_model),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('supplier_clean_model');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('collection')->nullable()->after('supplier_model');
            $table->decimal('length', 8, 2)->nullable()->after('description');
            $table->decimal('width', 8, 2)->nullable()->after('length');
            $table->decimal('height', 8, 2)->nullable()->after('width');
            $table->decimal('weight', 8, 2)->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['collection', 'length', 'width', 'height', 'weight']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('size')->nullable()->after('cost');
        });
    }
};

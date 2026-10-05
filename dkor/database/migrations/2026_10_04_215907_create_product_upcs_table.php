<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('imap', 10, 2)->nullable()->after('cost');
        });

        Schema::create('product_upcs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('upc');
            $table->timestamps();

            $table->unique(['product_id', 'upc']);
            $table->index('upc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_upcs');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('imap');
        });
    }
};

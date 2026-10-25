<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_lists');
    }
};

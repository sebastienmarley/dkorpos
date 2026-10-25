<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable();
            $table->string('type');
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->boolean('is_collect')->default(false);
            $table->foreignId('shipping_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('quote_number')->nullable();
            $table->timestamp('last_emailed_at')->nullable();
            $table->boolean('is_drop_ship')->default(false);
            $table->string('drop_ship_name')->nullable();
            $table->string('drop_ship_address_civic', 20)->nullable();
            $table->string('drop_ship_address_apartment', 20)->nullable();
            $table->string('drop_ship_address_street')->nullable();
            $table->string('drop_ship_address_city', 100)->nullable();
            $table->string('drop_ship_address_province', 2)->nullable();
            $table->string('drop_ship_address_country', 2)->nullable();
            $table->string('drop_ship_address_postal_code', 6)->nullable();
            $table->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();

            $table->unique('number');
            $table->index('store_id');
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_orders');
    }
};

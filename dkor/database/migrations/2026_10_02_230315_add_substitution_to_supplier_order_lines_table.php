<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->foreignId('substituted_from_line_id')->nullable()->after('cancelled_at')
                ->constrained('supplier_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('substituted_from_line_id');
        });
    }
};

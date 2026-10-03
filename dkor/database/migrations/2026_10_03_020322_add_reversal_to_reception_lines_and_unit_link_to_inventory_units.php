<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reception_lines', function (Blueprint $table) {
            $table->unsignedInteger('quantity_reversed')->default(0)->after('unit_cost');
            $table->timestamp('reversed_at')->nullable()->after('quantity_reversed');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->string('reversal_reason')->nullable()->after('reversed_by');
        });

        Schema::table('inventory_units', function (Blueprint $table) {
            $table->foreignId('reception_line_id')->nullable()->after('product_id')
                ->constrained('reception_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reception_line_id');
        });

        Schema::table('reception_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropColumn(['quantity_reversed', 'reversed_at', 'reversal_reason']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->after('department_id')->constrained()->nullOnDelete();
            $table->foreignId('color_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->string('size')->nullable()->after('cost');
            $table->text('description')->nullable()->after('size');
            $table->boolean('is_discontinued')->default(false)->after('description');
            $table->boolean('is_non_orderable')->default(false)->after('is_discontinued');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['department_id', 'category_id', 'color_id']);
            $table->dropColumn(['department_id', 'category_id', 'color_id', 'size', 'description', 'is_discontinued', 'is_non_orderable']);
        });
    }
};

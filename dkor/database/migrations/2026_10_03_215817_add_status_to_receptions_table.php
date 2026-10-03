<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('supplier_id');
            $table->timestamp('completed_at')->nullable()->after('received_at');
        });

        DB::table('receptions')->update(['completed_at' => DB::raw('received_at')]);
    }

    public function down(): void
    {
        Schema::table('receptions', function (Blueprint $table) {
            $table->dropColumn(['status', 'completed_at']);
        });
    }
};

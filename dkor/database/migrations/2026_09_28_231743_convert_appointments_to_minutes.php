<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->unsignedSmallInteger('start_minute')->default(0)->after('date');
            $table->unsignedSmallInteger('duration_minutes')->default(60)->after('start_minute');
        });

        DB::table('appointments')->update([
            'start_minute' => DB::raw('hour * 60'),
            'duration_minutes' => DB::raw('duration_hours * 60'),
        ]);

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'date', 'hour']);
            $table->dropColumn(['hour', 'duration_hours']);
            $table->index(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
            $table->unsignedTinyInteger('hour')->default(8)->after('date');
            $table->unsignedTinyInteger('duration_hours')->default(1)->after('hour');
        });

        DB::table('appointments')->update([
            'hour' => DB::raw('start_minute / 60'),
            'duration_hours' => DB::raw('MAX(1, (duration_minutes + 59) / 60)'),
        ]);

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['start_minute', 'duration_minutes']);
            $table->unique(['user_id', 'date', 'hour']);
        });
    }
};

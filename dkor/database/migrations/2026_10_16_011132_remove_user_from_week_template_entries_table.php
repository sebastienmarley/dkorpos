<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une semaine type devient un ensemble de quarts type (un par jour au plus), appliqué à un employé à la fois.
 *
 * Une semaine type existante qui couvrait plusieurs employés est séparée en une semaine type par employé
 * (« Semaine normale — Marie Tremblay »), pour ne perdre aucun quart.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (DB::table('week_templates')->orderBy('id')->get() as $week) {
                $userIds = DB::table('week_template_entries')
                    ->where('week_template_id', $week->id)
                    ->distinct()
                    ->orderBy('user_id')
                    ->pluck('user_id');

                if ($userIds->count() <= 1) {
                    continue;
                }

                foreach ($userIds as $userId) {
                    $user = DB::table('users')->where('id', $userId)->first();
                    $employeeName = $user ? trim(($user->firstname ?? '').' '.($user->lastname ?? '')) : '';

                    $newWeekId = DB::table('week_templates')->insertGetId([
                        'name' => $this->uniqueName($week->name.' — '.($employeeName !== '' ? $employeeName : '#'.$userId)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('week_template_entries')
                        ->where('week_template_id', $week->id)
                        ->where('user_id', $userId)
                        ->update(['week_template_id' => $newWeekId]);
                }

                DB::table('week_templates')->where('id', $week->id)->delete();
            }
        });

        Schema::table('week_template_entries', function (Blueprint $table) {
            $table->dropUnique(['week_template_id', 'user_id', 'weekday']);
            $table->dropConstrainedForeignId('user_id');
            $table->unique(['week_template_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::table('week_template_entries', function (Blueprint $table) {
            $table->dropUnique(['week_template_id', 'weekday']);
            $table->foreignId('user_id')->nullable()->after('week_template_id')->constrained()->cascadeOnDelete();
            $table->unique(['week_template_id', 'user_id', 'weekday']);
        });
    }

    private function uniqueName(string $name): string
    {
        $candidate = $name;
        $suffix = 2;

        while (DB::table('week_templates')->where('name', $candidate)->exists()) {
            $candidate = $name.' ('.$suffix++.')';
        }

        return $candidate;
    }
};

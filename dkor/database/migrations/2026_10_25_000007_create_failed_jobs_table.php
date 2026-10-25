<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('connection');
            $table->string('queue');
            $table->text('payload');
            $table->text('exception');
            $table->timestamp('failed_at')->useCurrent();

            $table->index(['connection', 'queue', 'failed_at']);
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
    }
};

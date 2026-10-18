<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Écritures du grand livre (ADR 0011) : équilibrées, immuables, liées à leur document source (référence
     * polymorphe, sans clé étrangère).
     */
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->date('posted_on');
            $table->string('description');
            $table->nullableMorphs('reference');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('posted_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};

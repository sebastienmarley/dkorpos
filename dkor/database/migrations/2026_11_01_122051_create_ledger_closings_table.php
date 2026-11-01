<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historique des clôtures du grand livre (ADR 0016) : chaque clôture ou réouverture fixe la date jusqu'à laquelle
     * le grand livre est clôturé (vide : tout est ouvert). La dernière ligne fait foi.
     */
    public function up(): void
    {
        Schema::create('ledger_closings', function (Blueprint $table) {
            $table->id();
            $table->date('closed_through')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_closings');
    }
};

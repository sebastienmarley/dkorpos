<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Facture payée hors de l'application, avant la tenue des paiements (#132) : elle n'a ni solde dû ni écriture de
     * paiement. Seule une facture sans écriture au grand livre peut l'être.
     */
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->timestamp('paid_outside_at')->nullable()->after('credit_received_at');
            $table->foreignId('paid_outside_by')->nullable()->after('paid_outside_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_outside_by');
            $table->dropColumn('paid_outside_at');
        });
    }
};

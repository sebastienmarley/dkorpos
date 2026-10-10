<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taxes figées de chaque commande client : une ligne par taxe, avec le nom, le taux et le numéro du marchand
     * copiés à la création de la commande. Remplace les colonnes fixes gst et qst. Les commandes existantes
     * reçoivent la TPS (5 %) et la TVQ (9,975 %) avec lesquelles elles ont été calculées.
     */
    public function up(): void
    {
        Schema::create('customer_order_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 50);
            $table->decimal('rate', 6, 3);
            $table->boolean('is_compound')->default(false);
            $table->string('registration_number')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

        DB::table('customer_orders')->get(['id', 'store_id', 'gst', 'qst'])->each(function (object $order): void {
            $registrations = DB::table('store_tax_registrations')->where('store_id', $order->store_id)->pluck('number', 'tax_name');

            foreach ([['TPS', '5', $order->gst], ['TVQ', '9.975', $order->qst]] as [$name, $rate, $amount]) {
                DB::table('customer_order_taxes')->insert([
                    'customer_order_id' => $order->id,
                    'name' => $name,
                    'rate' => $rate,
                    'is_compound' => false,
                    'registration_number' => $registrations[$name] ?? null,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn(['gst', 'qst']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->decimal('gst', 12, 2)->default(0)->after('subtotal');
            $table->decimal('qst', 12, 2)->default(0)->after('gst');
        });

        foreach (['gst' => 'TPS', 'qst' => 'TVQ'] as $column => $name) {
            DB::table('customer_order_taxes')->where('name', $name)->get(['customer_order_id', 'amount'])
                ->each(fn (object $tax) => DB::table('customer_orders')->where('id', $tax->customer_order_id)->update([$column => $tax->amount]));
        }

        Schema::dropIfExists('customer_order_taxes');
    }
};

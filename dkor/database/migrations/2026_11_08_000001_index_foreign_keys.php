<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur les clés étrangères. Contrairement à MySQL, PostgreSQL (la production, ADR 0014) et SQLite n'indexent pas
 * une colonne parce qu'elle porte une contrainte : chaque lecture d'une relation (les lignes d'une commande, ses
 * taxes, ses paiements…) parcourait toute la table. Le DemoDataSeeder prenait ainsi 70 à 90 s sur PostgreSQL contre
 * 8 s sur SQLite, et la suite PostgreSQL du CI dépassait 15 minutes.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'appointments' => ['created_by', 'customer_id', 'last_updated_by'],
        'categories' => ['department_id'],
        'customer_notices' => ['created_by', 'customer_id', 'customer_order_id'],
        'customer_order_deliveries' => ['address_verified_by', 'cancelled_by', 'customer_order_id', 'delivered_by', 'departed_by', 'pay_on_delivery_authorized_by', 'requested_by', 'rescheduled_from_id', 'shipped_by', 'shipping_supplier_id', 'supplier_invoice_id'],
        'customer_order_delivery_lines' => ['customer_order_line_id'],
        'customer_order_lines' => ['customer_order_id', 'customer_order_pickup_id', 'inventory_location_id', 'part_id', 'product_clearance_id', 'product_demo_id', 'product_id', 'promotion_id', 'service_id', 'supplier_id', 'supplier_order_line_id'],
        'customer_order_payments' => ['customer_order_id', 'customer_order_pickup_id', 'customer_payment_method_id', 'received_by'],
        'customer_order_pickups' => ['customer_order_id', 'handled_by'],
        'customer_order_salesperson' => ['user_id'],
        'customer_order_taxes' => ['customer_order_id', 'tax_id'],
        'customer_orders' => ['created_by', 'customer_id', 'promotion_id', 'store_id'],
        'customer_payment_methods' => ['ledger_account_id'],
        'defective_products' => ['created_by', 'customer_order_id', 'customer_order_line_id', 'product_id', 'reception_line_id', 'supplier_order_line_id'],
        'inventory_adjustments' => ['user_id'],
        'inventory_count_lines' => ['counted_by', 'inventory_adjustment_id', 'product_id'],
        'inventory_count_results' => ['inventory_zone_id', 'product_id', 'reconciled_by'],
        'inventory_count_scans' => ['inventory_count_sheet_id', 'product_id', 'user_id'],
        'inventory_count_sheet_lines' => ['product_id'],
        'inventory_count_sheets' => ['inventory_zone_id'],
        'inventory_counts' => ['finalized_by', 'started_by'],
        'inventory_locations' => ['inventory_zone_id'],
        'inventory_movements' => ['store_id', 'user_id'],
        'inventory_units' => ['reception_line_id'],
        'journal_entries' => ['created_by', 'store_id'],
        'label_requests' => ['printed_by', 'product_id', 'requested_by'],
        'ledger_closings' => ['user_id'],
        'ledger_entries' => ['store_id', 'user_id'],
        'ledger_entry_lines' => ['ledger_account_id', 'ledger_entry_id'],
        'part_product' => ['product_id'],
        'payroll_periods' => ['locked_by'],
        'price_list_items' => ['price_list_list_id', 'product_id'],
        'price_list_lists' => ['price_list_id'],
        'product_clearances' => ['store_id'],
        'product_demos' => ['store_id'],
        'product_store_locations' => ['inventory_location_id', 'store_id'],
        'products' => ['category_id', 'color_id', 'style_id'],
        'reception_lines' => ['product_id', 'reception_id', 'reversed_by', 'supplier_order_line_id'],
        'receptions' => ['received_by', 'supplier_id'],
        'role_has_permissions' => ['role_id'],
        'schedules' => ['created_by', 'last_updated_by'],
        'service_supplier' => ['supplier_id'],
        'store_transfers' => ['accepted_by', 'closed_by', 'customer_order_line_id', 'product_id', 'received_by', 'requested_by'],
        'stores' => ['billing_store_id', 'shipping_warehouse_id', 'warehouse_store_id'],
        'supplier_invoice_lines' => ['reception_line_id', 'supplier_invoice_id', 'supplier_order_line_id'],
        'supplier_invoices' => ['created_by', 'ledger_account_id', 'paid_outside_by'],
        'supplier_order_lines' => ['part_id', 'product_id', 'substituted_from_line_id', 'supplier_order_id'],
        'supplier_orders' => ['billing_store_id', 'created_by', 'shipping_supplier_id', 'supplier_id'],
        'supplier_payment_allocations' => ['supplier_invoice_id'],
        'supplier_payments' => ['cancelled_by', 'created_by', 'ledger_account_id', 'supplier_id'],
        'suppliers' => ['currency_id', 'default_shipping_supplier_id'],
        'user_denied_permissions' => ['permission_id'],
        'users' => ['last_modified_by', 'position_id', 'store_id'],
        'week_template_entries' => ['shift_template_id'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};

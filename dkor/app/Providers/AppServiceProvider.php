<?php

namespace App\Providers;

use App\Models;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMorphMap();
        $this->guardRealRecipients();
    }

    /**
     * Tant qu'aucune adresse de redirection (MAIL_ALWAYS_TO) n'est réglée, un service d'envoi réel n'envoie rien : un
     * oubli de configuration ne peut pas écrire à un vrai fournisseur ou client (ADR 0014). Les mailers log et array
     * n'envoient rien de toute façon.
     */
    protected function guardRealRecipients(): void
    {
        Event::listen(function (MessageSending $event): ?bool {
            if (filled(config('mail.to.address'))
                || config('mail.allow_real_recipients')
                || in_array(config('mail.default'), ['log', 'array'], true)) {
                return null;
            }

            Log::warning('Courriel non envoyé : MAIL_ALWAYS_TO est vide et MAIL_ALLOW_REAL_RECIPIENTS est faux.', [
                'subject' => $event->message->getSubject(),
            ]);

            return false;
        });
    }

    /**
     * Les références polymorphes (journal d'inventaire, écritures, avis, rôles) stockent un alias stable plutôt que le
     * nom de la classe : renommer un modèle ne brise plus les données (ADR 0014). Un alias ne change plus une fois en
     * production ; tout nouveau modèle reçoit le sien ici.
     */
    protected function configureMorphMap(): void
    {
        Relation::enforceMorphMap([
            'appointment' => Models\Appointment::class,
            'category' => Models\Category::class,
            'color' => Models\Color::class,
            'currency' => Models\Currency::class,
            'customer' => Models\customer::class,
            'customer_order' => Models\CustomerOrder::class,
            'customer_order_delivery' => Models\CustomerOrderDelivery::class,
            'customer_order_delivery_line' => Models\CustomerOrderDeliveryLine::class,
            'customer_order_line' => Models\CustomerOrderLine::class,
            'customer_order_payment' => Models\CustomerOrderPayment::class,
            'customer_order_pickup' => Models\CustomerOrderPickup::class,
            'customer_order_tax' => Models\CustomerOrderTax::class,
            'customer_payment_method' => Models\CustomerPaymentMethod::class,
            'defective_product' => Models\DefectiveProduct::class,
            'delivery_truck' => Models\DeliveryTruck::class,
            'department' => Models\Department::class,
            'holiday' => Models\Holiday::class,
            'inventory_adjustment' => Models\InventoryAdjustment::class,
            'inventory_count' => Models\InventoryCount::class,
            'inventory_count_line' => Models\InventoryCountLine::class,
            'inventory_count_result' => Models\InventoryCountResult::class,
            'inventory_count_scan' => Models\InventoryCountScan::class,
            'inventory_count_sheet' => Models\InventoryCountSheet::class,
            'inventory_count_sheet_line' => Models\InventoryCountSheetLine::class,
            'inventory_location' => Models\InventoryLocation::class,
            'inventory_movement' => Models\InventoryMovement::class,
            'inventory_stock' => Models\InventoryStock::class,
            'inventory_unit' => Models\InventoryUnit::class,
            'inventory_zone' => Models\InventoryZone::class,
            'label_request' => Models\LabelRequest::class,
            'ledger_account' => Models\LedgerAccount::class,
            'ledger_closing' => Models\LedgerClosing::class,
            'ledger_entry' => Models\LedgerEntry::class,
            'ledger_entry_line' => Models\LedgerEntryLine::class,
            'merchant_payment_method' => Models\MerchantPaymentMethod::class,
            'part' => Models\Part::class,
            'payroll_period' => Models\PayrollPeriod::class,
            'permission' => Models\Permission::class,
            'position' => Models\Position::class,
            'price_list' => Models\PriceList::class,
            'price_list_item' => Models\PriceListItem::class,
            'price_list_list' => Models\PriceListList::class,
            'product' => Models\Product::class,
            'product_store_location' => Models\ProductStoreLocation::class,
            'product_upc' => Models\ProductUpc::class,
            'reception' => Models\Reception::class,
            'reception_line' => Models\ReceptionLine::class,
            'role' => Models\Role::class,
            'schedule' => Models\Schedule::class,
            'service' => Models\Service::class,
            'service_supplier' => Models\ServiceSupplier::class,
            'shift_template' => Models\ShiftTemplate::class,
            'store' => Models\Store::class,
            'store_tax_registration' => Models\StoreTaxRegistration::class,
            'store_transfer' => Models\StoreTransfer::class,
            'style' => Models\Style::class,
            'supplier' => Models\Supplier::class,
            'supplier_invoice' => Models\SupplierInvoice::class,
            'supplier_invoice_line' => Models\SupplierInvoiceLine::class,
            'supplier_invoice_tax' => Models\SupplierInvoiceTax::class,
            'supplier_payment' => Models\SupplierPayment::class,
            'supplier_payment_allocation' => Models\SupplierPaymentAllocation::class,
            'supplier_order' => Models\SupplierOrder::class,
            'supplier_order_line' => Models\SupplierOrderLine::class,
            'tax' => Models\Tax::class,
            'user' => Models\User::class,
            'week_template' => Models\WeekTemplate::class,
            'week_template_entry' => Models\WeekTemplateEntry::class,
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        URL::forceHttps(app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

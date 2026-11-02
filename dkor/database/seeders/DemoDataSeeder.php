<?php

namespace Database\Seeders;

use App\Actions\RecordJournalEntry;
use App\Actions\RecordSupplierPayment;
use App\Actions\RequestShipment;
use App\Actions\ScheduleDelivery;
use App\Enums\DeliveryPeriod;
use App\Enums\LedgerAccountType;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierType;
use App\Models\Currency;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\DeliveryTruck;
use App\Models\LedgerAccount;
use App\Models\LedgerClosing;
use App\Models\Product;
use App\Models\Reception;
use App\Models\ReceptionLine;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\ServiceSupplier;
use App\Models\Store;
use App\Models\StoreTaxRegistration;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderLine;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Données de démonstration cohérentes pour essayer l'application de bout en bout : fournisseurs de tous types (CAD,
 * USD, sur mesure, services, transport), produits, clients, achats reçus et facturés, ventes à tous les stades
 * (dépôt, ramassage, livraison, expédition, service, retour, annulation avec frais, sur mesure), paiements aux
 * fournisseurs et écritures manuelles. Tout passe par les méthodes de l'application, sur environ deux mois : stock,
 * unités FIFO et grand livre concordent (`ledger:check`).
 *
 * À lancer après DatabaseSeeder : `php artisan migrate:fresh --seed` puis `php artisan db:seed --class=DemoDataSeeder`.
 */
class DemoDataSeeder extends Seeder
{
    private const DAYS = 60;

    /** Jours d'horaire publiés après aujourd'hui, pour planifier des livraisons et prendre des rendez-vous. */
    private const SCHEDULE_DAYS_AHEAD = 28;

    private Store $store;

    private User $admin;

    /** @var list<User> */
    private array $salespeople = [];

    private CustomerPaymentMethod $cash;

    private CustomerPaymentMethod $visa;

    private CustomerPaymentMethod $debit;

    /** @var Collection<int, Supplier> */
    private Collection $productSuppliers;

    private Supplier $customSupplier;

    private Supplier $serviceSupplier;

    private Supplier $carrier;

    /** @var Collection<int, Product> */
    private Collection $products;

    private Product $customTemplate;

    private Service $deliveryService;

    private Service $installation;

    /** @var Collection<int, customer> */
    private Collection $customers;

    /** @var list<SupplierInvoice> */
    private array $invoices = [];

    private CarbonImmutable $start;

    public function run(): void
    {
        $this->admin = User::query()->where('email', 'testadmin@dkor.ca')->first() ?? $this->seedBase();
        Auth::login($this->admin);
        $this->start = CarbonImmutable::today()->subDays(self::DAYS);

        try {
            $this->on(0, function (): void {
                $this->setUpStore();
                $this->setUpEmployees();
                $this->setUpPaymentMethods();
                $this->setUpSuppliersAndProducts();
                $this->setUpCustomers();
                $this->openingBalance();
            });

            $this->stockPurchases();
            $this->sales();
            $this->recentSales();
            $this->supplierPayments();
            $this->cardDeposits();
            $this->closeOldPeriod();
        } finally {
            Carbon::setTestNow();
            CarbonImmutable::setTestNow();
        }

        Artisan::call('ledger:check');
        $this->command->getOutput()->write(Artisan::output());
    }

    private function seedBase(): User
    {
        $this->call(DatabaseSeeder::class);

        return User::query()->where('email', 'testadmin@dkor.ca')->firstOrFail();
    }

    /**
     * Exécute $callback comme si on était $day jours après le début de la période de démonstration, à 10 h.
     */
    private function on(int $day, callable $callback): void
    {
        $moment = $this->start->addDays($day)->setTime(10, 0);
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);

        $callback();
    }

    private function info(string $message): void
    {
        $this->command->info($message);
    }

    private function setUpStore(): void
    {
        $this->store = $this->admin->store ?? Store::query()->firstOrFail();
        $this->store->update([
            'address_civic' => '200',
            'address_street' => 'boulevard René-Lepage',
            'address_city' => 'Rimouski',
            'address_province' => 'QC',
            'address_country' => 'CA',
            'address_postal_code' => 'G5L1P7',
            'cancellation_fee_percent' => 15,
            'custom_deposit_percent' => 50,
            'shipping_warehouse_id' => $this->store->id,
        ]);

        foreach (['TPS' => '123456789 RT0001', 'TVQ' => '1234567890 TQ0001'] as $tax => $number) {
            StoreTaxRegistration::query()->firstOrCreate(['store_id' => $this->store->id, 'tax_name' => $tax], ['number' => $number]);
        }

        DeliveryTruck::factory()->create(['store_id' => $this->store->id, 'name' => 'Camion 1', 'capacity_per_period' => 3]);
    }

    /**
     * Un employé par rôle (l'administrateur existe déjà), deux vendeurs à commission et à primes, tous avec le mot de
     * passe « password ».
     * Le livreur travaille du lundi au vendredi ; les vendeurs et le designer ont des quarts aléatoires dans les
     * heures d'ouverture du magasin, pour tester les rendez-vous. Les horaires couvrent aussi les semaines à venir.
     */
    private function setUpEmployees(): void
    {
        $this->info('Employés…');
        $employees = [
            ['owner', 'Paul', 'Proprio', 'proprio'],
            ['manager', 'Marie', 'Directrice', 'directeur'],
            ['salesman', 'Sophie', 'Vendeuse', 'vendeur'],
            ['salesman', 'Marc', 'Vendeur', 'vendeur2'],
            ['design', 'Diane', 'Designer', 'designer'],
            ['delivery', 'Luc', 'Livreur', 'livreur'],
            ['warehouse', 'Eric', 'Commis', 'entrepot'],
            ['accounting', 'Claire', 'Comptable', 'comptable'],
            ['thirdkey', 'Tom', 'Clef', 'troisiemecle'],
        ];

        foreach ($employees as [$role, $firstname, $lastname, $emailName]) {
            $user = User::factory()->withRole($role)->create([
                'store_id' => $this->store->id,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $emailName.'@dkor.ca',
                'first_day' => $this->start->subMonths(6)->toDateString(),
                ...($role === 'salesman' ? ['has_commission' => true, 'commission_rate' => '3.00', 'has_bonus' => true, 'weekly_sales_target' => 3000, 'bonus_amount' => 25, 'bonus_step' => 500] : []),
            ]);

            if ($role === 'salesman') {
                $this->salespeople[] = $user;
            }

            if ($role === 'delivery') {
                $this->publishDriverSchedule($user);
            }

            if (in_array($role, ['salesman', 'design'], true)) {
                $this->publishRandomSchedule($user);
            }
        }
    }

    private function publishDriverSchedule(User $user): void
    {
        for ($day = 0; $day <= self::DAYS + self::SCHEDULE_DAYS_AHEAD; $day++) {
            $date = $this->start->addDays($day);

            if ($date->isWeekend()) {
                continue;
            }

            $this->publishShift($user, $date, '08:00', '17:00', 60);
        }
    }

    /**
     * Quarts de 4 à 8 heures, quatre ou cinq jours ouverts par semaine, dans les heures d'ouverture du magasin et
     * jamais plus de 40 heures travaillées (pause déduite) dans une semaine.
     */
    private function publishRandomSchedule(User $user): void
    {
        $end = $this->start->addDays(self::DAYS + self::SCHEDULE_DAYS_AHEAD);

        for ($week = $this->start->startOfWeek(); $week->lte($end); $week = $week->addWeek()) {
            $openDays = collect(range(0, 6))
                ->map(fn (int $offset): CarbonImmutable => $week->addDays($offset))
                ->filter(fn (CarbonImmutable $date): bool => $date->between($this->start, $end) && ($this->store->opening_hours[strtolower($date->englishDayOfWeek)]['open'] ?? false))
                ->shuffle()
                ->take(fake()->numberBetween(4, 5))
                ->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp());
            $remainingMinutes = 40 * 60;

            foreach ($openDays as $date) {
                $hours = $this->store->opening_hours[strtolower($date->englishDayOfWeek)];
                [$opensAt, $closesAt] = [$this->minutesOf($hours['from']), $this->minutesOf($hours['to'])];
                $length = min(fake()->numberBetween(8, 16) * 30, $closesAt - $opensAt);
                $breakMinutes = $length > 5 * 60 ? 30 : 0;

                if ($length - $breakMinutes > $remainingMinutes || $length < 4 * 60) {
                    continue;
                }

                $startsAt = $opensAt + fake()->numberBetween(0, intdiv($closesAt - $opensAt - $length, 30)) * 30;
                $this->publishShift($user, $date, $this->timeOf($startsAt), $this->timeOf($startsAt + $length), $breakMinutes);
                $remainingMinutes -= $length - $breakMinutes;
            }
        }
    }

    private function publishShift(User $user, CarbonImmutable $date, string $startTime, string $endTime, int $breakMinutes): void
    {
        Schedule::factory()->published()->create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'break_minutes' => $breakMinutes,
        ]);
    }

    private function minutesOf(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }

    private function timeOf(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    private function setUpPaymentMethods(): void
    {
        $this->cash = CustomerPaymentMethod::cash();

        $clearing = fn (string $code, string $name): LedgerAccount => LedgerAccount::query()->firstOrCreate(['code' => $code], ['name' => $name, 'type' => LedgerAccountType::Asset]);

        $this->visa = CustomerPaymentMethod::query()->firstOrCreate(['name' => 'Visa'], ['is_active' => true, 'is_usable_on_delivery' => true]);
        $this->visa->update(['ledger_account_id' => $clearing('1030', 'Compensation Visa')->id]);

        $this->debit = CustomerPaymentMethod::query()->firstOrCreate(['name' => 'Débit'], ['is_active' => true, 'is_usable_on_delivery' => true]);
        $this->debit->update(['ledger_account_id' => $clearing('1031', 'Compensation Débit')->id]);

        LedgerAccount::query()->firstOrCreate(['code' => '5610'], ['name' => 'Frais de cartes', 'type' => LedgerAccountType::Expense]);
        LedgerAccount::query()->firstOrCreate(['code' => '5620'], ['name' => 'Frais bancaires', 'type' => LedgerAccountType::Expense]);
    }

    private function setUpSuppliersAndProducts(): void
    {
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $this->productSuppliers = collect([
            ['name' => 'Meubles Laurentides', 'early_payment_discount_percent' => 2, 'early_payment_discount_days' => 10],
            ['name' => 'Matelas Boréal'],
            ['name' => 'Luminaires Saint-Laurent'],
            ['name' => 'Décor Charlevoix'],
            ['name' => 'Ashford Furniture (USD)', 'currency_id' => $usd->id],
            ['name' => 'Carolina Outdoor (USD)', 'currency_id' => $usd->id],
        ])->map(fn (array $attributes): Supplier => Supplier::factory()->create([...$attributes, 'type' => SupplierType::Product, 'base_multiplier' => 2, 'address_province' => 'QC']));

        $this->customSupplier = Supplier::factory()->create(['name' => 'Atelier Sur Mesure Gaspésie', 'type' => SupplierType::Product, 'custom_made' => true, 'base_multiplier' => 2]);
        $this->serviceSupplier = Supplier::factory()->create(['name' => 'Installations Pro-Bas-Saint-Laurent', 'type' => SupplierType::Service]);
        $this->carrier = Supplier::factory()->create(['name' => 'Transport Purolator', 'type' => SupplierType::Shipping]);

        $models = ['Sofa', 'Fauteuil', 'Table', 'Chaise', 'Lampe', 'Matelas', 'Commode', 'Buffet'];
        $this->products = $this->productSuppliers->flatMap(fn (Supplier $supplier, int $index): Collection => collect(range(1, 6))->map(
            fn (int $number): Product => Product::factory()->create([
                'supplier_id' => $supplier->id,
                'model' => $models[($index + $number) % count($models)].' '.strtoupper(substr($supplier->name, 0, 3)).'-'.(100 + $number),
                'cost' => Money::of(fake()->numberBetween(30, 600) * 100),
                'is_taxable' => true,
                'length' => 40,
                'width' => 30,
                'height' => 30,
                'weight' => 20,
            ]),
        ))->values();

        $this->customTemplate = Product::factory()->create(['supplier_id' => $this->customSupplier->id, 'model' => 'SOFA-SUR-MESURE', 'is_custom' => true, 'cost' => Money::parse(1)]);

        $this->deliveryService = Service::factory()->internal(Money::parse(89))->create(['name' => 'Livraison à domicile']);
        $this->installation = Service::factory()->create(['name' => 'Installation']);
        ServiceSupplier::query()->create(['service_id' => $this->installation->id, 'supplier_id' => $this->serviceSupplier->id, 'cost' => Money::parse(120), 'selling_price' => Money::parse(199)]);
    }

    private function setUpCustomers(): void
    {
        $this->customers = customer::factory()->count(30)->create([
            'address_province' => 'QC',
            'address_country' => 'CA',
            'address_city' => 'Rimouski',
            'address_postal_code' => 'G5L1A1',
        ]);
    }

    private function openingBalance(): void
    {
        app(RecordJournalEntry::class)->handle($this->admin, today(), 'Solde d\'ouverture', 'Relevé bancaire', null, [
            ['account' => LedgerAccount::forKey(LedgerAccount::CASH_CAD), 'debit' => Money::parse(50000), 'credit' => Money::zero()],
            ['account' => LedgerAccount::forKey(LedgerAccount::CASH_USD), 'debit' => Money::parse(10000), 'credit' => Money::zero()],
            ['account' => LedgerAccount::forKey(LedgerAccount::OPENING_BALANCE), 'debit' => Money::zero(), 'credit' => Money::parse(60000)],
        ]);
    }

    /**
     * Commandes de stock envoyées à chaque fournisseur, reçues (une avec un manquant et un endommagé) puis facturées.
     */
    private function stockPurchases(): void
    {
        $this->info('Achats de stock…');

        foreach ($this->productSuppliers as $index => $supplier) {
            $order = null;

            $this->on(1 + $index, function () use ($supplier, &$order): void {
                $order = SupplierOrder::factory()->create(['supplier_id' => $supplier->id, 'store_id' => $this->store->id]);

                foreach ($this->products->where('supplier_id', $supplier->id) as $product) {
                    SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => fake()->numberBetween(4, 8), 'unit_cost' => $product->cost]);
                }

                $order->markPending();
                $order->fresh()->send();
            });

            $this->on(6 + $index, function () use ($order, $index): void {
                $receipts = $order->lines()->get()->mapWithKeys(fn (SupplierOrderLine $line, int $position): array => [
                    $line->id => $index === 0 && $position === 0
                        ? ['quantity' => $line->quantity - 2, 'damaged' => 1, 'short' => 1]
                        : ['quantity' => $line->quantity],
                ])->all();

                $reception = Reception::start($order->supplier, $receipts, ['reference' => 'BL-'.(5000 + $order->id)]);
                $reception->complete();
            });

            $this->on(9 + $index, fn () => $this->invoiceReception($order, freight: $index % 2 === 0 ? 45 : 0));
        }
    }

    private function invoiceReception(SupplierOrder $order, float $freight = 0): void
    {
        foreach (Reception::query()->whereHas('lines.orderLine', fn ($lines) => $lines->where('supplier_order_id', $order->id))->whereDoesntHave('invoice')->get() as $reception) {
            $merchandise = Money::sum($reception->invoiceableLines()->with('orderLine')->get()->map(fn (ReceptionLine $line): Money => $line->unit_cost->times($line->billable_quantity))->all());
            $isForeign = $reception->supplier->conversionRate() !== '1';
            $base = $merchandise->plus(Money::parse($freight));
            $taxes = $isForeign ? [] : ['TPS' => $base->percent(5)->toDecimal(), 'TVQ' => $base->percent(9.975)->toDecimal()];

            $this->invoices[] = SupplierInvoice::record($reception, [
                'invoice_number' => 'F-'.(10000 + $reception->id),
                'invoice_date' => today()->toDateString(),
                'freight_fee' => (string) $freight,
                'taxes' => $taxes,
                'invoice_total' => $base->plus(Money::sum(array_map(fn (string $amount): Money => Money::parse($amount), $taxes)))->toDecimal(),
            ]);
        }
    }

    /**
     * Commandes clients à tous les stades, réparties sur la période.
     */
    private function sales(): void
    {
        $this->info('Ventes…');
        $scenarios = ['pickup', 'deposit', 'delivery', 'shipment', 'specialOrder', 'service', 'return', 'cancellation', 'custom', 'pickup', 'delivery', 'pickup'];

        foreach (range(0, 23) as $index) {
            $scenario = $scenarios[$index % count($scenarios)];
            $day = 15 + intdiv($index * 40, 24);

            $this->on($day, function () use ($scenario, $index, $day): void {
                $order = $this->newOrder($index);

                match ($scenario) {
                    'delivery', 'shipment', 'specialOrder', 'service', 'return' => $this->{$scenario}($order, $day),
                    default => $this->{$scenario}($order),
                };
            });
        }
    }

    /**
     * Ramassages de la dernière semaine, jusqu'à aujourd'hui, pour que l'accueil montre des ventes du jour, de la
     * semaine et du mois.
     */
    private function recentSales(): void
    {
        $this->info('Ventes récentes…');

        foreach (range(self::DAYS - 6, self::DAYS) as $offset => $day) {
            foreach (range(0, $offset % 2) as $number) {
                $this->on($day, fn () => $this->pickup($this->newOrder(100 + $offset * 2 + $number)));
            }
        }
    }

    /**
     * Commande d'un client, vendue par un des deux vendeurs ou, une fois sur cinq, partagée entre eux.
     */
    private function newOrder(int $index): CustomerOrder
    {
        $order = CustomerOrder::factory()->create(['customer_id' => $this->customers[$index % $this->customers->count()]->id, 'store_id' => $this->store->id]);
        $order->syncSalespeople($index % 5 === 0
            ? [['user_id' => $this->salespeople[0]->id, 'percent' => 50], ['user_id' => $this->salespeople[1]->id, 'percent' => 50]]
            : [['user_id' => $this->salespeople[$index % 2]->id, 'percent' => 100]]);

        return $order;
    }

    /**
     * Ajoute un ou deux produits en stock à la commande.
     *
     * @return list<CustomerOrderLine>
     */
    private function stockLines(CustomerOrder $order, int $count = 2): array
    {
        $lines = [];

        foreach ($this->products->shuffle() as $product) {
            if (count($lines) === $count) {
                break;
            }

            if ($product->inventoryStocks()->where('store_id', $order->stockStoreId())->value('quantity_in_stock') >= 1) {
                $lines[] = $order->addProduct($product, 1);
            }
        }

        return $lines;
    }

    private function method(): CustomerPaymentMethod
    {
        return fake()->randomElement([$this->cash, $this->visa, $this->debit]);
    }

    /**
     * Encaisse le montant exigé pour remettre ces quantités (sans rien remettre si aucune quantité).
     *
     * @param  array<int, int>  $quantities
     */
    private function pay(CustomerOrder $order, array $quantities = [], ?Money $amount = null): void
    {
        $order = $order->fresh();
        $amount ??= $order->amountRequiredFor($quantities);

        $order->pickUp($quantities, $amount->isPositive() ? [['method_id' => $this->method()->id, 'amount' => $amount]] : []);
    }

    private function pickup(CustomerOrder $order): void
    {
        $quantities = collect($this->stockLines($order))->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => 1])->all();
        $this->pay($order, $quantities);
    }

    private function deposit(CustomerOrder $order): void
    {
        $this->stockLines($order);
        $this->pay($order);
    }

    private function delivery(CustomerOrder $order, int $day): void
    {
        $lines = $this->stockLines($order);
        $order->addService($this->deliveryService, null, 1, 'Livraison au rez-de-chaussée');
        $this->pay($order, [], $order->fresh()->balance_due);

        $quantities = collect($lines)->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => 1])->all();
        $deliveryDay = $this->weekdayNear($day + 3);
        $delivery = app(ScheduleDelivery::class)->handle($order->fresh(), $quantities, $this->start->addDays($deliveryDay)->toDateString(), DeliveryPeriod::Pm);

        $this->on($deliveryDay, function () use ($order, $delivery): void {
            $lineQuantities = $delivery->lines()->pluck('quantity', 'id')->map(fn ($quantity): int => (int) $quantity)->all();
            $order->fresh()->loadDelivery($delivery->fresh(), $lineQuantities);
            $order->fresh()->completeDelivery($delivery->fresh(), $lineQuantities);

            foreach ($order->fresh()->lines()->where('service_id', $this->deliveryService->id)->get() as $serviceLine) {
                $order->fresh()->completeServiceLine($serviceLine);
            }
        });
    }

    /**
     * Premier jour de semaine à partir de $day (le livreur ne travaille pas la fin de semaine), sans dépasser la
     * période de démonstration : on recule alors au dernier jour de semaine de la période.
     */
    private function weekdayNear(int $day): int
    {
        while ($this->start->addDays($day)->isWeekend()) {
            $day++;
        }

        if ($day > self::DAYS - 1) {
            $day = self::DAYS - 1;

            while ($this->start->addDays($day)->isWeekend()) {
                $day--;
            }
        }

        return $day;
    }

    private function shipment(CustomerOrder $order, int $day): void
    {
        $lines = $this->stockLines($order, 1);

        if ($lines === []) {
            return;
        }

        $order->fresh()->addShippingFee();
        $this->pay($order, [], $order->fresh()->amountRequiredForShipment([$lines[0]->id => 1]));

        $shipment = app(RequestShipment::class)->handle($order->fresh(), [$lines[0]->id => 1]);
        $cost = Money::parse(fake()->numberBetween(25, 60));
        $shipment->chooseCarrier($this->carrier->id, $cost);
        $order->fresh()->shipDelivery($shipment->fresh(), $shipment->lines()->pluck('quantity', 'id')->map(fn ($quantity): int => (int) $quantity)->all(), 'TRK'.(900000 + $shipment->id));

        $this->on(min($day + 5, self::DAYS), fn () => SupplierInvoice::recordForShipments($this->carrier, [$shipment->id], [
            'invoice_number' => 'PUR-'.(70000 + $shipment->id),
            'invoice_date' => today()->toDateString(),
            'merchandise_total' => $cost->toDecimal(),
            'invoice_total' => $cost->plus($cost->percent(5))->plus($cost->percent(9.975))->toDecimal(),
        ], ['TPS' => $cost->percent(5)->toDecimal(), 'TVQ' => $cost->percent(9.975)->toDecimal()]));
    }

    /**
     * Produit commandé pour le client : commande fournisseur envoyée, reçue, facturée, puis ramassée.
     */
    private function specialOrder(CustomerOrder $order, int $day): void
    {
        $product = $this->products->where('supplier_id', $this->productSuppliers[1]->id)->random();
        $line = $order->addProduct($product, 1);
        $order->adjustLine($line->fresh(), 0, 1);
        $this->pay($order);

        $supplierOrder = $line->fresh()->supplierOrderLine->order;
        $supplierOrder->send();

        $this->on(min($day + 7, self::DAYS), function () use ($order, $line, $supplierOrder): void {
            $orderLine = $supplierOrder->lines()->where('product_id', $line->product_id)->firstOrFail();
            Reception::record($supplierOrder->supplier, [$orderLine->id => ['quantity' => $orderLine->quantity]]);
            $this->invoiceReception($supplierOrder);

            $this->pay($order, [$line->id => 1]);
        });
    }

    /**
     * Installation par un fournisseur de services : la commande de services complétée complète la ligne du client,
     * qui reste non payée (compte client) quelques jours, puis est payée.
     */
    private function service(CustomerOrder $order, int $day): void
    {
        $line = $order->addService($this->installation, $this->serviceSupplier->id, 1, 'Installation d\'un lave-vaisselle');
        $supplierOrder = $line->fresh()->supplierOrderLine->order;
        $supplierOrder->send();

        $this->on(min($day + 4, self::DAYS), function () use ($supplierOrder): void {
            $supplierOrder->fresh()->complete();

            $cost = Money::parse(120);
            $this->invoices[] = SupplierInvoice::recordForOrder($supplierOrder->fresh(), [
                'invoice_number' => 'S-'.(3000 + $supplierOrder->id),
                'invoice_date' => today()->toDateString(),
                'taxes' => ['TPS' => $cost->percent(5)->toDecimal(), 'TVQ' => $cost->percent(9.975)->toDecimal()],
                'invoice_total' => $cost->plus($cost->percent(5))->plus($cost->percent(9.975))->toDecimal(),
            ]);
        });

        $this->on(min($day + 8, self::DAYS), fn () => $this->pay($order, [], $order->fresh()->balance_due));
    }

    private function return(CustomerOrder $order, int $day): void
    {
        $lines = $this->stockLines($order, 1);

        if ($lines === []) {
            return;
        }

        $this->pay($order, [$lines[0]->id => 1]);

        $this->on(min($day + 3, self::DAYS), function () use ($order, $lines): void {
            $pickedUp = $order->fresh()->lines()->where('product_id', $lines[0]->product_id)->firstOrFail();
            $refund = $order->fresh()->refundableFor($pickedUp, 1);
            $order->fresh()->returnLine($pickedUp, 1, refund: true, refunds: [['method_id' => $this->visa->id, 'amount' => $refund]]);
        });
    }

    private function cancellation(CustomerOrder $order): void
    {
        $product = $this->products->where('supplier_id', $this->productSuppliers[2]->id)->random();
        $line = $order->addProduct($product, 1);
        $order->adjustLine($line->fresh(), 0, 1);
        $this->pay($order);
        $line->fresh()->supplierOrderLine->order->send();

        $order->fresh()->cancelLineWithFee($line->fresh());
    }

    private function custom(CustomerOrder $order): void
    {
        $order->addCustomProduct($this->customTemplate, 1, 'Sofa 84 po, tissu lin gris, pattes noires', Money::parse(950), Money::parse(2199), 'SM-'.(400 + $order->id));
        $this->pay($order);
    }

    /**
     * Paiements de la plupart des factures fournisseur : au complet (escompte compris si encore offert) ou en partie.
     */
    private function supplierPayments(): void
    {
        $this->info('Paiements aux fournisseurs…');

        $this->on(self::DAYS - 10, function (): void {
            foreach ($this->invoices as $index => $invoice) {
                $invoice = $invoice->fresh();

                if ($index % 4 === 3 || $invoice->isPaid()) {
                    continue;
                }

                $isForeign = $invoice->supplier->conversionRate() !== '1';
                $discount = $invoice->discountAvailableOn(today());
                $amount = $invoice->balanceDue()->minus($discount ? $invoice->discount_amount : Money::zero());

                if ($index % 4 === 2) {
                    $amount = $amount->multiply('0.5');
                    $discount = false;
                }

                app(RecordSupplierPayment::class)->handle(
                    $this->admin,
                    $invoice->supplier,
                    LedgerAccount::forKey($isForeign ? LedgerAccount::CASH_USD : LedgerAccount::CASH_CAD),
                    today(),
                    SupplierPaymentMethod::Transfer,
                    'VIR-'.(800 + $index),
                    [$invoice->id => ['amount' => $amount, 'discount' => $discount]],
                    $isForeign ? $amount->multiply('1.37') : Money::zero(),
                );
            }
        });
    }

    /**
     * Dépôts des processeurs de cartes : le solde de chaque compte de compensation passe à l'encaisse, moins 2,5 % de
     * frais; frais bancaires du mois.
     */
    private function cardDeposits(): void
    {
        $this->on(self::DAYS - 2, function (): void {
            foreach ([$this->visa, $this->debit] as $method) {
                $account = $method->cashAccount();
                $balance = Money::of((int) $account->lines()->selectRaw('coalesce(sum(debit) - sum(credit), 0) as balance')->value('balance'));

                if (! $balance->isPositive()) {
                    continue;
                }

                $fees = $balance->percent(2.5);

                app(RecordJournalEntry::class)->handle($this->admin, today(), 'Dépôt '.$method->name, 'Relevé du processeur', $this->store->id, [
                    ['account' => LedgerAccount::forKey(LedgerAccount::CASH_CAD), 'debit' => $balance->minus($fees), 'credit' => Money::zero()],
                    ['account' => LedgerAccount::query()->where('code', '5610')->firstOrFail(), 'debit' => $fees, 'credit' => Money::zero()],
                    ['account' => $account, 'debit' => Money::zero(), 'credit' => $balance],
                ]);
            }

            app(RecordJournalEntry::class)->handle($this->admin, today(), 'Frais bancaires du mois', 'Relevé bancaire', null, [
                ['account' => LedgerAccount::query()->where('code', '5620')->firstOrFail(), 'debit' => Money::parse(18.5), 'credit' => Money::zero()],
                ['account' => LedgerAccount::forKey(LedgerAccount::CASH_CAD), 'debit' => Money::zero(), 'credit' => Money::parse(18.5)],
            ]);
        });
    }

    /**
     * Clôture le mois du début de la période de démonstration.
     */
    private function closeOldPeriod(): void
    {
        $this->on(self::DAYS, function (): void {
            $through = $this->start->endOfMonth();

            if ($through->lessThan(today()) && LedgerClosing::closedThrough() === null) {
                LedgerClosing::close($through, $this->admin);
            }
        });
    }
}

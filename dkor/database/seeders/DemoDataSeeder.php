<?php

namespace Database\Seeders;

use App\Actions\AcceptStoreTransfer;
use App\Actions\AdjustInventory;
use App\Actions\CloseStoreTransfer;
use App\Actions\LockPayrollPeriod;
use App\Actions\ReceiveStoreTransfer;
use App\Actions\RecordJournalEntry;
use App\Actions\RecordSupplierPayment;
use App\Actions\RequestShipment;
use App\Actions\RequestStoreTransfer;
use App\Actions\ScheduleDelivery;
use App\Enums\CustomerOrderLineStatus;
use App\Enums\DeliveryPeriod;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\InventoryMovementType;
use App\Enums\InventoryStatus;
use App\Enums\LedgerAccountType;
use App\Enums\StoreType;
use App\Enums\SupplierOrderStatus;
use App\Enums\SupplierPaymentMethod;
use App\Enums\SupplierType;
use App\Models\Currency;
use App\Models\customer;
use App\Models\CustomerOrder;
use App\Models\CustomerOrderLine;
use App\Models\CustomerPaymentMethod;
use App\Models\DeliveryTruck;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\InventoryZone;
use App\Models\LedgerAccount;
use App\Models\LedgerClosing;
use App\Models\PayrollPeriod;
use App\Models\Product;
use App\Models\ProductClearance;
use App\Models\ProductDemo;
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
use App\Support\DeliveryBooking;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Données de démonstration cohérentes pour essayer l'application de bout en bout, sur environ trois mois et quatre
 * magasins :
 *
 * - **Bureau chef** (virtuel) : propriétaire et comptabilité, aucune vente ; les fournisseurs lui facturent les
 *   commandes des trois magasins (`billing_store_id`).
 * - **DKOR Rimouski** : son propre stock, zones et emplacements, entrepôt d'expédition, deux camions.
 * - **DKOR Rivière-du-Loup** : son propre stock (une partie du catalogue seulement), zones et emplacements, un camion.
 * - **DKOR Mont-Joli** : servi par l'entrepôt de Rimouski (`warehouse_store_id`), un camion.
 *
 * Fournisseurs de tous types (CAD, USD, sur mesure, services, transport), catalogue d'environ 120 produits, clients
 * de chaque ville, achats reçus et facturés (avec écarts, réceptions partielles et commandes encore en route), ventes
 * à tous les stades (dépôt, ramassage, livraison, expédition, commande spéciale, service, retour, annulation avec frais,
 * sur mesure, démonstrateur, liquidation, transfert pour un client), transferts de stock entre magasins, ajustements,
 * paiements aux fournisseurs, dépôts des cartes, paies verrouillées et mois clôturé.
 *
 * Tout passe par les méthodes de l'application, jour après jour (un agenda exécuté dans l'ordre chronologique) :
 * stock, unités FIFO et grand livre concordent (`ledger:check`). Ce qui est prévu après aujourd'hui (livraisons,
 * réceptions, transferts) reste en cours, pour que l'application ait aussi du travail à venir.
 *
 * À lancer après DatabaseSeeder : `php artisan migrate:fresh --seed` puis `php artisan db:seed --class=DemoDataSeeder`.
 */
class DemoDataSeeder extends Seeder
{
    private const DAYS = 90;

    /** Jours d'horaire publiés après aujourd'hui, pour planifier des livraisons et prendre des rendez-vous. */
    private const SCHEDULE_DAYS_AHEAD = 28;

    /** Premier jour de ventes : le stock initial est reçu et les démonstrateurs sont en place. */
    private const SALES_START = 14;

    /** Produits par fournisseur de produits. */
    private const PRODUCTS_PER_SUPPLIER = 15;

    private Store $headOffice;

    private Store $rimouski;

    private Store $riviereDuLoup;

    private Store $montJoli;

    /** @var list<Store> Magasins qui vendent. */
    private array $salesStores = [];

    /** @var list<Store> Magasins qui tiennent du stock (entrepôts). */
    private array $stockStores = [];

    private User $admin;

    /** @var array<int, list<User>> Vendeurs, par magasin. */
    private array $salespeople = [];

    private CustomerPaymentMethod $cash;

    private CustomerPaymentMethod $visa;

    private CustomerPaymentMethod $debit;

    /** @var Collection<int, Supplier> */
    private Collection $productSuppliers;

    private Supplier $customSupplier;

    private Supplier $serviceSupplier;

    private Supplier $carrier;

    /** @var Collection<int, Product> Produits du catalogue, par id. */
    private Collection $products;

    /** @var array<int, list<int>> Produits tenus en stock, par magasin d'entrepôt. */
    private array $carried = [];

    private Product $customTemplate;

    private Service $deliveryService;

    private Service $installation;

    /** @var array<int, Collection<int, customer>> Clients de la ville de chaque magasin. */
    private array $customers = [];

    /** @var Collection<int, customer> */
    private Collection $allCustomers;

    /** @var list<SupplierInvoice> */
    private array $invoices = [];

    private CarbonImmutable $start;

    /** @var array<int, list<callable(): void>> Gestes à poser, par jour de la période. */
    private array $agenda = [];

    /** Jour de la période en cours d'exécution. */
    private int $day = 0;

    private int $orderCount = 0;

    public function run(): void
    {
        $this->admin = User::query()->where('email', 'testadmin@dkor.ca')->first() ?? $this->seedBase();
        Auth::login($this->admin);
        $this->start = CarbonImmutable::today()->subDays(self::DAYS);

        try {
            $this->on(0, function (): void {
                $this->setUpStores();
                $this->setUpEmployees();
                $this->setUpPaymentMethods();
                $this->setUpSuppliersAndProducts();
                $this->setUpLocations();
                $this->setUpCustomers();
                $this->openingBalance();
            });

            $this->planPurchases();
            $this->at(self::SALES_START - 1, fn () => $this->setUpDemosAndClearances());
            $this->planSales();
            $this->planStockTransfers();
            $this->planAdjustments();
            $this->planSupplierPayments();
            $this->planCardDeposits();

            $this->info('Trois mois d\'activité…');
            $this->runAgenda();

            $this->on(self::DAYS, function (): void {
                $this->lockPayrollPeriods();
                $this->closeOldPeriod();
            });
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

    /**
     * Prévoit $callback pour le jour $day (jamais avant le jour en cours). Ce qui est prévu après la période reste à
     * faire : c'est le travail à venir de l'application.
     */
    private function at(int $day, callable $callback): void
    {
        $this->agenda[max($day, $this->day)][] = $callback;
    }

    /**
     * Exécute l'agenda dans l'ordre chronologique ; un geste peut en prévoir d'autres, plus tard ou le jour même.
     */
    private function runAgenda(): void
    {
        // DEMO_SEEDER_TRACE=1 (le CI) : une ligne par geste sur stderr, pour voir où le seeder s'attarde.
        $trace = getenv('DEMO_SEEDER_TRACE') === '1';
        $started = microtime(true);

        for ($this->day = 0; $this->day <= self::DAYS; $this->day++) {
            $gesture = 0;

            while (! empty($this->agenda[$this->day])) {
                if ($trace) {
                    fwrite(STDERR, sprintf("[seeder] jour %d, geste %d, %.1f s\n", $this->day, ++$gesture, microtime(true) - $started));
                }

                $this->on($this->day, array_shift($this->agenda[$this->day]));
            }
        }

        $this->day = self::DAYS;
    }

    private function info(string $message): void
    {
        $this->command->info($message);
    }

    /**
     * Le magasin principal créé par DatabaseSeeder devient le bureau chef virtuel ; les trois magasins qui vendent lui
     * sont facturés.
     */
    private function setUpStores(): void
    {
        $this->info('Magasins…');
        $officeHours = $this->openingHours(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], '08:30', '16:30');
        $storeHours = fn (bool $openOnSunday): array => [
            'monday' => ['open' => true, 'from' => '09:30', 'to' => '17:30'],
            'tuesday' => ['open' => true, 'from' => '09:30', 'to' => '17:30'],
            'wednesday' => ['open' => true, 'from' => '09:30', 'to' => '17:30'],
            'thursday' => ['open' => true, 'from' => '09:30', 'to' => '21:00'],
            'friday' => ['open' => true, 'from' => '09:30', 'to' => '21:00'],
            'saturday' => ['open' => true, 'from' => '09:30', 'to' => '17:00'],
            'sunday' => ['open' => $openOnSunday, 'from' => '12:00', 'to' => '17:00'],
        ];

        $this->headOffice = $this->admin->store ?? Store::query()->firstOrFail();
        $this->headOffice->update([
            'name' => 'Bureau chef',
            'type' => StoreType::Virtual,
            'phone' => '(418)555-0100',
            'email' => 'bureauchef@dkor.ca',
            ...$this->address('200', 'boulevard René-Lepage Est', 'Rimouski', 'G5L1P7'),
            'opening_hours' => $officeHours,
        ]);

        $common = ['cancellation_fee_percent' => 15, 'custom_deposit_percent' => 50, 'billing_store_id' => $this->headOffice->id];

        $this->rimouski = Store::factory()->create([...$common, 'name' => 'DKOR Rimouski', 'phone' => '(418)555-0110', 'email' => 'rimouski@dkor.ca', ...$this->address('415', 'montée Industrielle-et-Commerciale', 'Rimouski', 'G5M1A5'), 'opening_hours' => $storeHours(true)]);
        $this->rimouski->update(['shipping_warehouse_id' => $this->rimouski->id]);

        $this->riviereDuLoup = Store::factory()->create([...$common, 'name' => 'DKOR Rivière-du-Loup', 'phone' => '(418)555-0120', 'email' => 'rdl@dkor.ca', ...$this->address('298', 'boulevard Armand-Thériault', 'Rivière-du-Loup', 'G5R4C2'), 'opening_hours' => $storeHours(false)]);
        $this->riviereDuLoup->update(['shipping_warehouse_id' => $this->riviereDuLoup->id]);

        $this->montJoli = Store::factory()->create([...$common, 'name' => 'DKOR Mont-Joli', 'phone' => '(418)555-0130', 'email' => 'montjoli@dkor.ca', ...$this->address('1500', 'boulevard Jacques-Cartier', 'Mont-Joli', 'G5H2W1'), 'opening_hours' => $storeHours(false), 'warehouse_store_id' => $this->rimouski->id, 'shipping_warehouse_id' => $this->rimouski->id]);

        $this->salesStores = [$this->rimouski, $this->riviereDuLoup, $this->montJoli];
        $this->stockStores = [$this->rimouski, $this->riviereDuLoup];

        foreach ([$this->headOffice, ...$this->salesStores] as $store) {
            foreach (['TPS' => '123456789 RT0001', 'TVQ' => '1234567890 TQ0001'] as $tax => $number) {
                StoreTaxRegistration::query()->firstOrCreate(['store_id' => $store->id, 'tax_name' => $tax], ['number' => $number]);
            }
        }

        foreach ([[$this->rimouski, 'Camion 1'], [$this->rimouski, 'Camion 2'], [$this->riviereDuLoup, 'Camion RDL'], [$this->montJoli, 'Camion MJ']] as [$store, $name]) {
            DeliveryTruck::factory()->create(['store_id' => $store->id, 'name' => $name, 'capacity_per_period' => 3]);
        }
    }

    /**
     * @param  list<string>  $openDays
     * @return array<string, array{open: bool, from: string, to: string}>
     */
    private function openingHours(array $openDays, string $from, string $to): array
    {
        return collect(Store::DAYS)
            ->mapWithKeys(fn (string $day): array => [$day => ['open' => in_array($day, $openDays, true), 'from' => $from, 'to' => $to]])
            ->all();
    }

    /** @return array<string, string> */
    private function address(string $civic, string $street, string $city, string $postalCode): array
    {
        return [
            'address_civic' => $civic,
            'address_street' => $street,
            'address_city' => $city,
            'address_province' => 'QC',
            'address_country' => 'CA',
            'address_postal_code' => $postalCode,
        ];
    }

    /**
     * Employés de chaque magasin (l'administrateur existe déjà), tous avec le mot de passe « password ». Les vendeurs
     * sont à commission et à primes.
     * Les livreurs travaillent du lundi au vendredi ; les autres ont des quarts aléatoires dans les heures d'ouverture
     * de leur magasin, pour tester les rendez-vous et la paie. Les horaires couvrent aussi les semaines à venir.
     */
    private function setUpEmployees(): void
    {
        $this->info('Employés…');
        $employees = [
            [$this->headOffice, 'owner', 'Paul', 'Proprio', 'proprio'],
            [$this->headOffice, 'accounting', 'Claire', 'Comptable', 'comptable'],
            [$this->headOffice, 'accounting', 'Hélène', 'Paradis', 'comptable2'],

            [$this->rimouski, 'manager', 'Marie', 'Directrice', 'directeur'],
            [$this->rimouski, 'salesman', 'Sophie', 'Vendeuse', 'vendeur'],
            [$this->rimouski, 'salesman', 'Marc', 'Vendeur', 'vendeur2'],
            [$this->rimouski, 'salesman', 'Julie', 'Tremblay', 'vendeur3'],
            [$this->rimouski, 'design', 'Diane', 'Designer', 'designer'],
            [$this->rimouski, 'delivery', 'Luc', 'Livreur', 'livreur'],
            [$this->rimouski, 'delivery', 'Kevin', 'Gagnon', 'livreur2'],
            [$this->rimouski, 'warehouse', 'Eric', 'Commis', 'entrepot'],
            [$this->rimouski, 'thirdkey', 'Tom', 'Clef', 'troisiemecle'],

            [$this->riviereDuLoup, 'manager', 'Nathalie', 'Pelletier', 'directeur.rdl'],
            [$this->riviereDuLoup, 'salesman', 'Alexandre', 'Dionne', 'vendeur.rdl'],
            [$this->riviereDuLoup, 'salesman', 'Isabelle', 'Ouellet', 'vendeur2.rdl'],
            [$this->riviereDuLoup, 'design', 'Mélanie', 'Bérubé', 'designer.rdl'],
            [$this->riviereDuLoup, 'delivery', 'Patrick', 'Lévesque', 'livreur.rdl'],
            [$this->riviereDuLoup, 'warehouse', 'Simon', 'Gagné', 'entrepot.rdl'],
            [$this->riviereDuLoup, 'thirdkey', 'Chantal', 'Roy', 'troisiemecle.rdl'],

            [$this->montJoli, 'manager', 'François', 'Côté', 'directeur.mj'],
            [$this->montJoli, 'salesman', 'Émilie', 'Lavoie', 'vendeur.mj'],
            [$this->montJoli, 'salesman', 'Jérôme', 'Bélanger', 'vendeur2.mj'],
            [$this->montJoli, 'delivery', 'Mathieu', 'Caron', 'livreur.mj'],
            [$this->montJoli, 'thirdkey', 'Annie', 'Dubé', 'troisiemecle.mj'],
        ];

        foreach ($employees as [$store, $role, $firstname, $lastname, $emailName]) {
            $user = User::factory()->withRole($role)->create([
                'store_id' => $store->id,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'email' => $emailName.'@dkor.ca',
                'first_day' => $this->start->subMonths(fake()->numberBetween(4, 30))->toDateString(),
                ...($role === 'salesman' ? ['has_commission' => true, 'commission_rate' => '3.00', 'has_bonus' => true, 'weekly_sales_target' => 3000, 'bonus_amount' => 25, 'bonus_step' => 500] : []),
            ]);

            if ($role === 'salesman') {
                $this->salespeople[$store->id][] = $user;
            }

            match ($role) {
                'delivery' => $this->publishDriverSchedule($user),
                'owner' => null,
                default => $this->publishRandomSchedule($user, $store),
            };
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
    private function publishRandomSchedule(User $user, Store $store): void
    {
        $end = $this->start->addDays(self::DAYS + self::SCHEDULE_DAYS_AHEAD);

        for ($week = $this->start->startOfWeek(); $week->lte($end); $week = $week->addWeek()) {
            $openDays = collect(fake()->shuffleArray(range(0, 6)))
                ->map(fn (int $offset): CarbonImmutable => $week->addDays($offset))
                ->filter(fn (CarbonImmutable $date): bool => $date->between($this->start, $end) && ($store->opening_hours[strtolower($date->englishDayOfWeek)]['open'] ?? false))
                ->take(fake()->numberBetween(4, 5))
                ->sortBy(fn (CarbonImmutable $date): int => $date->getTimestamp());
            $remainingMinutes = 40 * 60;

            foreach ($openDays as $date) {
                $hours = $store->opening_hours[strtolower($date->englishDayOfWeek)];
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

    /**
     * Huit fournisseurs de produits (deux en USD) de quinze produits chacun. Rimouski tient tout le catalogue ;
     * Rivière-du-Loup, deux produits sur trois : le reste lui arrive par transfert ou par commande.
     */
    private function setUpSuppliersAndProducts(): void
    {
        $this->info('Fournisseurs et produits…');
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $this->productSuppliers = collect([
            ['name' => 'Meubles Laurentides', 'early_payment_discount_percent' => 2, 'early_payment_discount_days' => 10],
            ['name' => 'Matelas Boréal'],
            ['name' => 'Luminaires Saint-Laurent'],
            ['name' => 'Décor Charlevoix'],
            ['name' => 'Meubles Bas-du-Fleuve', 'early_payment_discount_percent' => 1, 'early_payment_discount_days' => 15],
            ['name' => 'Tapis et Textiles Gaspésie'],
            ['name' => 'Ashford Furniture (USD)', 'currency_id' => $usd->id],
            ['name' => 'Carolina Outdoor (USD)', 'currency_id' => $usd->id],
        ])->map(fn (array $attributes): Supplier => Supplier::factory()->create([...$attributes, 'type' => SupplierType::Product, 'base_multiplier' => 2, 'address_province' => 'QC']));

        $this->customSupplier = Supplier::factory()->create(['name' => 'Atelier Sur Mesure Gaspésie', 'type' => SupplierType::Product, 'custom_made' => true, 'base_multiplier' => 2]);
        $this->serviceSupplier = Supplier::factory()->create(['name' => 'Installations Pro-Bas-Saint-Laurent', 'type' => SupplierType::Service]);
        $this->carrier = Supplier::factory()->create(['name' => 'Transport Purolator', 'type' => SupplierType::Shipping]);

        $families = [
            ['Sofa', 250, 900, 90, 38, 34, 60], ['Causeuse', 200, 700, 66, 38, 34, 45], ['Fauteuil', 120, 500, 36, 34, 36, 25],
            ['Table à manger', 200, 800, 72, 40, 30, 55], ['Chaise', 40, 150, 20, 22, 38, 8], ['Tabouret', 35, 120, 18, 18, 30, 6],
            ['Lampe sur pied', 40, 180, 14, 14, 62, 6], ['Lampe de table', 25, 90, 12, 12, 24, 3], ['Matelas', 200, 900, 80, 60, 12, 35],
            ['Sommier', 90, 300, 80, 60, 9, 30], ['Commode', 180, 600, 48, 20, 40, 50], ['Buffet', 220, 700, 60, 18, 34, 60],
            ['Bibliothèque', 90, 350, 36, 14, 72, 40], ['Table de chevet', 50, 180, 20, 16, 24, 12], ['Lit', 250, 900, 82, 62, 48, 70],
            ['Miroir', 40, 160, 30, 2, 40, 10], ['Tapis', 60, 400, 96, 1, 1, 15], ['Banc', 60, 220, 48, 16, 18, 15],
        ];

        $this->carried = [$this->rimouski->id => [], $this->riviereDuLoup->id => []];
        $this->products = collect();

        foreach ($this->productSuppliers as $index => $supplier) {
            foreach (range(1, self::PRODUCTS_PER_SUPPLIER) as $number) {
                [$family, $minCost, $maxCost, $length, $width, $height, $weight] = $families[($index * 7 + $number) % count($families)];

                $product = Product::factory()->create([
                    'supplier_id' => $supplier->id,
                    'model' => $family.' '.strtoupper(substr($supplier->name, 0, 3)).'-'.(100 + $number),
                    'cost' => Money::of(fake()->numberBetween($minCost, $maxCost) * 100),
                    'is_taxable' => true,
                    'length' => $length,
                    'width' => $width,
                    'height' => $height,
                    'weight' => $weight,
                ]);

                $this->products->put($product->id, $product);
                $this->carried[$this->rimouski->id][] = $product->id;

                if ($number % 3 !== 0) {
                    $this->carried[$this->riviereDuLoup->id][] = $product->id;
                }
            }
        }

        $this->customTemplate = Product::factory()->create(['supplier_id' => $this->customSupplier->id, 'model' => 'SOFA-SUR-MESURE', 'is_custom' => true, 'cost' => Money::parse(1)]);

        $this->deliveryService = Service::factory()->internal(Money::parse(89))->create(['name' => 'Livraison à domicile']);
        $this->installation = Service::factory()->create(['name' => 'Installation']);
        ServiceSupplier::query()->create(['service_id' => $this->installation->id, 'supplier_id' => $this->serviceSupplier->id, 'cost' => Money::parse(120), 'selling_price' => Money::parse(199)]);
    }

    /**
     * Zones et emplacements des deux entrepôts (salle de montre, deux allées), et emplacement habituel de chaque
     * produit tenu.
     */
    private function setUpLocations(): void
    {
        foreach ($this->stockStores as $store) {
            $prefix = $store->is($this->rimouski) ? 'R' : 'L';
            $warehouseLocations = [];

            $showroom = InventoryZone::factory()->create(['store_id' => $store->id, 'name' => 'Salle de montre', 'sort_order' => 1]);
            foreach (range(1, 12) as $number) {
                InventoryLocation::factory()->create(['inventory_zone_id' => $showroom->id, 'code' => sprintf('%s-SM-%02d', $prefix, $number), 'description' => 'Section '.$number.' de la salle de montre']);
            }

            foreach (['A' => 2, 'B' => 3] as $aisle => $sortOrder) {
                $zone = InventoryZone::factory()->create(['store_id' => $store->id, 'name' => 'Entrepôt — allée '.$aisle, 'sort_order' => $sortOrder]);

                foreach (range(1, 10) as $bay) {
                    foreach (range(1, 3) as $level) {
                        $warehouseLocations[] = InventoryLocation::factory()->create(['inventory_zone_id' => $zone->id, 'code' => sprintf('%s-%s-%02d-%d', $prefix, $aisle, $bay, $level)])->id;
                    }
                }
            }

            foreach ($this->carried[$store->id] as $productId) {
                $this->products[$productId]->setHabitualLocation($store->id, fake()->randomElement($warehouseLocations));
            }
        }
    }

    private function setUpCustomers(): void
    {
        $this->info('Clients…');
        $cities = [
            [$this->rimouski, 'Rimouski', 'G5L', 60],
            [$this->riviereDuLoup, 'Rivière-du-Loup', 'G5R', 45],
            [$this->montJoli, 'Mont-Joli', 'G5H', 45],
        ];

        foreach ($cities as [$store, $city, $prefix, $count]) {
            $this->customers[$store->id] = collect(range(1, $count))->map(fn (): customer => customer::factory()->create([
                'address_province' => 'QC',
                'address_country' => 'CA',
                'address_city' => $city,
                'address_postal_code' => strtoupper(fake()->bothify($prefix.'#?#')),
            ]));
        }

        $this->allCustomers = collect($this->customers)->flatten(1);
    }

    private function openingBalance(): void
    {
        app(RecordJournalEntry::class)->handle($this->admin, today(), 'Solde d\'ouverture', 'Relevé bancaire', null, [
            ['account' => LedgerAccount::forKey(LedgerAccount::CASH_CAD), 'debit' => Money::parse(250000), 'credit' => Money::zero()],
            ['account' => LedgerAccount::forKey(LedgerAccount::CASH_USD), 'debit' => Money::parse(40000), 'credit' => Money::zero()],
            ['account' => LedgerAccount::forKey(LedgerAccount::OPENING_BALANCE), 'debit' => Money::zero(), 'credit' => Money::parse(290000)],
        ]);
    }

    /**
     * Stock initial des deux entrepôts (une réception avec un manquant et un endommagé), réassort à mi-période (une
     * réception sur quatre pas encore facturée), puis commandes de fin de période : une partie reçue en partie,
     * le reste encore en route.
     */
    private function planPurchases(): void
    {
        foreach ($this->stockStores as $storeIndex => $store) {
            foreach ($this->productSuppliers as $index => $supplier) {
                $this->at(1 + $index + $storeIndex, fn () => $this->purchase($store, $supplier, 5, 3, $index === 0 && $storeIndex === 0 ? 'damaged' : 'none', $index % 2 === 0 ? 45 : 0));
                $this->at(42 + $index % 5, fn () => $this->purchase($store, $supplier, 5 + $index % 3, $index % 4 === 3 ? null : 3, 'none', $index % 3 === 0 ? 60 : 0));

                if (($index + $storeIndex) % 2 === 0) {
                    $this->at(self::DAYS - 9 + $index % 4, fn () => $this->purchase($store, $supplier, $index % 4 === 0 ? 4 : 30, null, $index % 4 === 0 ? 'partial' : 'none'));
                }
            }
        }
    }

    /**
     * Commande de réassort à ce fournisseur pour ce magasin : chaque produit tenu est remonté à son niveau cible.
     * La commande est envoyée, reçue $receiveAfter jours plus tard, puis facturée $invoiceAfter jours après (null :
     * reçue mais pas encore facturée).
     */
    private function purchase(Store $store, Supplier $supplier, int $receiveAfter, ?int $invoiceAfter, string $discrepancy = 'none', float $freight = 0): void
    {
        [$min, $max] = $store->is($this->rimouski) ? [5, 9] : [2, 4];
        $quantities = [];

        foreach ($this->products->where('supplier_id', $supplier->id)->whereIn('id', $this->carried[$store->id]) as $product) {
            $stock = $product->stockAt($store->id);
            $missing = fake()->numberBetween($min, $max) - (int) $stock?->quantity_in_stock - $this->quantityOnOrder($product, $store);

            if ($missing > 0) {
                $quantities[$product->id] = $missing;
            }
        }

        if ($quantities === []) {
            return;
        }

        $order = SupplierOrder::query()->where('supplier_id', $supplier->id)->where('store_id', $store->id)->where('status', SupplierOrderStatus::Draft)->first()
            ?? SupplierOrder::factory()->create(['supplier_id' => $supplier->id, 'store_id' => $store->id]);

        foreach ($quantities as $productId => $quantity) {
            $product = $this->products[$productId];
            SupplierOrderLine::factory()->forProduct($product)->create(['supplier_order_id' => $order->id, 'quantity' => $quantity, 'unit_cost' => $product->cost]);
        }

        $order->markPending();
        $order->fresh()->send();

        $this->at($this->day + $receiveAfter, fn () => $this->receive($order, $discrepancy, $invoiceAfter, $freight));
    }

    /** Quantité de stock déjà commandée et pas encore reçue pour ce magasin. */
    private function quantityOnOrder(Product $product, Store $store): int
    {
        return (int) SupplierOrderLine::query()
            ->where('product_id', $product->id)
            ->whereDoesntHave('customerOrderLine')
            ->whereHas('order', fn ($orders) => $orders->where('store_id', $store->id)->whereIn('status', [SupplierOrderStatus::Sent, SupplierOrderStatus::PartiallyReceived]))
            ->sum('quantity');
    }

    private function receive(SupplierOrder $order, string $discrepancy, ?int $invoiceAfter, float $freight): void
    {
        $receipts = $order->lines()->get()->mapWithKeys(fn (SupplierOrderLine $line, int $position): array => [
            $line->id => match (true) {
                $discrepancy === 'damaged' && $position === 0 && $line->quantity >= 3 => ['quantity' => $line->quantity - 2, 'damaged' => 1, 'short' => 1],
                $discrepancy === 'partial' && $position % 2 === 1 => null,
                default => ['quantity' => $line->quantity],
            },
        ])->filter()->all();

        Reception::start($order->supplier, $receipts, ['reference' => 'BL-'.(5000 + $order->id)])->complete();

        if ($invoiceAfter !== null) {
            $this->at($this->day + $invoiceAfter, fn () => $this->invoiceReception($order, $freight));
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
     * Dans chaque entrepôt : six articles placés en salle de montre avec leur fiche de démonstrateur (un sur trois
     * « tel quel »), deux autres en démo sans fiche, et cinq produits en liquidation (dont une qui prendra fin).
     */
    private function setUpDemosAndClearances(): void
    {
        $this->info('Démonstrateurs et liquidations…');
        $notes = ['Égratignure sur le côté gauche', 'Tissu légèrement décoloré', 'Modèle de plancher, très bon état', 'Petite marque sur le dessus', 'Coussin à remplacer', 'Sans boîte d\'origine'];

        foreach ($this->stockStores as $store) {
            $productIds = InventoryStock::query()->where('store_id', $store->id)->where('quantity_in_stock', '>=', 2)->pluck('product_id')->all();
            $chosen = fake()->randomElements($productIds, min(13, count($productIds)));

            foreach (array_slice($chosen, 0, 8) as $position => $productId) {
                $product = $this->products[$productId];
                InventoryMovement::record($product, $store->id, InventoryStatus::InStock, InventoryStatus::InDemo, 1, InventoryMovementType::Transfer, note: 'Placé en salle de montre', userId: $this->admin->id);

                if ($position < 6) {
                    $product->declareDemo($store->id, $this->roundedPrice($product->selling_price->multiply('0.7')), $notes[$position], $position % 3 === 0);
                }
            }

            foreach (array_slice($chosen, 8) as $position => $productId) {
                $product = $this->products[$productId];
                $product->clearAt($store->id, $this->roundedPrice($product->selling_price->multiply('0.6')));

                if ($position === 0) {
                    $this->at(60, fn () => $product->endClearanceAt($store->id));
                }
            }
        }
    }

    /** Prix arrondi aux 5 $, jamais sous 5 $. */
    private function roundedPrice(Money $price): Money
    {
        return Money::of(max(500, (int) round($price->cents() / 500) * 500));
    }

    /**
     * Ventes de chaque magasin, chaque jour ouvert : plus à Rimouski, plus le samedi, et un ramassage de plus par
     * magasin chaque jour de la dernière semaine pour que l'accueil montre des ventes du jour, de la semaine et du mois
     * (Rimouski ouvre aussi le dimanche).
     */
    private function planSales(): void
    {
        foreach (range(self::SALES_START, self::DAYS) as $day) {
            $date = $this->start->addDays($day);

            foreach ($this->salesStores as $store) {
                if (! ($store->opening_hours[strtolower($date->englishDayOfWeek)]['open'] ?? false)) {
                    continue;
                }

                $count = match (true) {
                    $store->is($this->rimouski) => fake()->numberBetween(1, 2),
                    $store->is($this->riviereDuLoup) => fake()->numberBetween(0, 2),
                    default => fake()->numberBetween(0, 1),
                } + ($date->isSaturday() ? 1 : 0);

                if ($day >= self::DAYS - 6) {
                    $this->at($day, fn () => $this->pickup($this->newOrder($store)));
                }

                for ($sale = 0; $sale < $count; $sale++) {
                    $this->at($day, fn () => $this->sell($store));
                }
            }
        }
    }

    private function sell(Store $store): void
    {
        $scenarios = ['pickup', 'delivery', 'deposit', 'pickup', 'demo', 'shipment', 'specialOrder', 'pickup', 'clearance', 'service', 'customerTransfer', 'delivery', 'return', 'pickup', 'cancellation', 'custom'];
        $scenario = $scenarios[$this->orderCount % count($scenarios)];
        $order = $this->newOrder($store);

        $this->{$scenario}($order);
    }

    /**
     * Commande d'un client de la ville du magasin (parfois d'une autre ville), vendue par un des vendeurs du magasin
     * ou, une fois sur cinq, partagée entre deux.
     */
    private function newOrder(Store $store): CustomerOrder
    {
        $index = $this->orderCount++;
        $customers = $index % 8 === 7 ? $this->allCustomers : $this->customers[$store->id];
        $salespeople = $this->salespeople[$store->id];

        $order = CustomerOrder::factory()->create(['customer_id' => fake()->randomElement($customers->all())->id, 'store_id' => $store->id]);
        $order->syncSalespeople($index % 5 === 0 && count($salespeople) > 1
            ? [['user_id' => $salespeople[0]->id, 'percent' => 50], ['user_id' => $salespeople[1]->id, 'percent' => 50]]
            : [['user_id' => fake()->randomElement($salespeople)->id, 'percent' => 100]]);

        return $order;
    }

    /**
     * Ajoute à la commande des produits en stock dans son entrepôt, un de chaque.
     *
     * @return list<CustomerOrderLine>
     */
    private function stockLines(CustomerOrder $order, int $count = 2): array
    {
        $productIds = InventoryStock::query()
            ->where('store_id', $order->stockStoreId())
            ->where('quantity_in_stock', '>', 0)
            ->whereIn('product_id', $this->products->keys())
            ->pluck('product_id')
            ->all();

        return array_values(array_map(
            fn (int $productId): CustomerOrderLine => $order->addProduct($this->products[$productId], 1),
            fake()->randomElements($productIds, min($count, count($productIds))),
        ));
    }

    /**
     * @param  list<CustomerOrderLine>  $lines
     * @return array<int, int>
     */
    private function quantitiesOf(array $lines): array
    {
        return collect($lines)->mapWithKeys(fn (CustomerOrderLine $line): array => [$line->id => $line->fresh()->quantity_reserved])->filter()->all();
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
        $lines = $this->stockLines($order, fake()->numberBetween(1, 3));

        if ($lines === []) {
            $this->custom($order);

            return;
        }

        $this->pay($order, $this->quantitiesOf($lines));
    }

    private function deposit(CustomerOrder $order): void
    {
        if ($this->stockLines($order) === []) {
            $this->custom($order);

            return;
        }

        $this->pay($order);
    }

    private function delivery(CustomerOrder $order): void
    {
        $lines = $this->stockLines($order);

        if ($lines === []) {
            $this->custom($order);

            return;
        }

        $order->addService($this->deliveryService, null, 1, 'Livraison au rez-de-chaussée');
        $this->pay($order, [], $order->fresh()->balance_due);

        [$deliveryDay, $period] = $this->deliverySlot($order->store, $this->day + fake()->numberBetween(2, 6));
        $delivery = app(ScheduleDelivery::class)->handle($order->fresh(), $this->quantitiesOf($lines), $this->start->addDays($deliveryDay)->toDateString(), $period);

        $this->at($deliveryDay, function () use ($order, $delivery): void {
            $lineQuantities = $delivery->lines()->pluck('quantity', 'id')->map(fn ($quantity): int => (int) $quantity)->all();
            $order->fresh()->loadDelivery($delivery->fresh(), $lineQuantities);
            $order->fresh()->completeDelivery($delivery->fresh(), $lineQuantities);

            foreach ($order->fresh()->lines()->where('service_id', $this->deliveryService->id)->get() as $serviceLine) {
                $order->fresh()->completeServiceLine($serviceLine);
            }
        });
    }

    /**
     * Première demi-journée de semaine libre pour les camions du magasin, à partir de $fromDay.
     *
     * @return array{int, DeliveryPeriod}
     */
    private function deliverySlot(Store $store, int $fromDay): array
    {
        for ($day = $fromDay; $day <= self::DAYS + self::SCHEDULE_DAYS_AHEAD; $day++) {
            $date = $this->start->addDays($day);

            if ($date->isWeekend()) {
                continue;
            }

            foreach ([DeliveryPeriod::Am, DeliveryPeriod::Pm] as $period) {
                if (DeliveryBooking::remaining($store->id, $date->toDateString(), $period) > 0) {
                    return [$day, $period];
                }
            }
        }

        throw new RuntimeException("Aucune place de livraison pour {$store->name}.");
    }

    private function shipment(CustomerOrder $order): void
    {
        $lines = $this->stockLines($order, 1);

        if ($lines === []) {
            $this->custom($order);

            return;
        }

        $quantities = [$lines[0]->id => 1];
        $order->fresh()->addShippingFee();
        $this->pay($order, [], $order->fresh()->amountRequiredForShipment($quantities));

        $shipment = app(RequestShipment::class)->handle($order->fresh(), $quantities);
        $cost = Money::parse(fake()->numberBetween(25, 60));
        $shipment->chooseCarrier($this->carrier->id, $cost);
        $order->fresh()->shipDelivery($shipment->fresh(), $shipment->lines()->pluck('quantity', 'id')->map(fn ($quantity): int => (int) $quantity)->all(), 'TRK'.(900000 + $shipment->id));

        $this->at($this->day + 5, fn () => SupplierInvoice::recordForShipments($this->carrier, [$shipment->id], [
            'invoice_number' => 'PUR-'.(70000 + $shipment->id),
            'invoice_date' => today()->toDateString(),
            'merchandise_total' => $cost->toDecimal(),
            'invoice_total' => $cost->plus($cost->percent(5))->plus($cost->percent(9.975))->toDecimal(),
        ], ['TPS' => $cost->percent(5)->toDecimal(), 'TVQ' => $cost->percent(9.975)->toDecimal()]));
    }

    /**
     * Produit de ce fournisseur qui n'est pas en liquidation dans l'entrepôt de la commande : il peut être commandé au
     * fournisseur pour le client.
     */
    private function orderableProduct(CustomerOrder $order, Supplier $supplier): Product
    {
        $cleared = ProductClearance::query()->active()->where('store_id', $order->stockStoreId())->pluck('product_id')->all();

        return fake()->randomElement($this->products->where('supplier_id', $supplier->id)->whereNotIn('id', $cleared)->all());
    }

    /**
     * Produit commandé pour le client : commande fournisseur envoyée, reçue et facturée une semaine plus tard, puis
     * ramassée.
     */
    private function specialOrder(CustomerOrder $order): void
    {
        $product = $this->orderableProduct($order, $this->productSuppliers[1]);
        $line = $order->addProduct($product, 1);
        $order->adjustLine($line->fresh(), 0, 1);
        $this->pay($order);

        $supplierOrder = $line->fresh()->supplierOrderLine->order;
        $supplierOrder->send();

        $this->at($this->day + 7, function () use ($order, $line, $supplierOrder): void {
            $orderLine = $supplierOrder->lines()->whereKey($line->fresh()->supplier_order_line_id)->first()
                ?? $supplierOrder->lines()->where('product_id', $line->product_id)->firstOrFail();
            Reception::record($supplierOrder->supplier, [$orderLine->id => ['quantity' => $orderLine->quantity]]);
            $this->invoiceReception($supplierOrder);

            $this->pay($order, [$line->id => 1]);
        });
    }

    /**
     * Installation par un fournisseur de services : la commande de services complétée complète la ligne du client,
     * qui reste non payée (compte client) quelques jours, puis est payée.
     */
    private function service(CustomerOrder $order): void
    {
        $line = $order->addService($this->installation, $this->serviceSupplier->id, 1, 'Installation d\'un lave-vaisselle');
        $supplierOrder = $line->fresh()->supplierOrderLine->order;
        $supplierOrder->send();

        $this->at($this->day + 4, function () use ($supplierOrder): void {
            $supplierOrder->fresh()->complete();

            $cost = Money::parse(120);
            $this->invoices[] = SupplierInvoice::recordForOrder($supplierOrder->fresh(), [
                'invoice_number' => 'S-'.(3000 + $supplierOrder->id),
                'invoice_date' => today()->toDateString(),
                'taxes' => ['TPS' => $cost->percent(5)->toDecimal(), 'TVQ' => $cost->percent(9.975)->toDecimal()],
                'invoice_total' => $cost->plus($cost->percent(5))->plus($cost->percent(9.975))->toDecimal(),
            ]);
        });

        $this->at($this->day + 8, fn () => $this->pay($order, [], $order->fresh()->balance_due));
    }

    private function return(CustomerOrder $order): void
    {
        $lines = $this->stockLines($order, 1);

        if ($lines === []) {
            $this->custom($order);

            return;
        }

        $this->pay($order, $this->quantitiesOf($lines));

        // Jamais de retour le dernier jour : un remboursement peut effacer les ventes du jour (un dimanche, seul
        // Rimouski vend), et l'accueil doit en montrer.
        $returnDay = $this->day + 3 === self::DAYS ? self::DAYS - 1 : $this->day + 3;

        $this->at($returnDay, function () use ($order, $lines): void {
            $pickedUp = $order->fresh()->lines()->where('product_id', $lines[0]->product_id)->where('status', CustomerOrderLineStatus::PickedUp)->firstOrFail();
            $refund = $order->fresh()->refundableFor($pickedUp, 1);
            $order->fresh()->returnLine($pickedUp, 1, refund: true, refunds: [['method_id' => $this->visa->id, 'amount' => $refund]]);
        });
    }

    private function cancellation(CustomerOrder $order): void
    {
        $product = $this->orderableProduct($order, $this->productSuppliers[2]);
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
     * Vente d'un démonstrateur libre de l'entrepôt de la commande, avec parfois un autre article, ramassés aussitôt.
     * Une fois sur deux, la vente est un ramassage ordinaire : des démonstrateurs restent en salle de montre.
     */
    private function demo(CustomerOrder $order): void
    {
        $demo = $this->orderCount % 32 < 16
            ? ProductDemo::query()->available()->where('store_id', $order->stockStoreId())->orderBy('id')->first()
            : null;

        if ($demo === null) {
            $this->pickup($order);

            return;
        }

        $lines = [$order->addDemo($demo), ...$this->stockLines($order, fake()->numberBetween(0, 1))];
        $this->pay($order, $this->quantitiesOf($lines));
    }

    /** Vente d'un article en liquidation encore en stock dans l'entrepôt de la commande. */
    private function clearance(CustomerOrder $order): void
    {
        $productId = ProductClearance::query()
            ->active()
            ->where('store_id', $order->stockStoreId())
            ->whereIn('product_id', InventoryStock::query()->where('store_id', $order->stockStoreId())->where('quantity_in_stock', '>', 0)->select('product_id'))
            ->value('product_id');

        if ($productId === null) {
            $this->pickup($order);

            return;
        }

        $this->pay($order, $this->quantitiesOf([$order->addProduct($this->products[$productId], 1)]));
    }

    /**
     * Article que l'entrepôt de la commande n'a pas, mais que l'autre entrepôt a : transfert pour le client, accepté
     * le lendemain, reçu deux jours plus tard, puis ramassé.
     */
    private function customerTransfer(CustomerOrder $order): void
    {
        $toStoreId = $order->stockStoreId();
        $fromStoreId = collect($this->stockStores)->first(fn (Store $store): bool => $store->id !== $toStoreId)->id;
        $productId = InventoryStock::query()
            ->where('store_id', $fromStoreId)
            ->where('quantity_in_stock', '>', 1)
            ->whereIn('product_id', $this->products->keys())
            ->whereNotIn('product_id', InventoryStock::query()->where('store_id', $toStoreId)->where('quantity_in_stock', '>', 0)->select('product_id'))
            ->inRandomOrder()
            ->value('product_id');

        if ($productId === null) {
            $this->pickup($order);

            return;
        }

        $product = $this->products[$productId];
        $line = $order->addProduct($product, 1);
        $this->pay($order);

        $transfer = app(RequestStoreTransfer::class)->handle($this->admin, $product, $fromStoreId, 1, $line->fresh());

        $this->at($this->day + 1, fn () => app(AcceptStoreTransfer::class)->handle($this->admin, $transfer->fresh()));
        $this->at($this->day + 3, fn () => app(ReceiveStoreTransfer::class)->handle($this->admin, $transfer->fresh(), 1));
        $this->at($this->day + 4, fn () => $this->pay($order, [$transfer->line()->firstOrFail()->id => 1]));
    }

    /**
     * Transferts de stock entre les deux entrepôts, surtout de Rimouski vers Rivière-du-Loup : acceptés le lendemain,
     * reçus le surlendemain (un avec un écart), un refusé, un annulé ; ceux de la fin de la période sont encore
     * demandés ou en transit.
     */
    private function planStockTransfers(): void
    {
        foreach ([18, 24, 31, 38, 45, 52, 59, 66, 73, 80, 87, 89, 90] as $position => $day) {
            $this->at($day, function () use ($position): void {
                [$from, $to] = $position % 4 === 3 ? [$this->riviereDuLoup, $this->rimouski] : [$this->rimouski, $this->riviereDuLoup];
                $productId = InventoryStock::query()->where('store_id', $from->id)->where('quantity_in_stock', '>=', 3)->whereIn('product_id', $this->products->keys())->inRandomOrder()->value('product_id');

                if ($productId === null) {
                    return;
                }

                $transfer = app(RequestStoreTransfer::class)->handle($this->admin, $this->products[$productId], $from->id, fake()->numberBetween(1, 2), toStoreId: $to->id);

                match ($position) {
                    4 => $this->at($this->day + 1, fn () => app(CloseStoreTransfer::class)->handle($this->admin, $transfer->fresh(), true, 'Réservé pour une vente au magasin')),
                    7 => $this->at($this->day + 1, fn () => app(CloseStoreTransfer::class)->handle($this->admin, $transfer->fresh(), false)),
                    default => $this->at($this->day + 1, function () use ($transfer, $position): void {
                        app(AcceptStoreTransfer::class)->handle($this->admin, $transfer->fresh());

                        $this->at($this->day + 1, fn () => app(ReceiveStoreTransfer::class)->handle($this->admin, $transfer->fresh(), $position === 2 ? max(0, $transfer->quantity - 1) : $transfer->quantity));
                    }),
                };
            });
        }
    }

    /** Quelques ajustements d'inventaire : bris, usage du magasin et article retrouvé. */
    private function planAdjustments(): void
    {
        $adjustments = [
            [30, $this->rimouski, InventoryAdjustmentReason::Breakage, false, 'Échappé au déchargement'],
            [47, $this->riviereDuLoup, InventoryAdjustmentReason::StoreUse, false, 'Utilisé pour la vitrine'],
            [62, $this->rimouski, InventoryAdjustmentReason::CountCorrection, true, 'Retrouvé dans l\'allée B'],
            [75, $this->riviereDuLoup, InventoryAdjustmentReason::Theft, false, 'Disparu de la salle de montre'],
        ];

        foreach ($adjustments as [$day, $store, $reason, $isEntry, $note]) {
            $this->at($day, function () use ($store, $reason, $isEntry, $note): void {
                $productId = InventoryStock::query()->where('store_id', $store->id)->where('quantity_in_stock', '>=', 2)->whereIn('product_id', $this->products->keys())->inRandomOrder()->value('product_id');

                if ($productId !== null) {
                    app(AdjustInventory::class)->handle($this->admin, $this->products[$productId], $store, $reason, InventoryStatus::InStock, $isEntry, 1, $note);
                }
            });
        }
    }

    /**
     * Deux tournées de paiements des factures fournisseur : au complet (escompte compris si encore offert) ou en
     * partie ; une facture sur quatre reste à payer.
     */
    private function planSupplierPayments(): void
    {
        foreach ([40, self::DAYS - 5] as $round => $day) {
            $this->at($day, function () use ($round): void {
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
                        'VIR-'.(800 + $round * 1000 + $index),
                        [$invoice->id => ['amount' => $amount, 'discount' => $discount]],
                        $isForeign ? $amount->multiply('1.37') : Money::zero(),
                    );
                }
            });
        }
    }

    /**
     * Dépôts mensuels des processeurs de cartes : le solde de chaque compte de compensation passe à l'encaisse, moins
     * 2,5 % de frais; frais bancaires du mois.
     */
    private function planCardDeposits(): void
    {
        foreach ([30, 60, self::DAYS - 2] as $day) {
            $this->at($day, function (): void {
                foreach ([$this->visa, $this->debit] as $method) {
                    $account = $method->cashAccount();
                    $balance = Money::of((int) $account->lines()->selectRaw('coalesce(sum(debit) - sum(credit), 0) as balance')->value('balance'));

                    if (! $balance->isPositive()) {
                        continue;
                    }

                    $fees = $balance->percent(2.5);

                    app(RecordJournalEntry::class)->handle($this->admin, today(), 'Dépôt '.$method->name, 'Relevé du processeur', null, [
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
    }

    /** Verrouille les périodes de paie terminées depuis au moins une semaine. */
    private function lockPayrollPeriods(): void
    {
        for ($period = PayrollPeriod::startFor($this->start); PayrollPeriod::endFor($period)->lt(today()->subWeek()); $period = PayrollPeriod::endFor($period)->addDay()) {
            app(LockPayrollPeriod::class)->lock($period, $this->admin);
        }
    }

    /**
     * Clôture le grand livre jusqu'à la fin de l'avant-dernier mois.
     */
    private function closeOldPeriod(): void
    {
        $through = today()->subMonthNoOverflow()->startOfMonth()->subDay();

        if ($through->greaterThanOrEqualTo($this->start->endOfMonth()->startOfDay()) && LedgerClosing::closedThrough() === null) {
            LedgerClosing::close($through, $this->admin);
        }
    }
}

<?php

namespace App\Support;

use App\Models\LabelRequest;
use App\Models\User;

/**
 * Source unique de la navigation de l'application : le tiroir de menu, la barre d'onglets mobile et la recherche
 * « spotlight » lisent toutes la même liste, filtrée selon les permissions de l'usager.
 *
 * @phpstan-type NavigationItem array{label: string, icon: string, route: string, url: string, active: bool, navigate: bool, badge: ?int}
 * @phpstan-type NavigationSection array{heading: ?string, items: list<NavigationItem>, active: bool}
 */
class Navigation
{
    /**
     * Pages proposées dans la barre d'onglets mobile, par ordre de priorité, avec un libellé assez court pour l'onglet ;
     * les trois premières accessibles sont retenues après l'accueil.
     *
     * @var array<string, string>
     */
    private const BOTTOM_TAB_PRIORITY = [
        'customer-orders.index' => 'Commandes',
        'deliveries.mine' => 'Livraisons',
        'inventory.my-counts' => 'Comptes',
        'receptions.index' => 'Réceptions',
        'customers.index' => 'Clients',
        'products.index' => 'Produits',
        'schedules.index' => 'Horaire',
    ];

    private const BOTTOM_TAB_COUNT = 3;

    /**
     * @return list<NavigationSection>
     */
    public function sections(User $user): array
    {
        $sections = [
            [null, [
                $this->item(__('Accueil'), 'home', 'home'),
            ]],
            ['Ventes', [
                $this->item(__('Clients'), 'user-group', 'customers.index', 'customers.*', $user->can('customers.view')),
                $this->item(__('Commandes clients'), 'shopping-cart', 'customer-orders.index', 'customer-orders.*', $user->can('customer_orders.view')),
            ]],
            ['Livraisons', [
                $this->item(__('Mes livraisons'), 'truck', 'deliveries.mine', allowed: $user->can('deliveries.handle')),
                $this->item(__('Expéditions'), 'paper-airplane', 'shipments.index', 'shipments.*', $user->can('shipments.manage')),
                $this->item(__('Livraisons'), 'map-pin', 'deliveries.index', allowed: $user->can('deliveries.view')),
            ]],
            ['Achats', [
                $this->item(__('Fournisseurs'), 'building-storefront', 'suppliers.index', 'suppliers.*', $user->can('suppliers.view')),
                $this->item(__('Commandes fournisseurs'), 'truck', 'supplier-orders.index', 'supplier-orders.*', $user->can('supplier_orders.view')),
                $this->item(__('Réceptions'), 'inbox-arrow-down', 'receptions.index', 'receptions.*', $user->can('receptions.view')),
            ]],
            ['Inventaire', [
                $this->item(__('Transferts'), 'arrows-right-left', 'inventory.transfers', allowed: $user->canAny(['transfers.request', 'transfers.accept', 'transfers.receive'])),
                $this->item(__('Journal d\'inventaire'), 'clipboard-document-list', 'inventory.movements', allowed: $user->can('inventory.view')),
                $this->item(__('Ajustements d\'inventaire'), 'adjustments-horizontal', 'inventory.adjustments', allowed: $user->can('inventory.adjust')),
                $this->item(__('Prises d\'inventaire'), 'clipboard-document-check', 'inventory.counts', 'inventory.counts*', $user->can('inventory.count')),
                $this->item(__('Mes comptes'), 'qr-code', 'inventory.my-counts', allowed: $user->can('inventory.count')),
                $this->item(__('Zones et emplacements'), 'map', 'inventory.zones', allowed: $user->can('inventory.supervise'), navigate: false),
            ]],
            ['Catalogue', [
                $this->item(__('Produits'), 'cube', 'products.index', 'products.*', $user->can('products.view')),
                $this->item(__('Pièces'), 'puzzle-piece', 'parts.index', 'parts.*', $user->can('parts.view')),
                $this->item(__('Services'), 'wrench', 'catalog.services', allowed: $user->can('services.view')),
                $this->item(__('Classification'), 'tag', 'catalog.classification', allowed: $user->canAny(['departments.view', 'categories.view', 'colors.view', 'styles.view'])),
                $this->item(__('Étiquettes'), 'printer', 'labels.index', 'labels.*', $user->can('labels.view'), badge: fn (): int => (int) LabelRequest::query()->pending()->sum('quantity')),
                $this->item(__('Listes de prix'), 'currency-dollar', 'catalog.price-lists', 'catalog.price-lists*', $user->can('price_lists.view')),
            ]],
            ['Comptabilité', [
                $this->item(__('Facturation fournisseurs'), 'document-text', 'accounting.invoices', 'accounting.invoices*', $user->can('invoices.view')),
                $this->item(__('Comptes à payer'), 'banknotes', 'accounting.payables', allowed: $user->can('supplier_payments.view')),
                $this->item(__('Paie'), 'banknotes', 'accounting.payroll', allowed: $user->can('payroll.view')),
                $this->item(__('Modes de paiement'), 'credit-card', 'accounting.payment-methods', allowed: $user->can('payment_methods.view')),
                $this->item(__('Taxes'), 'receipt-percent', 'accounting.taxes', allowed: $user->can('taxes.view')),
                $this->item(__('Devises'), 'currency-dollar', 'accounting.currencies', allowed: $user->can('currencies.view')),
                $this->item(__('Grand livre'), 'book-open', 'accounting.ledger', allowed: $user->can('ledger.view')),
                $this->item(__('États comptables'), 'document-chart-bar', 'accounting.statements', allowed: $user->can('ledger.view')),
            ]],
            ['Gestion horaire', [
                $this->item(__('Mon horaire'), 'calendar-days', 'schedules.index', allowed: $user->can('schedules.view')),
                $this->item(__('Gestion des horaires'), 'table-cells', 'schedules.schedule-edit', allowed: $user->can('schedule_management.view'), badge: fn (): int => AppointmentBooking::uncoveredCount()),
                $this->item(__('Modèles d\'horaire'), 'squares-2x2', 'schedules.templates', allowed: $user->can('schedule_templates.view')),
                $this->item(__('Jours fériés'), 'sun', 'schedules.holidays', allowed: $user->can('holidays.view')),
                $this->item(__('Rendez-vous'), 'clock', 'schedules.appointments', allowed: $user->can('appointments.view')),
            ]],
            ['Administration', [
                $this->item(__('Magasins'), 'building-storefront', 'stores.index', 'stores.*', $user->can('stores.view')),
                $this->item(__('Utilisateurs'), 'users', 'users.index', 'users.*', $user->can('users.view')),
                $this->item(__('Positions'), 'briefcase', 'admin.positions', allowed: $user->can('positions.manage')),
                $this->item(__('Rôles'), 'shield-check', 'admin.roles', allowed: $user->can('roles.manage')),
                $this->item(__('Permissions'), 'key', 'admin.permissions', allowed: $user->can('permissions.manage')),
            ]],
        ];

        $visibleSections = [];

        foreach ($sections as [$heading, $items]) {
            $items = array_values(array_filter($items));

            if ($items === []) {
                continue;
            }

            $visibleSections[] = [
                'heading' => $heading !== null ? (string) __($heading) : null,
                'items' => $items,
                'active' => in_array(true, array_column($items, 'active'), true),
            ];
        }

        return $visibleSections;
    }

    /**
     * L'accueil suivi des pages prioritaires accessibles à l'usager, pour la barre d'onglets mobile.
     *
     * @param  list<NavigationSection>  $sections  Les sections déjà calculées par sections(), pour ne pas refaire les badges.
     * @return list<NavigationItem>
     */
    public function bottomTabs(array $sections): array
    {
        $itemsByRoute = array_column(array_merge(...array_column($sections, 'items')), null, 'route');

        $tabs = [];

        foreach (self::BOTTOM_TAB_PRIORITY as $route => $shortLabel) {
            if (isset($itemsByRoute[$route])) {
                $tabs[] = [...$itemsByRoute[$route], 'label' => __($shortLabel)];
            }
        }

        return [$itemsByRoute['home'], ...array_slice($tabs, 0, self::BOTTOM_TAB_COUNT)];
    }

    /**
     * @param  (callable(): int)|null  $badge  Calculé seulement si l'usager voit la page.
     * @return NavigationItem|null
     */
    private function item(string $label, string $icon, string $route, ?string $activePattern = null, bool $allowed = true, ?callable $badge = null, bool $navigate = true): ?array
    {
        if (! $allowed) {
            return null;
        }

        $badgeCount = $badge !== null ? $badge() : 0;

        return [
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            'url' => route($route),
            'active' => request()->routeIs($activePattern ?? $route),
            'navigate' => $navigate,
            'badge' => $badgeCount > 0 ? $badgeCount : null,
        ];
    }
}

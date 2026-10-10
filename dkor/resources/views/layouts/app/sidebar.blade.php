<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    <style>
        /* Sidebar positionnée à droite */
        *:has(>[data-flux-main]) {
            grid-template-areas:
                "header  header  header"
                "aside   main    sidebar"
                "aside   footer  sidebar" !important;
        }
        *:has(>[data-flux-sidebar]+[data-flux-header]) {
            grid-template-areas:
                "header  header  header"
                "aside   main    sidebar"
                "aside   footer  sidebar" !important;
        }

        /* ── Mobile : ancrage à droite ─────────────────────────── */
        [data-flux-sidebar-on-mobile] {
            inset-inline-start: auto !important;
            inset-inline-end: 0 !important;
        }

        /* Collapse → bande icônes (au lieu de tout masquer) */
        [data-flux-sidebar-on-mobile][data-flux-sidebar-collapsed-mobile] {
            transform: translateX(calc(100% - 3.5rem)) !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }

        /* Icônes centrées dans la bande mobile */
        [data-flux-sidebar-on-mobile][data-flux-sidebar-collapsed-mobile] [data-flux-sidebar-item] {
            width: 2.5rem !important;
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }

        /* Masquer le texte dans la bande mobile */
        [data-flux-sidebar-on-mobile][data-flux-sidebar-collapsed-mobile] [data-content] {
            display: none !important;
        }

        /* Masquer l'en-tête (logo + bouton collapse) dans la bande mobile */
        [data-flux-sidebar-on-mobile][data-flux-sidebar-collapsed-mobile] [data-flux-sidebar-header] {
            display: none !important;
        }

        /* Masquer le menu utilisateur dans la bande mobile */
        [data-flux-sidebar-on-mobile][data-flux-sidebar-collapsed-mobile] [data-sidebar-user-menu] {
            display: none !important;
        }
    </style>
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar
            sticky
            collapsible="true"
            class="border-s border-zinc-200 bg-zinc-50/95 backdrop-blur-sm dark:border-zinc-700 dark:bg-zinc-900/95"
        >
            <flux:sidebar.header class="justify-between">
                <div class="in-data-flux-sidebar-collapsed-desktop:hidden min-w-0 flex-1">
                    <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate />
                </div>
                <flux:sidebar.collapse />
            </flux:sidebar.header>

            <flux:sidebar.nav class="mt-2">
                <flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('Accueil') }}
                </flux:sidebar.item>

                @can('customers.view')
                    <flux:sidebar.item icon="user-group" :href="route('customers.index')" :current="request()->routeIs('customers.*')" wire:navigate>
                        {{ __('Clients') }}
                    </flux:sidebar.item>
                @endcan

                @can('customer_orders.view')
                    <flux:sidebar.item icon="shopping-cart" :href="route('customer-orders.index')" :current="request()->routeIs('customer-orders.*')" wire:navigate>
                        {{ __('Commandes clients') }}
                    </flux:sidebar.item>
                @endcan

                @can('suppliers.view')
                    <flux:sidebar.item icon="building-storefront" :href="route('suppliers.index')" :current="request()->routeIs('suppliers.*')" wire:navigate>
                        {{ __('Fournisseurs') }}
                    </flux:sidebar.item>
                @endcan

                @can('supplier_orders.view')
                    <flux:sidebar.item icon="truck" :href="route('supplier-orders.index')" :current="request()->routeIs('supplier-orders.*')" wire:navigate>
                        {{ __('Commandes fournisseurs') }}
                    </flux:sidebar.item>
                @endcan

                @can('receptions.view')
                    <flux:sidebar.item icon="inbox-arrow-down" :href="route('receptions.index')" :current="request()->routeIs('receptions.*')" wire:navigate>
                        {{ __('Réceptions') }}
                    </flux:sidebar.item>
                @endcan

                @can('inventory.view')
                    <flux:sidebar.item icon="clipboard-document-list" :href="route('inventory.movements')" :current="request()->routeIs('inventory.*')" wire:navigate>
                        {{ __('Journal d\'inventaire') }}
                    </flux:sidebar.item>
                @endcan

                @canany(['products.view', 'services.view', 'departments.view', 'categories.view', 'colors.view', 'price_lists.view'])
                <flux:sidebar.group :heading="__('Catalogue')" expandable :expanded="request()->routeIs('products.*') || request()->routeIs('catalog.*')">
                    @can('products.view')
                        <flux:sidebar.item icon="cube" :href="route('products.index')" :current="request()->routeIs('products.*')" wire:navigate>
                            {{ __('Produits') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('services.view')
                        <flux:sidebar.item icon="wrench" :href="route('catalog.services')" :current="request()->routeIs('catalog.services')" wire:navigate>
                            {{ __('Services') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('departments.view')
                        <flux:sidebar.item icon="tag" :href="route('catalog.departments')" :current="request()->routeIs('catalog.departments')" wire:navigate>
                            {{ __('Départements') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('categories.view')
                        <flux:sidebar.item icon="squares-2x2" :href="route('catalog.categories')" :current="request()->routeIs('catalog.categories')" wire:navigate>
                            {{ __('Catégories') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('colors.view')
                        <flux:sidebar.item icon="swatch" :href="route('catalog.colors')" :current="request()->routeIs('catalog.colors')" wire:navigate>
                            {{ __('Couleurs') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('price_lists.view')
                        <flux:sidebar.item icon="currency-dollar" :href="route('catalog.price-lists')" :current="request()->routeIs('catalog.price-lists*')" wire:navigate>
                            {{ __('Listes de prix') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
                @endcanany

                @canany(['currencies.view', 'invoices.view', 'payroll.view', 'payment_methods.view', 'taxes.view'])
                <flux:sidebar.group :heading="__('Comptabilité')" expandable :expanded="request()->routeIs('accounting.*')">
                    @can('invoices.view')
                        <flux:sidebar.item icon="document-text" :href="route('accounting.invoices')" :current="request()->routeIs('accounting.invoices*')" wire:navigate>
                            {{ __('Facturation fournisseurs') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('payroll.view')
                        <flux:sidebar.item icon="banknotes" :href="route('accounting.payroll')" :current="request()->routeIs('accounting.payroll')" wire:navigate>
                            {{ __('Paie') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('payment_methods.view')
                        <flux:sidebar.item icon="credit-card" :href="route('accounting.payment-methods')" :current="request()->routeIs('accounting.payment-methods')" wire:navigate>
                            {{ __('Modes de paiement') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('taxes.view')
                        <flux:sidebar.item icon="receipt-percent" :href="route('accounting.taxes')" :current="request()->routeIs('accounting.taxes')" wire:navigate>
                            {{ __('Taxes') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('currencies.view')
                        <flux:sidebar.item icon="currency-dollar" :href="route('accounting.currencies')" :current="request()->routeIs('accounting.currencies')" wire:navigate>
                            {{ __('Devises') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
                @endcanany

                @can('stores.view')
                    <flux:sidebar.item icon="building-storefront" :href="route('stores.index')" :current="request()->routeIs('stores.*')" wire:navigate>
                        {{ __('Magasins') }}
                    </flux:sidebar.item>
                @endcan

                @canany(['users.view', 'positions.manage', 'roles.manage', 'permissions.manage'])
                    <flux:sidebar.group :heading="__('Administration')" expandable :expanded="request()->routeIs('admin.*') || request()->routeIs('users.*')">
                        @can('users.view')
                            <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                                {{ __('Utilisateurs') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('positions.manage')
                            <flux:sidebar.item icon="briefcase" :href="route('admin.positions')" :current="request()->routeIs('admin.positions')" wire:navigate>
                                {{ __('Positions') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('roles.manage')
                            <flux:sidebar.item icon="shield-check" :href="route('admin.roles')" :current="request()->routeIs('admin.roles')" wire:navigate>
                                {{ __('Rôles') }}
                            </flux:sidebar.item>
                        @endcan
                        @can('permissions.manage')
                            <flux:sidebar.item icon="key" :href="route('admin.permissions')" :current="request()->routeIs('admin.permissions')" wire:navigate>
                                {{ __('Permissions') }}
                            </flux:sidebar.item>
                        @endcan
                    </flux:sidebar.group>
                @endcanany

                @canany(['schedules.view', 'schedule_management.view', 'schedule_templates.view', 'holidays.view', 'appointments.view'])
                <flux:sidebar.group :heading="__('Gestion horaire')" expandable>
                    @can('schedules.view')
                        <flux:sidebar.item icon="calendar-days" :href="route('schedules.index')" :current="request()->routeIs('schedules.index')" wire:navigate>
                            {{ __('Mon horaire') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('schedule_management.view')
                        <flux:sidebar.item icon="table-cells" :href="route('schedules.schedule-edit')" :current="request()->routeIs('schedules.schedule-edit')" wire:navigate>
                            {{ __('Gestion des horaires') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('schedule_templates.view')
                        <flux:sidebar.item icon="squares-2x2" :href="route('schedules.templates')" :current="request()->routeIs('schedules.templates')" wire:navigate>
                            {{ __('Modèles d\'horaire') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('holidays.view')
                        <flux:sidebar.item icon="sun" :href="route('schedules.holidays')" :current="request()->routeIs('schedules.holidays')" wire:navigate>
                            {{ __('Jours fériés') }}
                        </flux:sidebar.item>
                    @endcan
                    @can('appointments.view')
                        <flux:sidebar.item icon="clock" :href="route('schedules.appointments')" :current="request()->routeIs('schedules.appointments')" wire:navigate>
                            {{ __('Rendez-vous') }}
                        </flux:sidebar.item>
                    @endcan
                </flux:sidebar.group>
                @endcanany
            </flux:sidebar.nav>

            <flux:spacer />

            <div data-sidebar-user-menu class="in-data-flux-sidebar-collapsed-desktop:hidden">
                <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->fullName()" />
            </div>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="right" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->fullName()"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->fullName() }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Profil') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>

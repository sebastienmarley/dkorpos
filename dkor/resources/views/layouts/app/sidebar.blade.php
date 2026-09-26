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

                <flux:sidebar.item icon="users" :href="route('users.index')" :current="request()->routeIs('users.*')" wire:navigate>
                    {{ __('Utilisateurs') }}
                </flux:sidebar.item>
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

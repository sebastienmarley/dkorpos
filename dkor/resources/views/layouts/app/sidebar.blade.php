@php
    $navigation = app(\App\Support\Navigation::class);
    $navigationSections = $navigation->sections(auth()->user());
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header sticky class="gap-2 border-b border-zinc-200 bg-zinc-50/95 px-4! backdrop-blur-sm sm:px-6! dark:border-zinc-700 dark:bg-zinc-900/95">
            <x-app-logo href="{{ route('home') }}" wire:navigate />

            <flux:spacer />

            <x-navigation.spotlight-trigger />

            <livewire:notifications-bell />

            <flux:dropdown position="bottom" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    data-test="user-menu-button"
                />

                <flux:menu>
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

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Profil') }}
                        </flux:menu.item>
                        @if (auth()->user()->pin !== null)
                            <flux:menu.item as="button" icon="lock-closed" class="w-full cursor-pointer" x-on:click="$dispatch('lock-session')">
                                {{ __('Verrouiller') }}
                            </flux:menu.item>
                        @endif
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

            {{-- Sur téléphone, le menu s'ouvre depuis la barre d'onglets du bas. --}}
            <flux:modal.trigger name="app-navigation" class="max-md:hidden">
                <flux:button variant="ghost" icon="bars-3" :aria-label="__('Menu')" data-test="navigation-menu-button" />
            </flux:modal.trigger>
        </flux:header>

        <x-navigation.drawer :sections="$navigationSections" />

        {{ $slot }}

        <x-navigation.bottom-tabs :tabs="$navigation->bottomTabs($navigationSections)" />

        <x-navigation.spotlight :sections="$navigationSections" />

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @include('partials.session-lock')

        @fluxScripts
    </body>
</html>

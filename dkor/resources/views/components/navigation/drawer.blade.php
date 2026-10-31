@props([
    'sections',
])

{{-- Menu complet de l'application, ouvert à droite depuis l'en-tête ou la barre d'onglets mobile. --}}
<flux:modal name="app-navigation" flyout position="right" class="w-80 max-w-[85vw] min-w-0! p-4!" data-test="navigation-drawer">
    <div class="mb-4 pe-10">
        <x-app-logo href="{{ route('home') }}" wire:navigate />
    </div>

    <flux:navlist>
        @foreach ($sections as $section)
            <flux:navlist.group :heading="$section['heading']" :expandable="$section['heading'] !== null" :expanded="$section['active']">
                @foreach ($section['items'] as $item)
                    @if ($item['navigate'])
                        <flux:navlist.item :icon="$item['icon']" :href="$item['url']" :current="$item['active']" :badge="$item['badge']" badge:color="amber" wire:navigate>
                            {{ $item['label'] }}
                        </flux:navlist.item>
                    @else
                        <flux:navlist.item :icon="$item['icon']" :href="$item['url']" :current="$item['active']" :badge="$item['badge']" badge:color="amber">
                            {{ $item['label'] }}
                        </flux:navlist.item>
                    @endif
                @endforeach
            </flux:navlist.group>
        @endforeach
    </flux:navlist>
</flux:modal>

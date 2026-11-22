{{--
    Barre d'onglets partagée (fiches produit, fournisseur, magasin et usager, classification, pièces).
    À placer dans un x-data qui expose l'onglet actif (ex. x-data="{ tab: @entangle('activeTab') }") : un clic passe
    par switchTab(), qui sauvegarde l'onglet quitté (App\Concerns\SavesTabsOnSwitch).
    Sans rien à sauvegarder (save-on-switch false), le clic change seulement tab.

    Usage :
        <x-tabs :tabs="['identification' => __('Identification'), 'hr' => __('RH')]" />
        <x-tabs :tabs="['catalog' => __('Catalogue')]" :save-on-switch="false" class="mb-4" />
--}}
@props([
    /** @var array<string, string> clé de l'onglet => libellé */
    'tabs' => [],
    'saveOnSwitch' => true,
])

<div role="tablist" {{ $attributes->class('flex gap-1 overflow-x-auto overflow-y-hidden border-b border-zinc-200 [scrollbar-width:none] dark:border-zinc-700') }}>
    @foreach ($tabs as $tabKey => $tabLabel)
        <button
            type="button"
            role="tab"
            wire:key="tab-{{ $tabKey }}"
            @click="{{ $saveOnSwitch ? "\$wire.switchTab('{$tabKey}')" : "tab = '{$tabKey}'" }}"
            :aria-selected="tab === '{{ $tabKey }}'"
            :class="tab === '{{ $tabKey }}'
                ? 'border-zinc-900 bg-zinc-100 font-semibold text-zinc-900 dark:border-white dark:bg-white/10 dark:text-white'
                : 'border-transparent font-medium text-zinc-500 hover:border-zinc-300 hover:bg-zinc-50 hover:text-zinc-800 dark:text-zinc-400 dark:hover:border-zinc-500 dark:hover:bg-white/5 dark:hover:text-zinc-200'"
            class="-mb-px shrink-0 rounded-t-lg border-b-[3px] px-4 py-2.5 text-sm whitespace-nowrap transition-colors"
        >{{ $tabLabel }}</button>
    @endforeach
</div>

{{--
    Ligne de table entièrement cliquable qui mène à une fiche (wire:navigate).
    Sans fiche, action donne plutôt une expression Alpine à exécuter (ex. ouvrir un modal d'édition).
    Sans href ni action (pas d'accès), la ligne reste une ligne ordinaire.

    Un clic sur un lien, un bouton ou un champ de la ligne garde son propre comportement,
    Ctrl/Cmd + clic ouvre la fiche dans un nouvel onglet et un texte sélectionné ne déclenche rien.
    Garder un vrai lien dans la ligne (ex. sur le nom) pour le clavier et les lecteurs d'écran.

    Ajoute une dernière cellule avec un chevron : prévoir une colonne vide <flux:table.column />.

    Usage :
        <x-table.row-link :key="$user->id" :href="$canOpen ? route('users.show', $user) : null">
            <flux:table.cell>…</flux:table.cell>
        </x-table.row-link>

        <x-table.row-link :key="$customer->id" :action="$canEdit ? '$dispatch(\'open-customer-edit\', { id: '.$customer->id.' })' : null">
--}}
@props([
    'href' => null,
    'action' => null,
    'key' => null,
])

@if ($href)
    <flux:table.row
        :key="$key"
        {{ $attributes->class('cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60') }}
        :data-href="$href"
        x-data
        x-on:click="
            if ($event.target.closest('a, button, input, select, textarea, label')) return;
            if (window.getSelection().toString() !== '') return;
            if ($event.metaKey || $event.ctrlKey) { window.open($el.dataset.href, '_blank'); return; }
            Livewire.navigate($el.dataset.href);
        "
        data-row-link
    >
        {{ $slot }}

        <flux:table.cell class="w-0 pe-3">
            <flux:icon name="chevron-right" variant="mini" class="text-zinc-400" />
        </flux:table.cell>
    </flux:table.row>
@elseif ($action)
    <flux:table.row
        :key="$key"
        {{ $attributes->class('cursor-pointer transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-800/60') }}
        x-data
        x-on:click="
            if ($event.target.closest('a, button, input, select, textarea, label')) return;
            if (window.getSelection().toString() !== '') return;
            {{ $action }}
        "
        data-row-link
    >
        {{ $slot }}

        <flux:table.cell class="w-0 pe-3">
            <flux:icon name="chevron-right" variant="mini" class="text-zinc-400" />
        </flux:table.cell>
    </flux:table.row>
@else
    <flux:table.row :key="$key" {{ $attributes }}>
        {{ $slot }}

        <flux:table.cell class="w-0 pe-3" />
    </flux:table.row>
@endif

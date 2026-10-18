@props(['product', 'label' => true])

{{-- Ouvre la modale globale d'étiquette de prix (App\Livewire\LabelQueueForm) pour ce produit. --}}
@can('labels.create')
    <flux:button
        icon="tag"
        x-on:click="$dispatch('queue-label', { productId: {{ $product->id }} })"
        :tooltip="$label ? null : __('Étiquette de prix')"
        {{ $attributes }}
    >
        @if ($label)
            {{ __('Étiquette') }}
        @endif
    </flux:button>
@endcan

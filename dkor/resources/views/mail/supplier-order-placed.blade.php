@php
    $lines = $order->lines->reject(fn ($line) => $line->status->isClosed());
@endphp
<x-mail::message>
# {{ __('Commande :number', ['number' => $order->number]) }}

@if ($order->quote_number)
**{{ __('Quote #') }}** : {{ $order->quote_number }}
@endif

<x-mail::table>
| {{ $order->isProductOrder() ? __('Produit') : __('Description') }} | {{ __('Quantité') }} | {{ __('Coût unitaire') }} | {{ __('Total') }} |
| :-- | --: | --: | --: |
@foreach ($lines as $line)
| {{ $line->label }} | {{ $line->quantity }} | {{ number_format($line->unit_cost, 2) }} $ | {{ number_format($line->total, 2) }} $ |
@endforeach
</x-mail::table>

**{{ __('Total') }}** : {{ number_format($order->total, 2) }} $

@if ($order->isProductOrder())
@if ($order->isCollectShipping())
**{{ __('Transport') }}** : {{ __('collect') }}@if ($order->shippingSupplier) — {{ $order->shippingSupplier->name }}@endif
@else
**{{ __('Transport') }}** : {{ __('prépayé') }}
@endif
@endif

@if ($order->is_drop_ship)
**{{ __('Livrer à (drop ship)') }}** : {{ $order->drop_ship_name }}
{!! nl2br(e($order->dropShipAddressLabel())) !!}

@endif
@if ($order->notes)
**{{ __('Notes') }}** : {{ $order->notes }}
@endif

{{ __('Merci de nous confirmer la réception de cette commande.') }}

{{ config('app.name') }}
</x-mail::message>

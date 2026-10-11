<x-mail::message>
# {{ __('Erreur d\'expédition') }}

{{ __('À la réception :number (bordereau :reference), nous avons constaté les écarts suivants.', ['number' => $reception->number, 'reference' => $reception->reference ?? '—']) }}

@if ($shortLines->isNotEmpty())
## {{ __('Articles manquants — demande de crédit') }}

{{ __('Ces articles ne nous sont pas parvenus. Merci de nous émettre une note de crédit.') }}

<x-mail::table>
| {{ __('Commande') }} | {{ __('Article') }} | {{ __('Manquant') }} | {{ __('Coût unitaire') }} | {{ __('Crédit demandé') }} |
| :-- | :-- | --: | --: | --: |
@foreach ($shortLines as $line)
| {{ $line->orderLine->order->number }} | {{ $line->orderLine->label }} | {{ $line->quantity_short }} | {{ $line->unit_cost->format() }} | {{ $line->unit_cost->times($line->quantity_short)->format() }} |
@endforeach
</x-mail::table>

**{{ __('Total du crédit demandé') }} : {{ \App\Support\Money::sum($shortLines->map(fn ($line) => $line->unit_cost->times($line->quantity_short)))->format() }}**
@endif

@if ($overshipLines->isNotEmpty())
## {{ __('Articles reçus en trop') }}

{{ __('Ces articles ne figuraient pas sur notre commande.') }}

<x-mail::table>
| {{ __('Commande') }} | {{ __('Article') }} | {{ __('Quantité') }} |
| :-- | :-- | --: |
@foreach ($overshipLines as $line)
| {{ $line->orderLine->order->number }} | {{ $line->orderLine->label }} | {{ $line->quantity }} |
@endforeach
</x-mail::table>
@endif

{{ config('app.name') }}
</x-mail::message>

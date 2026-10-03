<x-mail::message>
# {{ __('Demande d\'annulation') }}

{{ __('Nous demandons l\'annulation de la ligne suivante de la commande :number.', ['number' => $line->order->number]) }}

<x-mail::table>
| {{ __('Article') }} | {{ __('Quantité commandée') }} | {{ __('Déjà reçue') }} | {{ __('À annuler') }} |
| :-- | --: | --: | --: |
| {{ $line->label }} | {{ $line->quantity }} | {{ $line->quantity_received }} | {{ $line->quantity_outstanding }} |
</x-mail::table>

@if ($line->cancellation_reason)
{{ __('Raison') }} : {{ $line->cancellation_reason }}
@endif

{{ __('Merci de nous confirmer l\'annulation.') }}

{{ config('app.name') }}
</x-mail::message>

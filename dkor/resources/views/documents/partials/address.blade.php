{{-- Bloc d'adresse d'un magasin ou d'un fournisseur (colonnes address_*). --}}
<div class="party-name">{{ $party->name }}</div>
@if (filled($party->address_street))
    <div>
        {{ trim($party->address_civic.' '.$party->address_street) }}@if (filled($party->address_apartment)), {{ __('app.') }} {{ $party->address_apartment }}@endif
    </div>
    <div>{{ trim($party->address_city.' '.$party->address_province.' '.$party->address_postal_code) }}</div>
@endif
@if (filled($party->phone))
    <div>{{ __('Tél.') }} {{ $party->phone }}</div>
@endif
@if (filled($party->email))
    <div>{{ $party->email }}</div>
@endif

{{-- Bloc d'adresse d'un magasin, d'un fournisseur ou d'un client (colonnes address_*). --}}
<div class="party-name">{{ $name ?? $party->name }}</div>
@if (filled($party->address_street))
    <div>
        {{ trim($party->address_civic.' '.$party->address_street) }}@if (filled($party->address_apartment)), {{ __('app.') }} {{ $party->address_apartment }}@endif
    </div>
    <div>{{ trim($party->address_city.' '.$party->address_province.' '.$party->address_postal_code) }}</div>
@endif
@if (filled($party->phone ?? null))
    <div>{{ __('Tél.') }} {{ $party->phone }}</div>
@endif
@if (filled($party->cellphone ?? null))
    <div>{{ __('Cell.') }} {{ $party->cellphone }}</div>
@endif
@if (filled($party->email ?? null))
    <div>{{ $party->email }}</div>
@endif

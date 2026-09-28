@props([
    'prefix',
    'currentCountry' => 'CA',
])

@php
$provinces = [
    'AB' => 'Alberta',
    'BC' => 'Colombie-Britannique',
    'MB' => 'Manitoba',
    'NB' => 'Nouveau-Brunswick',
    'NL' => 'Terre-Neuve-et-Labrador',
    'NS' => 'Nouvelle-Écosse',
    'NT' => 'Territoires du Nord-Ouest',
    'NU' => 'Nunavut',
    'ON' => 'Ontario',
    'PE' => 'Île-du-Prince-Édouard',
    'QC' => 'Québec',
    'SK' => 'Saskatchewan',
    'YT' => 'Yukon',
];

$states = [
    'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
    'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
    'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho',
    'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas',
    'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland',
    'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi',
    'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada',
    'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York',
    'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma',
    'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina',
    'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah',
    'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia',
    'WI' => 'Wisconsin', 'WY' => 'Wyoming', 'DC' => 'District of Columbia',
];

$isUs = $currentCountry === 'US';
$regions = $isUs ? $states : $provinces;
$regionLabel = $isUs ? __('État') : __('Province');
$postalLabel = $isUs ? __('ZIP Code') : __('Code postal');
$postalPlaceholder = $isUs ? '00000' : 'A1A1A1';
$postalMaxlength = $isUs ? 5 : 6;
@endphp

<div class="space-y-4">
    {{-- No civique + Rue --}}
    <div class="grid gap-4 sm:grid-cols-4">
        <flux:field>
            <flux:label>{{ __('N° civique') }}</flux:label>
            <flux:input wire:model="{{ $prefix }}.civic" type="text" />
            <flux:error name="{{ $prefix }}.civic" />
        </flux:field>

        <flux:field class="sm:col-span-3">
            <flux:label>{{ __('Rue') }}</flux:label>
            <flux:input wire:model="{{ $prefix }}.street" type="text" />
            <flux:error name="{{ $prefix }}.street" />
        </flux:field>
    </div>

    {{-- Appartement --}}
    <flux:field class="max-w-xs">
        <flux:label>{{ __('Appartement') }}</flux:label>
        <flux:input wire:model="{{ $prefix }}.apartment" type="text" />
        <flux:error name="{{ $prefix }}.apartment" />
    </flux:field>

    {{-- Ville + Province/État + Pays --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <flux:field>
            <flux:label>{{ __('Ville') }}</flux:label>
            <flux:input wire:model="{{ $prefix }}.city" type="text" />
            <flux:error name="{{ $prefix }}.city" />
        </flux:field>

        <flux:field>
            <flux:label>{{ $regionLabel }}</flux:label>
            <flux:select wire:model="{{ $prefix }}.province">
                <flux:select.option value="">{{ __('Sélectionner…') }}</flux:select.option>
                @foreach ($regions as $code => $name)
                    <flux:select.option :value="$code">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="{{ $prefix }}.province" />
        </flux:field>

        <flux:field>
            <flux:label>{{ __('Pays') }}</flux:label>
            <flux:select wire:model.live="{{ $prefix }}.country">
                <flux:select.option value="CA">{{ __('Canada') }}</flux:select.option>
                <flux:select.option value="US">{{ __('États-Unis') }}</flux:select.option>
            </flux:select>
            <flux:error name="{{ $prefix }}.country" />
        </flux:field>
    </div>

    {{-- Code postal / ZIP --}}
    <flux:field class="max-w-xs">
        <flux:label>{{ $postalLabel }}</flux:label>
        @if ($isUs)
            {{-- ZIP: digits only, 5 chars --}}
            <div
                x-data
                x-on:input.capture="
                    const el = $event.target;
                    const val = el.value.replace(/\D/g, '').substring(0, 5);
                    if (el.value !== val) el.value = val;
                "
            >
                <flux:input wire:model="{{ $prefix }}.postal_code" type="text" maxlength="5" placeholder="{{ $postalPlaceholder }}" />
            </div>
        @else
            {{-- Code postal canadien: lettres et chiffres en majuscule, 6 chars --}}
            <div
                x-data
                x-on:input.capture="
                    const el = $event.target;
                    const val = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 6);
                    if (el.value !== val) el.value = val;
                "
            >
                <flux:input wire:model="{{ $prefix }}.postal_code" type="text" maxlength="6" placeholder="{{ $postalPlaceholder }}" />
            </div>
        @endif
        <flux:error name="{{ $prefix }}.postal_code" />
    </flux:field>
</div>

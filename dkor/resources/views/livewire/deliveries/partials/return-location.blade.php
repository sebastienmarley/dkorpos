@if ($this->getReturnLocations()->isNotEmpty())
    <flux:select wire:model="returnLocations.{{ $deliveryLine->id }}" size="sm">
        <flux:select.option value="">{{ __('Emplacement au retour (facultatif)…') }}</flux:select.option>
        @foreach ($this->getReturnLocations() as $location)
            <flux:select.option :value="$location->id">{{ $location->label() }}</flux:select.option>
        @endforeach
    </flux:select>
@endif

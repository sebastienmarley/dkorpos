@props(['label' => null, 'name' => null])

<flux:field>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    {{--
        Capture phase intercepts the input event before Livewire's wire:model listener.
        Replaces commas with periods, strips non-numeric characters, and limits to 2 decimal places.
    --}}
    <div
        x-data
        x-on:input.capture="
            const el = $event.target;
            let val = el.value.replace(',', '.');
            val = val.replace(/[^0-9.]/g, '');
            const parts = val.split('.');
            if (parts.length > 2) {
                val = parts[0] + '.' + parts.slice(1).join('');
            }
            if (parts.length === 2 && parts[1].length > 2) {
                val = parts[0] + '.' + parts[1].substring(0, 2);
            }
            if (el.value !== val) el.value = val;
        "
    >
        <flux:input type="text" inputmode="decimal" placeholder="0.00" {{ $attributes }} />
    </div>

    @if ($name)
        <flux:error :name="$name" />
    @endif
</flux:field>

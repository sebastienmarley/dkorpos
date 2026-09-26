@props(['label' => null, 'name' => null])

<flux:field>
    @if ($label)
        <flux:label>{{ $label }}</flux:label>
    @endif

    {{--
        Capture phase intercepts the input event before Livewire's wire:model listener,
        so the formatted value is what Livewire receives on each keystroke.
    --}}
    <div
        x-data
        x-on:input.capture="
            const el = $event.target;
            const raw = el.value.replace(/\D/g, '').substring(0, 10);
            let f = '';
            if (raw.length > 0) {
                f = '(' + raw.substring(0, Math.min(3, raw.length));
                if (raw.length >= 3) f += ')';
                if (raw.length > 3) f += raw.substring(3, Math.min(6, raw.length));
                if (raw.length > 6) f += '-' + raw.substring(6);
            }
            if (el.value !== f) el.value = f;
        "
    >
        <flux:input type="tel" placeholder="(xxx)xxx-xxxx" {{ $attributes }} />
    </div>

    @if ($name)
        <flux:error :name="$name" />
    @endif
</flux:field>

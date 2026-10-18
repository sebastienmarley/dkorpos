<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}

        @can('labels.create')
            <livewire:label-queue-form />
        @endcan
    </flux:main>
</x-layouts::app.sidebar>

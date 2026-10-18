<div wire:poll.60s x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    <flux:button variant="ghost" size="sm" square class="relative" :aria-label="__('Notifications')" x-ref="button" x-on:click="open = ! open">
        <flux:icon.bell variant="outline" class="size-5" />
        @if ($notifications->isNotEmpty())
            <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-500 px-1 text-[10px] font-semibold text-white">
                {{ $notifications->count() > 9 ? '9+' : $notifications->count() }}
            </span>
        @endif
    </flux:button>

    {{-- Téléporté dans <body> pour ne pas être coupé par la barre latérale. --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            x-anchor.bottom-end.offset.8="$refs.button"
            x-on:click.outside="if (! $refs.button.contains($event.target)) open = false"
            class="z-50 w-80 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900"
        >
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-2 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Notifications') }}</flux:heading>
                @if ($notifications->isNotEmpty())
                    <flux:button variant="ghost" size="xs" wire:click="markAllAsRead">{{ __('Tout marquer comme lu') }}</flux:button>
                @endif
            </div>

            @forelse ($notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="open('{{ $notification->id }}')"
                    class="flex w-full items-start gap-3 px-4 py-3 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800"
                >
                    <flux:icon :name="$notification->data['icon'] ?? 'bell'" variant="mini" class="mt-0.5 shrink-0 text-amber-500" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-zinc-900 dark:text-white">{{ $notification->data['title'] ?? '' }}</span>
                        @if (filled($notification->data['body'] ?? null))
                            <span class="block text-sm text-zinc-500">{{ $notification->data['body'] }}</span>
                        @endif
                        <span class="mt-0.5 block text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</span>
                    </span>
                </button>
            @empty
                <flux:text class="px-4 py-6 text-center text-zinc-400">{{ __('Aucune notification.') }}</flux:text>
            @endforelse
        </div>
    </template>
</div>

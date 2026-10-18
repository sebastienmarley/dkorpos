<div class="mx-auto max-w-md p-4">
    @if ($sheet)
        <div class="mb-4">
            <flux:button size="sm" variant="ghost" icon="arrow-left" wire:click="back">{{ __('Mes comptes') }}</flux:button>
            <flux:heading level="1" size="xl" class="mt-2">{{ $sheet->areaLabel() }}</flux:heading>
            <div class="mt-1 flex gap-2">
                <flux:badge size="sm">{{ $sheet->pass->label() }}</flux:badge>
                <flux:badge size="sm" :color="$sheet->state->color()">{{ $sheet->state->label() }}</flux:badge>
            </div>
        </div>

        @if ($sheet->state === \App\Enums\InventoryCountSheetState::Todo)
            <flux:button variant="primary" class="w-full" wire:click="start">{{ __('Commencer le compte') }}</flux:button>
        @elseif ($sheet->state === \App\Enums\InventoryCountSheetState::InProgress)
            <form wire:submit="scan" class="space-y-3">
                <div class="flex gap-2">
                    <div class="w-20">
                        <flux:input wire:model="quantity" type="number" min="1" inputmode="numeric" :label="__('Qté')" />
                    </div>
                    <div class="flex-1">
                        <flux:input wire:model="code" :label="__('Code-barres')" autofocus autocomplete="off" inputmode="none"
                            x-init="$el.focus()" x-on:blur="setTimeout(() => { if (! document.activeElement || document.activeElement === document.body) $el.focus() }, 50)" />
                    </div>
                </div>
                <flux:error name="code" />
                <flux:error name="quantity" />
            </form>

            @if ($lastScan)
                <div class="mt-4 rounded-xl border p-4 {{ $lastScan->isUnknown() ? 'border-amber-400 bg-amber-50 dark:bg-amber-950' : 'border-zinc-200 dark:border-zinc-700' }}">
                    @if ($lastScan->isUnknown())
                        <flux:heading size="lg">{{ __('Code inconnu : :code', ['code' => $lastScan->code]) }}</flux:heading>
                        @if ($canAssociate)
                            <flux:input class="mt-3" wire:model.live.debounce.300ms="productSearch" icon="magnifying-glass" :placeholder="__('Associer à un produit…')" />
                            <div class="mt-2 divide-y divide-zinc-200 dark:divide-zinc-700">
                                @foreach ($productResults as $result)
                                    <button type="button" wire:key="associate-{{ $result->id }}" wire:click="associate({{ $result->id }})" class="w-full px-2 py-2 text-start">
                                        {{ $result->display_name }}
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <flux:text class="mt-1">{{ __('La lecture est mise de côté pour le superviseur. Continuez le compte.') }}</flux:text>
                        @endif
                    @else
                        <flux:text class="text-xs uppercase text-zinc-500">{{ __('Dernière lecture') }}</flux:text>
                        <flux:heading size="xl">+{{ $lastScan->quantity }} · {{ $lastScan->product?->display_name }}</flux:heading>
                    @endif
                </div>
            @endif

            <div class="mt-4 flex gap-2">
                <flux:button class="flex-1" icon="arrow-uturn-left" wire:click="undo">{{ __('Annuler la dernière lecture') }}</flux:button>
                <flux:button class="flex-1" variant="primary" wire:click="finish"
                    wire:confirm="{{ __('Terminer la feuille ? Vous ne pourrez plus la modifier.') }}">{{ __('Terminer') }}</flux:button>
            </div>
        @endif

        <div class="mt-6 divide-y divide-zinc-200 rounded-xl border border-zinc-200 dark:divide-zinc-700 dark:border-zinc-700">
            @forelse ($sheet->lines->sortBy(fn ($line) => $line->product->display_name) as $line)
                <div class="flex items-center justify-between gap-3 p-3" wire:key="sheet-line-{{ $line->id }}">
                    <flux:text class="flex-1">{{ $line->product->display_name }}</flux:text>
                    @if ($sheet->state === \App\Enums\InventoryCountSheetState::InProgress)
                        <div class="w-20">
                            <flux:input size="sm" type="number" min="0" inputmode="numeric" wire:model.blur="quantities.{{ $line->product_id }}" />
                        </div>
                    @else
                        <flux:text class="font-medium">{{ $line->quantity }}</flux:text>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center"><flux:text class="text-zinc-400">{{ __('Aucun produit compté.') }}</flux:text></div>
            @endforelse
        </div>
    @else
        <flux:heading level="1" size="xl" class="mb-4">{{ __('Mes comptes') }}</flux:heading>

        <div class="space-y-3">
            @forelse ($sheets as $mySheet)
                <button type="button" wire:key="my-sheet-{{ $mySheet->id }}" wire:click="select({{ $mySheet->id }})"
                    class="w-full rounded-xl border border-zinc-200 p-4 text-start dark:border-zinc-700">
                    <div class="flex items-center justify-between">
                        <flux:heading size="lg">{{ $mySheet->areaLabel() }}</flux:heading>
                        <flux:badge size="sm" :color="$mySheet->state->color()">{{ $mySheet->state->label() }}</flux:badge>
                    </div>
                    <flux:text class="text-sm text-zinc-500">{{ $mySheet->pass->label() }} · {{ __('Prise du :date', ['date' => $mySheet->inventoryCount->counted_on->format('Y-m-d')]) }}</flux:text>
                </button>
            @empty
                <flux:text class="text-zinc-400">{{ __('Aucune feuille de compte ne vous est attribuée.') }}</flux:text>
            @endforelse
        </div>
    @endif
</div>

<div
    class="p-6"
    x-data="{
        printed: false,
        print(url) {
            const frame = this.$refs.frame;
            this.printed = false;

            frame.onload = () => {
                const done = () => {
                    if (! this.printed) {
                        this.printed = true;
                        this.$wire.askConfirmation();
                    }
                };

                frame.contentWindow.addEventListener('afterprint', done, { once: true });
                frame.contentWindow.focus();
                frame.contentWindow.print();
                done();
            };

            frame.src = url;
        },
    }"
    x-on:print-labels.window="print($event.detail.url)"
>
    <div class="mb-6 flex items-center justify-between">
        <div>
            <flux:heading level="1" size="xl">{{ __('Étiquettes à imprimer') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500">{{ __('Les démonstrateurs dont le prix a changé s\'ajoutent automatiquement à la file.') }}</flux:text>
        </div>

        @can('labels.print')
            <flux:button variant="primary" icon="printer" wire:click="print" :disabled="$requests->isEmpty()">
                {{ trans_choice('Imprimer :count étiquette|Imprimer :count étiquettes', $requests->whereIn('id', $selected)->sum('quantity')) }}
            </flux:button>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:checkbox.group wire:model.live="selected">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-0"><flux:checkbox.all /></flux:table.column>
                    <flux:table.column>{{ __('Modèle') }}</flux:table.column>
                    <flux:table.column>{{ __('Fournisseur') }}</flux:table.column>
                    <flux:table.column>{{ __('Prix') }}</flux:table.column>
                    <flux:table.column>{{ __('Raison') }}</flux:table.column>
                    <flux:table.column>{{ __('Demandée') }}</flux:table.column>
                    <flux:table.column class="w-28">{{ __('Quantité') }}</flux:table.column>
                    <flux:table.column />
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($requests as $request)
                        <flux:table.row :key="$request->id">
                            <flux:table.cell><flux:checkbox :value="$request->id" /></flux:table.cell>
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('products.show', $request->product)" wire:navigate>{{ $request->product->model }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $request->product->supplier->name }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($request->reason === \App\Enums\LabelReason::PriceChange && $request->product->displayed_price)
                                    <span class="text-zinc-400 line-through">{{ $request->product->displayed_price->format() }}</span>
                                    →
                                @endif
                                <span class="font-medium">{{ $request->product->selling_price->format() }}</span>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$request->reason->color()">{{ $request->reason->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">
                                {{ $request->created_at->format('Y-m-d') }}
                                @if ($request->requester)
                                    · {{ $request->requester->name }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @can('labels.print')
                                    <flux:input
                                        type="number" min="1" max="99" size="sm"
                                        value="{{ $request->quantity }}"
                                        wire:change="updateQuantity({{ $request->id }}, $event.target.value)"
                                    />
                                @else
                                    {{ $request->quantity }}
                                @endcan
                            </flux:table.cell>
                            <flux:table.cell class="text-right">
                                @can('labels.print')
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="remove({{ $request->id }})" wire:confirm="{{ __('Retirer cette demande de la file ?') }}" />
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="py-12 text-center">
                                <div class="flex flex-col items-center gap-2">
                                    <flux:icon name="tag" class="h-8 w-8 text-zinc-300" />
                                    <flux:text class="text-zinc-400">{{ __('Aucune étiquette à imprimer.') }}</flux:text>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:checkbox.group>
    </div>

    {{-- Cadre hors écran qui charge les étiquettes et ouvre la boîte d'impression du navigateur. --}}
    <iframe x-ref="frame" wire:ignore title="{{ __('Impression des étiquettes') }}" class="pointer-events-none fixed -left-[10000px] top-0 h-0 w-0 border-0"></iframe>

    <flux:modal wire:model="showConfirmModal" class="w-full max-w-sm" :dismissible="false">
        <flux:heading class="mb-1">{{ __('Impression terminée ?') }}</flux:heading>
        <flux:text class="text-zinc-500">
            {{ trans_choice('Confirmez que l\'étiquette s\'est bien imprimée : elle sera retirée de la file.|Confirmez que les :count étiquettes se sont bien imprimées : elles seront retirées de la file.', $requests->whereIn('id', $printing)->sum('quantity')) }}
        </flux:text>

        <div class="mt-6 flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="cancelPrinted">{{ __('Non, les garder') }}</flux:button>
            <flux:button variant="primary" wire:click="confirmPrinted">{{ __('Oui, tout est imprimé') }}</flux:button>
        </div>
    </flux:modal>
</div>

<div class="p-6">
    <div class="mb-6">
        <flux:link :href="route('accounting.invoices')" wire:navigate class="text-sm">← {{ __('Facturation fournisseurs') }}</flux:link>
        <flux:heading level="1" size="xl" class="mt-1">{{ __('Nouvelle facture') }}</flux:heading>
        <flux:text class="mt-1 text-zinc-500">{{ __('Facture rattachée à un fournisseur, sans lien avec une réception ni une commande.') }}</flux:text>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <flux:field>
                <flux:label>{{ __('Fournisseur') }}</flux:label>
                <flux:select wire:model.live="supplierId" :placeholder="__('Choisir un fournisseur…')">
                    @foreach ($suppliers as $supplier)
                        <flux:select.option :value="$supplier->id">{{ $supplier->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="supplierId" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Numéro de facture') }}</flux:label>
                <flux:input wire:model="invoiceNumber" type="text" required />
                <flux:error name="invoiceNumber" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Date de facturation') }}</flux:label>
                <flux:input wire:model.live="invoiceDate" type="date" required />
                <flux:error name="invoiceDate" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>{{ __('Description') }}</flux:label>
            <flux:input wire:model="description" type="text" />
            <flux:error name="description" />
        </flux:field>

        <div class="grid gap-4 sm:grid-cols-5">
            <flux:field>
                <flux:label>{{ __('Montant avant frais et taxes') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="merchandiseTotal" type="number" min="0" step="0.01" required />
                <flux:error name="merchandiseTotal" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Frais de transport') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="freightFee" type="number" min="0" step="0.01" required />
                <flux:error name="freightFee" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Frais de douanes') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="customsFee" type="number" min="0" step="0.01" required />
                <flux:error name="customsFee" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Taxes') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="taxes" type="number" min="0" step="0.01" required />
                <flux:error name="taxes" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Montant de la facture') }}</flux:label>
                <flux:input wire:model.live.debounce.400ms="invoiceTotal" type="number" min="0" step="0.01" required />
                <flux:error name="invoiceTotal" />
            </flux:field>
        </div>

        @if ($totals)
            <div class="max-w-xl rounded-xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt>{{ __('Montant avant frais et taxes') }}</dt><dd>{{ number_format($totals['merchandise_total'], 2) }} $</dd></div>
                    <div class="flex justify-between"><dt>{{ __('Transport + douanes + taxes') }}</dt><dd>{{ number_format((float) $freightFee + (float) $customsFee + (float) $taxes, 2) }} $</dd></div>
                    <div class="flex justify-between font-medium"><dt>{{ __('Total calculé') }}</dt><dd>{{ number_format($totals['computed_total'], 2) }} $</dd></div>
                    <div class="flex justify-between font-medium"><dt>{{ __('Montant de la facture') }}</dt><dd>{{ number_format((float) $invoiceTotal, 2) }} $</dd></div>
                    <div class="flex items-center justify-between">
                        <dt>{{ __('Écart') }}</dt>
                        <dd>
                            @if (filled($invoiceTotal) && abs($totals['variance']) >= 0.005)
                                <flux:badge color="red" size="sm">{{ number_format($totals['variance'], 2) }} $</flux:badge>
                            @elseif (filled($invoiceTotal))
                                <flux:badge color="green" size="sm">{{ __('Aucun') }}</flux:badge>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    @if ($totals['discount_due_date'])
                        <div class="flex justify-between text-zinc-500">
                            <dt>{{ __('Escompte :percent % si payé avant le :date', ['percent' => $totals['discount_percent'], 'date' => $totals['discount_due_date']->format('Y-m-d')]) }}</dt>
                            <dd>{{ number_format($totals['discount_amount'], 2) }} $</dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button :href="route('accounting.invoices')" wire:navigate variant="ghost">{{ __('Annuler') }}</flux:button>
            <flux:button type="submit" variant="primary" icon="document-check">{{ __('Enregistrer la facture') }}</flux:button>
        </div>
    </form>
</div>

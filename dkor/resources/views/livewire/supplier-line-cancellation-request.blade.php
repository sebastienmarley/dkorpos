<flux:modal wire:model="showModal" class="w-full max-w-md">
    <flux:heading class="mb-1">{{ __('Demander l\'annulation de la ligne') }}</flux:heading>

    @if ($line)
        <flux:text class="text-zinc-500">
            {{ $line->label }} · {{ $line->order->supplier->name }} · {{ $line->order->number }}
            · {{ __(':count restant à recevoir', ['count' => $line->quantity_outstanding]) }}
        </flux:text>
    @endif

    <flux:text class="mt-2 text-zinc-500">
        {{ (\App\Models\SupplierOrder::emailEnabled() ? __('Un courriel est envoyé au fournisseur; ') : __('Aucun courriel n\'est envoyé (courriels désactivés) : avisez le fournisseur; ')).__('la ligne reste « en demande d\'annulation » jusqu\'à sa réponse.') }}
    </flux:text>

    <form wire:submit="submit" class="mt-6 space-y-4">
        <flux:field>
            <flux:label>{{ __('Raison (optionnel)') }}</flux:label>
            <flux:input wire:model="reason" type="text" />
            <flux:error name="reason" />
        </flux:field>

        <div class="flex justify-end gap-3 pt-2">
            <flux:button type="button" variant="ghost" wire:click="$set('showModal', false)">{{ __('Retour') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Envoyer la demande') }}</flux:button>
        </div>
    </form>
</flux:modal>

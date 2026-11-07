@php
    /** @var \App\Models\SupplierOrder $order */
    $lines = $order->lines->reject(fn ($line) => $line->status->isClosed());
    $issuer = $order->billingStore ?? $order->store;
    $orderedOn = $order->sent_at ?? $order->created_at;
    $forPrint ??= false;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Commande :number', ['number' => $order->number]) }}</title>
    @include('documents.partials.styles')
</head>
<body>
    @if ($forPrint)
        <div class="toolbar">
            <button type="button" onclick="window.print()">{{ __('Imprimer') }}</button>
        </div>
    @endif

    <div class="document">
        <table class="header">
            <tr>
                <td class="issuer">
                    @if ($issuer)
                        @include('documents.partials.address', ['party' => $issuer])
                    @else
                        <div class="party-name">{{ config('app.name') }}</div>
                    @endif
                </td>
                <td class="title">
                    <h1>{{ __('BON DE COMMANDE') }}</h1>
                    <table class="meta">
                        <tr><td class="label">{{ __('Numéro') }}</td><td><strong>{{ $order->number }}</strong></td></tr>
                        @if ($orderedOn)
                            <tr><td class="label">{{ __('Date') }}</td><td>{{ $orderedOn->format('Y-m-d') }}</td></tr>
                        @endif
                        @if ($order->quote_number)
                            <tr><td class="label">{{ __('Quote #') }}</td><td>{{ $order->quote_number }}</td></tr>
                        @endif
                        @if ($order->supplier->account_number)
                            <tr><td class="label">{{ __('No de client') }}</td><td>{{ $order->supplier->account_number }}</td></tr>
                        @endif
                        @if ($order->isProductOrder())
                            <tr>
                                <td class="label">{{ __('Transport') }}</td>
                                <td>
                                    @if ($order->isCollectShipping())
                                        {{ __('collect') }}@if ($order->shippingSupplier) — {{ $order->shippingSupplier->name }}@endif
                                    @else
                                        {{ __('prépayé') }}
                                    @endif
                                </td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="parties">
            <tr>
                <td>
                    <h2>{{ __('Fournisseur') }}</h2>
                    @include('documents.partials.address', ['party' => $order->supplier])
                </td>
                <td>
                    <h2>{{ __('Facturer à') }}</h2>
                    @if ($issuer)
                        @include('documents.partials.address', ['party' => $issuer])
                    @endif
                </td>
                <td>
                    <h2>{{ $order->is_drop_ship ? __('Livrer à (drop ship)') : __('Livrer à') }}</h2>
                    @if ($order->is_drop_ship)
                        <div class="party-name">{{ $order->drop_ship_name }}</div>
                        <div style="white-space: pre-line;">{{ $order->dropShipAddressLabel() }}</div>
                    @elseif ($order->store)
                        @include('documents.partials.address', ['party' => $order->store])
                    @endif
                </td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>{{ $order->isProductOrder() ? __('Produit') : __('Description') }}</th>
                    <th class="num">{{ __('Quantité') }}</th>
                    <th class="num">{{ __('Coût unitaire') }}</th>
                    <th class="num">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line->label }}</td>
                        <td class="num">{{ $line->quantity }}</td>
                        <td class="num">{{ $line->unit_cost->format() }}</td>
                        <td class="num">{{ $line->total->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="num">{{ __('Total') }}@if ($order->supplier->currency) ({{ $order->supplier->currency->code }})@endif</td>
                    <td class="num">{{ $order->total->format() }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($order->notes)
            <div class="notes"><strong>{{ __('Notes') }} :</strong> {{ $order->notes }}</div>
        @endif

        <div class="footer">
            {{ __('Merci de nous confirmer la réception de cette commande.') }}
            {{ __('Veuillez indiquer le numéro :number sur vos factures et bordereaux.', ['number' => $order->number]) }}
        </div>
    </div>
</body>
</html>

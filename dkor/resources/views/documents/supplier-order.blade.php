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
    <style>
        @page { size: letter; margin: 0.5in; }
        body { margin: 0; padding: 24px; background: #fff; color: #18181b; font-family: "Helvetica Neue", Arial, sans-serif; font-size: 13px; line-height: 1.45; }
        .document { max-width: 800px; margin: 0 auto; }
        .toolbar { max-width: 800px; margin: 0 auto 16px; text-align: right; }
        .toolbar button { padding: 8px 16px; border: 1px solid #d4d4d8; border-radius: 6px; background: #18181b; color: #fff; font-size: 13px; cursor: pointer; }
        table { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; }
        .issuer { font-size: 12px; color: #52525b; }
        .issuer .party-name { font-size: 18px; font-weight: 700; color: #18181b; }
        .title { text-align: right; }
        .title h1 { margin: 0 0 6px; font-size: 22px; letter-spacing: 0.04em; }
        .meta td { padding: 1px 0 1px 12px; text-align: right; }
        .meta .label { color: #71717a; }
        .parties { margin-top: 24px; }
        .parties td { width: 33.33%; vertical-align: top; padding: 10px 12px; border: 1px solid #e4e4e7; }
        .parties h2 { margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.06em; color: #71717a; }
        .party-name { font-weight: 600; }
        .lines { margin-top: 24px; }
        .lines th { padding: 8px; border-bottom: 2px solid #18181b; font-size: 11px; text-transform: uppercase; letter-spacing: 0.04em; text-align: left; }
        .lines td { padding: 8px; border-bottom: 1px solid #e4e4e7; vertical-align: top; }
        .lines .num { text-align: right; white-space: nowrap; }
        .lines tfoot td { border-bottom: 0; font-weight: 700; font-size: 14px; }
        .notes { margin-top: 20px; padding: 10px 12px; background: #f4f4f5; white-space: pre-line; }
        .footer { margin-top: 28px; font-size: 12px; color: #52525b; }
        @media print {
            body { padding: 0; }
            .toolbar { display: none; }
            .lines tr { break-inside: avoid; }
        }
    </style>
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

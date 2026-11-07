@php
    /** @var \App\Models\CustomerOrder $order */
    $lines = $order->lines->filter(fn ($line) => $line->status->isBillable());
    $feeLines = $order->lines->filter(fn ($line) => $line->cancellation_fee && ! $line->cancellation_fee->isZero());
    $delivery = $order->deliveries
        ->reject(fn ($delivery) => $delivery->status === \App\Enums\DeliveryStatus::Cancelled)
        ->sortByDesc('id')
        ->first();
    $customerName = trim($order->customer->firstname.' '.$order->customer->lastname);
    $forPrint ??= false;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('Commande #:id', ['id' => $order->id]) }}</title>
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
                    @if ($order->store)
                        @include('documents.partials.address', ['party' => $order->store])
                    @else
                        <div class="party-name">{{ config('app.name') }}</div>
                    @endif
                </td>
                <td class="title">
                    <h1>{{ __('COMMANDE') }}</h1>
                    <table class="meta">
                        <tr><td class="label">{{ __('Numéro') }}</td><td><strong>#{{ $order->id }}</strong></td></tr>
                        <tr><td class="label">{{ __('Date') }}</td><td>{{ $order->created_at->format('Y-m-d') }}</td></tr>
                        @if ($order->salespeople->isNotEmpty())
                            <tr><td class="label">{{ __('Vendeur(s)') }}</td><td>{{ $order->salespeople->map(fn ($salesperson) => $salesperson->fullName())->join(', ') }}</td></tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>

        <table class="parties">
            <tr>
                <td>
                    <h2>{{ __('Client') }}</h2>
                    @include('documents.partials.address', ['party' => $order->customer, 'name' => $customerName])
                </td>
                <td>
                    @if ($delivery)
                        <h2>{{ $delivery->method === \App\Enums\DeliveryMethod::Shipping ? __('Expédier à') : __('Livrer à') }}</h2>
                        @include('documents.partials.address', ['party' => $delivery, 'name' => $customerName])
                        @if ($delivery->scheduled_date)
                            <div class="line-detail">{{ __('Prévue le :date', ['date' => $delivery->scheduled_date->format('Y-m-d')]) }}</div>
                        @endif
                    @else
                        <h2>{{ __('Ramassage') }}</h2>
                        @if ($order->store)
                            @include('documents.partials.address', ['party' => $order->store])
                        @endif
                    @endif
                </td>
            </tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('Article') }}</th>
                    <th class="num">{{ __('Quantité') }}</th>
                    <th class="num">{{ __('Prix unitaire') }}</th>
                    <th class="num">{{ __('Total') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>
                            {{ $line->label }}
                            @if ($line->isService() || $line->is_custom)
                                @if (filled($line->description))
                                    <div class="line-detail" style="white-space: pre-line;">{{ $line->description }}</div>
                                @endif
                            @elseif ($line->isPart())
                                @if (filled($line->part->description))
                                    <div class="line-detail">{{ $line->part->description }}</div>
                                @endif
                            @elseif (filled($line->product->description))
                                <div class="line-detail">{{ $line->product->description }}</div>
                            @endif
                            @if ($line->is_no_charge)
                                <div class="line-detail">{{ __('Sans frais') }}</div>
                            @endif
                            @unless ($line->is_taxable)
                                <div class="line-detail">{{ __('Non taxable') }}</div>
                            @endunless
                            @if ($line->note)
                                <div class="line-detail">{{ $line->note }}</div>
                            @endif
                        </td>
                        <td class="num">{{ $line->quantity }}</td>
                        <td class="num">{{ $line->unit_price->format() }}</td>
                        <td class="num">{{ $line->total->format() }}</td>
                    </tr>
                @endforeach
                @foreach ($feeLines as $line)
                    <tr>
                        <td>{{ __('Frais d\'annulation — :item', ['item' => $line->label]) }}</td>
                        <td class="num">1</td>
                        <td class="num">{{ $line->cancellation_fee->format() }}</td>
                        <td class="num">{{ $line->cancellation_fee->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr><td>{{ __('Sous-total') }}</td><td class="num">{{ $order->subtotal->format() }}</td></tr>
            @foreach ($order->taxLines as $taxLine)
                <tr>
                    <td>
                        {{ $taxLine->name }} ({{ rtrim(rtrim(number_format($taxLine->rate, 3, '.', ''), '0'), '.') }} %)
                        @if ($taxLine->registrationNumber())
                            <div class="muted">{{ $taxLine->registrationNumber() }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $taxLine->amount->format() }}</td>
                </tr>
            @endforeach
            <tr class="grand"><td>{{ __('Total') }}</td><td class="num">{{ $order->total->format() }}</td></tr>
            @foreach ($order->payments as $payment)
                <tr>
                    <td class="muted">
                        {{ $payment->created_at->format('Y-m-d') }} · {{ $payment->type === \App\Enums\CustomerPaymentType::Payment ? $payment->paymentMethod?->name : $payment->type->label() }}
                    </td>
                    <td class="num">{{ $payment->amount->negate()->format() }}</td>
                </tr>
            @endforeach
            <tr class="grand">
                <td>{{ $order->balance_due->isNegative() ? __('Crédit du client') : __('Solde à payer') }}</td>
                <td class="num">{{ $order->balance_due->abs()->format() }}</td>
            </tr>
        </table>

        <div class="footer">
            @if ($order->store && $order->store->cancellation_fee_percent > 0)
                <p>{{ __('Des frais d\'annulation de :percent % du prix s\'appliquent à un article déjà commandé.', ['percent' => rtrim(rtrim(number_format($order->store->cancellation_fee_percent, 2, ',', ''), '0'), ',')]) }}</p>
            @endif
            <p>{{ __('Merci de votre confiance!') }}</p>
        </div>
    </div>
</body>
</html>

@props(['product'])

@php
    $price = $product->selling_price;
    $priceText = $price->cents() % 100 === 0 ? number_format(intdiv($price->cents(), 100)).' $' : $price->format();
    $priceSize = match (true) {
        mb_strlen($priceText) <= 6 => '64pt',
        mb_strlen($priceText) <= 8 => '54pt',
        default => '44pt',
    };
@endphp

{{-- Étiquette 4 × 6 po : seule la zone utile 3,5 × 3,5 po du haut est imprimée, le bas est préimprimé. --}}
<section class="label">
    <div class="label-area">
        <div class="label-price" style="font-size: {{ $priceSize }}">{{ $priceText }}</div>

        <div class="label-details">
            <div class="label-supplier">{{ $product->supplier->name }}</div>
            @if (filled($product->collection))
                <div>{{ $product->collection }}</div>
            @endif
            @if ($product->color)
                <div>{{ $product->color->name }}</div>
            @endif
        </div>

        <div class="label-footer">
            <div class="label-model">{{ $product->model }}</div>
            <div class="label-id-row">
                <span class="label-id">{{ $product->id }}</span>
                <span class="label-barcode">{!! \App\Support\Code128::svg((string) $product->id) !!}</span>
            </div>
        </div>
    </div>
</section>

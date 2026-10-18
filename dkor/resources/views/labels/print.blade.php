<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title }}</title>
    <style>
        /* Position de la zone utile sur la feuille 4 × 6 po : à ajuster selon l'imprimante. */
        :root {
            --label-area-top: 0.25in;
            --label-area-left: 0.25in;
        }

        @page {
            size: 4in 6in;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            color: #000;
            background: #fff;
            font-family: "Helvetica Neue", Arial, sans-serif;
        }

        .label {
            position: relative;
            width: 4in;
            height: 6in;
            overflow: hidden;
            break-after: page;
        }

        .label:last-of-type {
            break-after: auto;
        }

        .label-area {
            position: absolute;
            top: var(--label-area-top);
            left: var(--label-area-left);
            width: 3.5in;
            height: 3.5in;
            display: grid;
            grid-template-rows: repeat(3, 1fr);
            padding: 0.08in;
        }

        .label-price {
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            line-height: 1;
            white-space: nowrap;
            letter-spacing: -0.02em;
        }

        .label-details {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.04in;
            font-size: 13pt;
            text-align: center;
        }

        .label-details > div,
        .label-model {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .label-supplier {
            font-weight: 700;
            text-transform: uppercase;
        }

        .label-footer {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            gap: 0.06in;
        }

        .label-model {
            text-align: right;
            font-size: 15pt;
            font-weight: 700;
        }

        .label-id-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.15in;
        }

        .label-id {
            font-family: "Courier New", monospace;
            font-size: 13pt;
            font-weight: 700;
        }

        .label-barcode svg {
            display: block;
            width: 2.1in;
            height: 0.45in;
        }

        /* Aperçu à l'écran : contour de la feuille, zone utile et partie préimprimée. */
        @media screen {
            body {
                background: #e4e4e7;
                padding: 0.5in 0;
            }

            .toolbar {
                display: flex;
                justify-content: center;
                gap: 0.75rem;
                margin-bottom: 0.5in;
                font-size: 14px;
            }

            .toolbar button {
                padding: 0.5rem 1rem;
                border: 0;
                border-radius: 0.5rem;
                background: #18181b;
                color: #fff;
                font: inherit;
                cursor: pointer;
            }

            .label {
                margin: 0 auto 0.5in;
                background: #fff;
                box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
            }

            .label::after {
                content: "{{ __('Partie préimprimée') }}";
                position: absolute;
                inset: calc(var(--label-area-top) + 3.6in) 0 0 0;
                display: flex;
                align-items: center;
                justify-content: center;
                background: repeating-linear-gradient(45deg, #f4f4f5 0 10px, #e4e4e7 10px 20px);
                color: #71717a;
                font-size: 12px;
            }

            .label-area {
                outline: 1px dashed #a1a1aa;
            }
        }

        @media print {
            .toolbar {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">{{ __('Imprimer') }}</button>
        <span>{{ __('Dans la boîte d\'impression : marges « Aucune », échelle 100 %, alimentation manuelle.') }}</span>
    </div>

    @foreach ($products as $product)
        <x-labels.product :product="$product" />
    @endforeach

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>
</html>

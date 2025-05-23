<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>باركود</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: sans-serif;
        }

        .barcode-container {
            width: 100%;
            height: 100%;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            page-break-inside: avoid;
        }

        .barcode-box {
            margin: 0;
            padding: 0;
            text-align: center;
        }

        .barcode-label {
            font-size: 10px;
            margin-top: 4px;
        }
    </style>
</head>
<body>

<div class="barcode-container">
    {{-- المنتج الرئيسي --}}
    @for ($i = 0; $i < ($quantities['main'] ?? 1); $i++)
        <div class="barcode-box">
            <div style="padding-top: 20px">
                {!! str_replace('<?xml version="1.0" standalone="no"?>', '', DNS1D::getBarcodeSVG($product['id'], 'C39', 2.5, 60)) !!}
            </div>
            <div class="barcode-label">{{ $product['name'] }}</div>
        </div>
    @endfor

    {{-- المتغيرات --}}
    @if (!empty($variations))
        @foreach ($variations as $variation)
            @php
                $variationId = is_array($variation) ? $variation['id'] ?? '' : $variation;
                $qty = $quantities[$variationId] ?? 1;
            @endphp

            @for ($j = 0; $j < $qty; $j++)
                <div class="barcode-box">
                    <div style="padding-top: 20px">
                        {!! str_replace('<?xml version="1.0" standalone="no"?>', '', DNS1D::getBarcodeSVG($variationId, 'C39', 2.5, 60)) !!}
                    </div>
                    <div class="barcode-label">{{ $variation['name'] }}</div>
                </div>
            @endfor
        @endforeach
    @endif
</div>

</body>
</html>

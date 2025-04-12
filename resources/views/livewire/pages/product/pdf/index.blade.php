<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طباعة باركود المنتج</title>
    <style>
        body {
            direction: rtl;
            padding: 20px;
        }

        h1, h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        .product, .variation {
            border: 1px solid #333;
            border-radius: 5px;
            padding: 10px 15px;
            margin-bottom: 20px;
        }

        .label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
        }

        .barcode-box {
            margin-top: 10px;
            border-top: 1px dashed #aaa;
            padding-top: 10px;
        }


    </style>
</head>
<body>

<h1>طباعة باركود المنتج</h1>

{{-- المنتج الرئيسي --}}
<div class="product">
    <span class="label">المنتج الرئيسي:</span>
    <p><strong>الاسم:</strong> {{ $product['name'] ?? 'غير معروف' }}</p>
    <p><strong>الكمية المطلوبة:</strong> {{ $quantities['main'] ?? 1 }}</p>
    <div class="barcode-box">
{{--        <img src="data:image/png;base64, {!! base64_encode(\DNS1D::getBarcodePNG($product['id'], 'C39')) !!} ">--}}
{{--        <div>{!! DNS1D::getBarcodeHTML('4445645656', 'C39'); !!}</div>--}}
        {!! str_replace('<?xml version="1.0" standalone="no"?>', '', DNS1D::getBarcodeSVG($product['id'], 'C39' , 3,33)); !!}

    </div>
</div>

{{-- المتغيرات إن وجدت --}}
@if(!empty($variations))
    <h2>المتغيرات:</h2>
    @foreach($variations as $variation)
        @php
            $variationId = $variation;
            $variationQty = $quantities[$variationId] ?? 0;
            $variationName = collect($variation['attributes'] ?? [])
                ->map(fn($attr) => "{$attr['name']}: {$attr['option']}")
                ->join(' - ');
        @endphp

        <div class="variation">
            <span class="label">متغير:</span>
            <p><strong>{{ $variationName ?: 'غير محدد' }}</strong></p>
            <p><strong>الكمية المطلوبة:</strong> {{ $variationQty }}</p>

            {{-- توليد باركود نصي أو كصورة لاحقاً --}}
            <div class="barcode-box">
                <p><strong>الباركود:</strong> {{ $variationId }}</p>
                {!! str_replace('<?xml version="1.0" standalone="no"?>', '', DNS1D::getBarcodeSVG($variationId, 'C39' , 3,33)); !!}
            </div>
        </div>
    @endforeach
@endif

</body>
</html>

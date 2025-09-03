<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>باركود المنتج</title>
    <style>
        /* --- إعدادات الصفحة للطباعة (مهم جداً لـ mPDF) --- */
        @page {
            /* هوامش صغيرة لتوفير مساحة أمان عند الطباعة والقطع */
            margin: 2mm;
        }

        /* --- إعدادات أساسية --- */
        body {
            margin: 0;
            padding: 0;
            /* * استخدام خط dejavusans المدمج في mPDF، فهو يدعم العربية بشكل ممتاز
             * ويضمن عدم ظهور مربعات أو رموز غريبة.
            */
            font-family: 'dejavusans', sans-serif;
            font-size: 8pt; /* حجم خط أساسي مناسب للطباعة */
            /* كل ملصق سيأخذ صفحة كاملة بشكل تلقائي */
        }

        /*
         * --- تصميم ملصق الباركود الواحد ---
         * يمثل هذا العنصر كل ملصق على حدة.
         */
        .barcode-box {
            /* اجعل الملصق يملأ المساحة المتاحة داخل هوامش الصفحة */
            width: 100%;
            height: 100%;

            /* محاذاة كل المحتوى في المنتصف */
            text-align: center;

            /* * استخدام Flexbox لتوزيع العناصر عمودياً بشكل مثالي.
             * mPDF يدعم هذا النوع من التنسيق البسيط بشكل جيد.
            */
            display: flex;
            flex-direction: column;
            justify-content: space-between; /* يوزع المسافة بين رقم المنتج، الباركود، والاسم */
            align-items: center;

            /* أمر مهم جداً: يمنع mPDF من تقسيم الملصق الواحد على صفحتين */
            page-break-inside: avoid;

            box-sizing: border-box;
        }

        /* --- تنسيق رقم المنتج (ID) --- */
        .product-id {
            font-size: 10pt; /* حجم أكبر لسهولة القراءة */
            font-weight: bold;
            margin: 0;
            padding: 0;
        }

        /* --- حاوية صورة الباركود --- */
        .barcode-image img {
            max-width: 100%; /* تأكد من أن الباركود لا يتجاوز عرض الملصق */
            height: auto;
            margin-top: 10px;
        }

        /* --- تنسيق اسم المنتج --- */
        .product-name {
            font-size: 7.5pt; /* حجم أصغر ليكون ثانويًا */
            margin: 0;
            padding: 0;
            /* الخصائص التالية تمنع النص الطويل من إفساد التصميم */
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%; /* يجب أن يكون بنفس عرض الملصق */
        }

    </style>
</head>
<body>

{{--
    سيقوم mPDF تلقائيًا بإنشاء صفحة جديدة لكل عنصر .barcode-box
    بسبب حجم الصفحة الصغير الذي تم تحديده.
--}}
<div class="barcode-container">

    {{-- المنتج الرئيسي --}}
    @for ($i = 0; $i < ($quantities['main'] ?? 1); $i++)
        <div class="barcode-box">
            <p class="product-id" style="margin-bottom: 10px">{{ $product['id'] }}</p>
            <div class="barcode-image">
                {{--
                    تم تعديل أبعاد الباركود لتناسب حجم الملصق الصغير.
                    عرض 1.2 وارتفاع 45 يعطي نتيجة أفضل من 1.5 و 60 للحجم المحدد.
                --}}
                <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($product['id'], 'C39', 1.6, 60) }}" alt="barcode" />
            </div>
            <p class="product-name" style="margin-top: 10px">{{ $product['name'] }}</p>
        </div>
    @endfor

    {{-- المتغيرات --}}
    @if (!empty($variations))
        @foreach ($variations as $variation)
            @php
                // هذا الكود يتعامل مع الحالتين: لو كانت $variation مصفوفة أو مجرد id
                $variationId = is_array($variation) ? ($variation['id'] ?? '') : $variation;
                $variationName = is_array($variation) ? ($variation['name'] ?? '') : $variationId;
                $qty = $quantities[$variationId] ?? 1;
            @endphp

            @for ($j = 0; $j < $qty; $j++)
                <div class="barcode-box">
                    <p class="product-id" style="margin-bottom: 10px">{{ $variationId }}</p>
                    <div class="barcode-image">
                        <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($variationId, 'C39', 1.6, 60) }}" alt="barcode" />
                    </div>
                    <p class="product-name" style="margin-top: 10px">{{ $variationName }}</p>
                </div>
            @endfor
        @endforeach
    @endif

</div>

</body>
</html>

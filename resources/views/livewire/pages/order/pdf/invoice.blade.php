@php
    $settings = app(\App\Settings\GeneralSettings::class);
@endphp

    <!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فاتورة الطلبية رقم {{ $orderId }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            direction: rtl;
            text-align: right;
            color: #333;
            background-color: #fff;
            font-size: 12px;
            line-height: 1.4;
        }

        .invoice-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
        }

        /* Header بسيط */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #ddd;
        }

        .header h1 {
            font-size: 18px;
            color: #333;
            margin-bottom: 5px;
        }

        .invoice-id {
            font-size: 14px;
            color: #666;
        }

        /* الصف العلوي - معلومات الطلب والعميل جنب بعض */
        .top-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-section {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .section-title {
            font-size: 14px;
            color: #674a87;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #ddd;
            font-weight: 700;
        }

        .info-item {
            margin-bottom: 8px;
            display: flex;
            align-items: flex-start;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            min-width: 80px;
            margin-left: 10px;
        }

        .info-value {
            flex: 1;
        }

        .status-badge {
            background: #ba3586;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            display: inline-block;
        }

        /* جدول المنتجات */
        .products-section {
            margin-bottom: 20px;
        }

        .products-section .section-title {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
            margin-bottom: 0;
            border-bottom: none;
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #ddd;
        }

        .products-table th {
            background: #674a87;
            color: white;
            padding: 10px 8px;
            text-align: center;
            font-weight: 600;
            font-size: 12px;
        }

        .products-table td {
            padding: 10px 8px;
            text-align: center;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }

        .product-meta {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
            text-align: right;
        }

        .meta-item {
            margin-bottom: 2px;
        }

        /* الصف السفلي - الشحن والملخص */
        .bottom-row {
            display: flex;
            gap: 20px;
        }

        .summary-section {
            flex: 1;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .final-total {
            font-weight: 700;
            color: #ba3586;
            font-size: 14px;
            border-bottom: none;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 2px solid #ba3586;
        }

        /* الفوتر */
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 11px;
        }

        @media (max-width: 768px) {
            .top-row,
            .bottom-row {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
<div class="invoice-container">
    <!-- الهيدر -->
    <div class="header">
        <h1>فاتورة الطلبية</h1>
        <div class="invoice-id">رقم الطلبية: {{ $orderId }}</div>
    </div>

    <!-- الصف العلوي: معلومات الطلب والعميل -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <!-- معلومات الطلب -->
            <td style="width: 50%; vertical-align: top; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                <div class="section-title" style="font-size: 14px; color: #674a87; margin-bottom: 10px; font-weight: 700;">معلومات الطلب</div>
                <div style="margin-bottom: 8px;"><strong>الرقم:</strong> {{ $order['id'] ?? $orderId }}</div>
                <div style="margin-bottom: 8px;"><strong>التاريخ:</strong> {{ isset($order['date_created']) ? \Carbon\Carbon::parse($order['date_created'])->format('Y-m-d H:i') : '' }}</div>
                <div style="margin-bottom: 8px;"><strong>الحالة:</strong>
                    <span style="background:#ba3586; color:white; padding:3px 8px; border-radius:4px; font-size:11px;">
                    {{ ucfirst($order['status'] ?? 'pending') }}
                </span>
                </div>
                <div><strong>الدفع:</strong> {{ $order['payment_method_title'] ?? 'غير محدد' }}</div>
            </td>

            <!-- معلومات العميل -->
            <td style="width: 50%; vertical-align: top; padding: 10px; background: #f8f9fa; border-radius: 8px;">
                <div class="section-title" style="font-size: 14px; color: #674a87; margin-bottom: 10px; font-weight: 700;">معلومات العميل</div>
                <div style="margin-bottom: 8px;"><strong>الاسم:</strong> {{ $order['billing']['first_name'] ?? '' }} {{ $order['billing']['last_name'] ?? '' }}</div>
                <div style="margin-bottom: 8px;"><strong>الهاتف:</strong> {{ $order['billing']['phone'] ?? '' }}</div>
                <div style="margin-bottom: 8px;"><strong>البريد:</strong> {{ $order['billing']['email'] ?? '' }}</div>
                <div><strong>المدينة:</strong> {{ $order['billing']['city'] ?? '' }}</div>
            </td>
        </tr>
    </table>


    <!-- جدول المنتجات -->
    @if(isset($order['line_items']) && !empty($order['line_items']))
        <div class="products-section">
            <div class="section-title">عناصر الطلبية</div>
            <table class="products-table">
                <thead>
                <tr>
                    <th width="80">الصورة</th>
                    <th>المنتج</th>
                    <th width="70">الكمية</th>
                    <th width="90">السعر</th>
                    <th width="90">الإجمالي</th>
                </tr>
                </thead>
                <tbody>
                @foreach($order['line_items'] as $item)
                    <tr>
                        <td>
                            @if(!empty($item['image']['src']))
                                <img src="{{ $item['image']['src'] }}" style="width: 100px" alt="{{ $item['name'] ?? 'منتج' }}" class="product-image">
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 600;">{{ $item['name'] ?? 'منتج' }}</div>
                            @if(!empty($item['meta_data']))
                                <div class="product-meta">
                                    @foreach ($item['meta_data'] as $meta)
                                        @if (!empty($meta['display_key']) && !empty($meta['display_value']))
                                            <div class="meta-item">
                                                <strong>{{ $meta['display_key'] }}:</strong> {!! $meta['display_value'] !!}
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>{{ $item['quantity'] ?? 0 }}</td>
                        <td>{{ number_format((float)($item['price'] ?? 0), 2) }} {{ $order['currency'] ?? 'USD' }}</td>
                        <td>{{ number_format((float)($item['total'] ?? 0), 2) }} {{ $order['currency'] ?? 'USD' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <!-- الصف السفلي: الشحن والملخص -->
    <div class="bottom-row">
        <!-- معلومات الشحن -->
{{--        @if(isset($order['shipping_lines']) && !empty($order['shipping_lines']))--}}
{{--            <div class="summary-section">--}}
{{--                <div class="section-title">معلومات الشحن</div>--}}
{{--                @foreach($order['shipping_lines'] as $shipping)--}}
{{--                    <div class="info-item">--}}
{{--                        <span class="info-label">الطريقة:</span>--}}
{{--                        <span class="info-value">{{ $shipping['method_title'] ?? 'غير محدد' }}</span>--}}
{{--                    </div>--}}
{{--                    <div class="info-item">--}}
{{--                        <span class="info-label">التكلفة:</span>--}}
{{--                        <span class="info-value">{{ number_format((float)($shipping['total'] ?? 0), 2) }} {{ $order['currency'] ?? 'USD' }}</span>--}}
{{--                    </div>--}}
{{--                @endforeach--}}
{{--            </div>--}}
{{--        @endif--}}

        <!-- ملخص الحساب -->
        <div class="summary-section">
            <div class="section-title">ملخص الحساب</div>
            <div class="total-row">
                <span>المجموع:</span>
                <span>{{ number_format((float)$totalAmount, 2) }} {{ $order['currency'] ?? 'USD' }}</span>
            </div>
            @if(isset($order['discount_total']) && (float)$order['discount_total'] > 0)
                <div class="total-row">
                    <span>الخصم:</span>
                    <span>- {{ number_format((float)$order['discount_total'], 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                </div>
            @endif
            @if(isset($order['shipping_total']) && (float)$order['shipping_total'] > 0)
                <div class="total-row">
                    <span>الشحن:</span>
                    <span>{{ number_format((float)$order['shipping_total'], 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                </div>
            @endif
            @if(isset($order['total_tax']) && (float)$order['total_tax'] > 0)
                <div class="total-row">
                    <span>الضريبة:</span>
                    <span>{{ number_format((float)$order['total_tax'], 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                </div>
            @endif
            <div class="total-row final-total">
                <span>الإجمالي النهائي:</span>
                <span>{{ number_format((float)$totalAmountAfterDiscount, 2) }} {{ $order['currency'] ?? 'USD' }}</span>
            </div>
        </div>
    </div>

    <!-- الفوتر -->
    <div class="footer">
        <p>تم إنشاء الفاتورة في {{ \Carbon\Carbon::now()->format('Y-m-d H:i') }}</p>
    </div>
</div>
</body>
</html>

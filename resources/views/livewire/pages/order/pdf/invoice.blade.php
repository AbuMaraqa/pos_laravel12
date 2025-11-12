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
            font-size: 13px;
        }

        .invoice-container {
            width: 100%;
            background-color: white;
            border: none;
            box-shadow: none;
            border-radius: 0;
            overflow: hidden;
        }

        .header {
            background-color: rgb(103,74,135);
            color: white;
            padding: 15px;
            text-align: center;
            border: none;
            position: relative;
        }

        .header-logo {
            height: 70px;
            max-width: 200px;
            object-fit: contain;
            margin-bottom: 12px;
        }

        .header h1 {
            font-size: 20px;
            margin-bottom: 5px;
            border: none;
        }

        .invoice-id {
            font-size: 14px;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 3px 10px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 5px;
            border: none;
        }

        .content {
            padding: 8px;
            border: none;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
            border: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            border: none;
        }

        .status-badge {
            padding: 2px 6px;
            border-radius: 8px;
            color: white;
            font-size: 10px;
            display: inline-block;
            border: none;
        }

        .status-pending { background-color: rgb(103,74,135); }
        .status-processing { background-color: rgb(186,53,134); }
        .status-completed { background-color: #28a745; }
        .status-cancelled { background-color: #dc3545; }
        .status-refunded { background-color: #6c757d; }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 11px;
            border: none;
        }

        table th {
            background-color: rgb(103,74,135);
            color: white;
            padding: 6px 4px;
            text-align: center;
            font-weight: 600;
            border: none;
        }

        table td {
            padding: 6px 4px;
            text-align: center;
            border-bottom: 1px solid #eee;
            border: none;
            vertical-align: middle; /* للتأكد من المحاذاة العمودية */
        }

        .total-section {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 0;
            margin-top: 8px;
            border: none;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 12px;
            border: none;
        }

        .final-total {
            color: rgb(186,53,134);
            font-size: 14px;
            font-weight: 700;
            border-top: 1px solid rgb(186,53,134);
            padding-top: 6px;
            margin-top: 6px;
            border: none;
        }

        .footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #eee;
            color: #777;
            font-size: 10px;
            border: none;
        }

        /* تصميم احترافي للمعلومات بجانب بعض */
        .info-section {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
            border: none;
        }

        .info-box {
            flex: 1;
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 0;
            border-right: 2px solid rgb(103,74,135);
            border: none;
        }

        .info-box h3 {
            color: rgb(103,74,135);
            font-size: 13px;
            margin-bottom: 6px;
            font-weight: 700;
            border: none;
        }

        .products-section {
            margin-bottom: 10px;
            border: none;
        }

        .products-section h3 {
            color: rgb(103,74,135);
            font-size: 13px;
            margin-bottom: 6px;
            font-weight: 700;
            background-color: #f8f9fa;
            padding: 6px;
            border-radius: 0;
            border-right: 2px solid rgb(103,74,135);
            border: none;
        }

        .columns {
            display: flex;
            gap: 15px;
            border: none;
        }

        .column {
            flex: 1;
            border: none;
        }

        @media (max-width: 600px) {
            .info-section {
                flex-direction: column;
                gap: 8px;
                border: none;
            }

            .columns {
                flex-direction: column;
                gap: 8px;
                border: none;
            }

            .header-logo {
                height: 50px;
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
<div class="invoice-container">
    @if($settings->getLogoUrl())
        <div style="text-align: center;">
            <img src="{{ $settings->getLogoUrl() }}" alt="شعار الشركة" class="header-logo">
        </div>
    @endif
    <div class="header">
        <h1>فاتورة الطلبية</h1>
        <div class="invoice-id">رقم الطلبية: {{ $orderId }}</div>
    </div>

    <div class="content">
        <div class="info-section">
            <div class="info-box">
                <h3>معلومات الطلبية</h3>
                <div class="info-row">
                    <span class="info-label">رقم:</span>
                    <span>{{ $order['id'] ?? $orderId }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">التاريخ:</span>
                    <span>{{ isset($order['date_created']) ? \Carbon\Carbon::parse($order['date_created'])->format('Y-m-d H:i') : '' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">الحالة:</span>
                    <span>
                            <span class="status-badge status-{{ $order['status'] ?? 'pending' }}">
                                {{ ucfirst($order['status'] ?? 'pending') }}
                            </span>
                        </span>
                </div>
                <div class="info-row">
                    <span class="info-label">الدفع:</span>
                    <span>{{ $order['payment_method_title'] ?? 'غير محدد' }}</span>
                </div>
            </div>

            @if(isset($order['billing']) && !empty($order['billing']))
                <div class="info-box">
                    <h3>معلومات العميل</h3>
                    <div class="info-row">
                        <span class="info-label">الاسم:</span>
                        <span>{{ $order['billing']['first_name'] ?? '' }} {{ $order['billing']['last_name'] ?? '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">البريد:</span>
                        <span>{{ $order['billing']['email'] ?? '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span>{{ $order['billing']['phone'] ?? '' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">المدينة:</span>
                        <span>{{ $order['billing']['city'] ?? '' }}</span>
                    </div>
                </div>
            @endif
        </div>

        <div class="columns">
            <div class="column">
                @if(isset($order['line_items']) && !empty($order['line_items']))
                    <div class="products-section">
                        <h3>عناصر الطلبية</h3>
                        <table>
                            <thead>
                            <tr>
                                <th></th>
                                <th>المنتج</th>
                                <th>الكمية</th>
                                <th>السعر</th>
                                <th>الإجمالي</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($order['line_items'] as $item)
                                <tr>
                                    {{-- ✅  بداية التعديل --}}
                                    <td>
                                        <img style="width: 50px; height: 50px;" src="{{ $item['image']['src'] ?? '' }}" alt="">
                                    </td>
                                    <td>
                                        {{ $item['name'] ?? 'منتج' }}

                                        {{-- إضافة خصائص المتغير (مثل اللون والمقاس) --}}
                                        <div style="font-size: 10px; color: #555; margin-top: 4px;">
                                            @foreach ($item['meta_data'] as $meta)
                                                @if (!empty($meta['display_key']) && !empty($meta['display_value']))
                                                    <span style="display: block; margin-bottom: 2px;">
                                                        {{ $meta['display_key'] }}: {!! $meta['display_value'] !!}
                                                    </span>
                                                @endif
                                            @endforeach
                                        </div>
                                    </td>
                                    {{-- نهاية التعديل --}}

                                    <td>{{ $item['quantity'] ?? 0 }}</td>
                                    <td>{{ number_format((float)($item['price'] ?? 0), 2) }}</td>
                                    <td>{{ number_format((float)($item['total'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="column">
                @if(isset($order['shipping_lines']) && !empty($order['shipping_lines']))
                    <div class="info-box">
                        <h3>معلومات الشحن</h3>
                        @foreach($order['shipping_lines'] as $shipping)
                            <div class="info-row">
                                <span class="info-label">الطريقة:</span>
                                <span>{{ $shipping['method_title'] ?? 'غير محدد' }}</span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">التكلفة:</span>
                                <span>{{ number_format((float)($shipping['total'] ?? 0), 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="total-section">
                    <h3 style="color: rgb(103,74,135); font-size: 13px; margin-bottom: 6px; font-weight: 700;">ملخص الحساب</h3>
                    <div class="total-row">
                        <span class="info-label">المجموع:</span>
                        <span>{{ number_format((float)$totalAmount, 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                    </div>
                    @if(isset($order['discount_total']) && (float)$order['discount_total'] > 0)
                        <div class="total-row">
                            <span class="info-label">الخصم:</span>
                            <span>- {{ number_format((float)$order['discount_total'], 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                        </div>
                    @endif
                    @if(isset($order['shipping_total']) && (float)$order['shipping_total'] > 0)
                        <div class="total-row">
                            <span class="info-label">الشحن:</span>
                            <span>{{ number_format((float)$order['shipping_total'], 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                        </div>
                    @endif
                    @if(isset($order['total_tax']) && (float)$order['total_tax'] > 0)
                        <div class="total-row">
                            <span class="info-label">الضريبة:</span>
                            <span>{{ number_format((float)$order['total_tax'], 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                        </div>
                    @endif
                    <div class="total-row final-total">
                        <span class="info-label">الإجمالي:</span>
                        <span>{{ number_format((float)$totalAmountAfterDiscount, 2) }} {{ $order['currency'] ?? 'USD' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>تم إنشاء الفاتورة في {{ \Carbon\Carbon::now()->format('Y-m-d H:i') }}</p>
    </div>
</div>
</body>
</html>

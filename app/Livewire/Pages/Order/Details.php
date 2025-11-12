<?php

namespace App\Livewire\Pages\Order;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use PDF; // أضف هذا السطر

class Details extends Component
{
    public $order = [];
    public $search = '';
    public $products = [];
    public $quantities = [];

    public $prices = [];
    public $orderId;

    public $shippingZoneMethod;

    public $totalAmount = 0;

    public $totalAmountAfterDiscount = 0;

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount($order): void
    {
        $this->orderId = $order;

        $this->loadOrderDetails($order);

        foreach ($this->order['line_items'] as $item) {
            $this->quantities[$item['product_id']] = $item['quantity'];
            $this->prices[$item['product_id']] = $item['price']; // ⬅️ أضف هذا السطر
        }
    }

    // الدالة الجديدة لطباعة الطلبية
    public function printOrder()
    {
        // تأكد من تحميل بيانات الطلبية الحالية
        $this->loadOrderDetails($this->orderId);

        $pdf = Pdf::loadView('livewire.pages.order.pdf.invoice', [
            'order' => $this->order,
            'orderId' => $this->orderId,
            'totalAmount' => $this->totalAmount,
            'totalAmountAfterDiscount' => $this->totalAmountAfterDiscount,
        ], [], [
            'format' => 'A4', // يمكنك تغيير الحجم حسب الحاجة
            'orientation' => 'P' // Portrait
        ]);

        return response()->streamDownload(function () use ($pdf) {
            $pdf->stream();
        }, 'order-' . $this->orderId . '.pdf');
    }

    public function addProductToOrder(int $productId): array
    {
        // احضار تفاصيل الطلب الحالي
        $order = $this->wooService->getOrdersById($this->orderId);

        if (!$order) {
            throw new \Exception("الطلب غير موجود");
        }

        $lineItems = [];

        $lineItems[] = [
            'product_id' => $productId,
            'quantity' => 1
        ];

        $response = $this->wooService->put('orders/' . $this->orderId, [
            'line_items' => $lineItems
        ]);

        $this->loadOrderDetails($this->orderId);

        return $response;
    }

    public function loadProducts(array $query = []): void
    {
        if (!empty($this->search)) {
            $query['search'] = $this->search;
        }

        $query['per_page'] = 100;

        $asd = $this->products = $this->wooService->getAllVariations($query);

//        dd($this->products = $this->wooService->getProducts($query));
    }

    public function changeQty($productId)
    {
        $newQty = $this->quantities[$productId] ?? null;

        if (is_null($newQty)) {
            return;
        }

        $this->updateProductQuantity($productId, $newQty);
    }

    public function changePrice($productId)
    {
        $newPrice = $this->prices[$productId] ?? null; // ⬅️ تصحيح اسم المتغير

        if (is_null($newPrice)) {
            return;
        }

        // استدعاء الدالة التي ستقوم بالتحديث الفعلي
        $this->updateProductPrice($productId, $newPrice); // ⬅️ أضف هذا السطر
    }

    public function updateProductQuantity($productId, $newQty)
    {
        $order = $this->wooService->get('orders/' . $this->orderId);

        $lineItemToUpdate = collect($order['line_items'])->firstWhere('product_id', $productId);

        if (!$lineItemToUpdate) {
            throw new \Exception("Product not found in order.");
        }

        $payload = [
            'line_items' => [
                [
                    'id' => $lineItemToUpdate['id'],
                    'quantity' => $newQty,
                    'subtotal' => strval(($lineItemToUpdate['price']) * $newQty),
                    'total' => strval(($lineItemToUpdate['price']) * $newQty),
                ]
            ]
        ];


        $response = $this->wooService->put('orders/' . $this->orderId, $payload);

        $this->loadOrderDetails($this->orderId);

        return $response;
    }

    public function updateProductPrice($productId, $newPrice)
    {
        // 1. التحقق من السعر
        if (is_null($newPrice) || !is_numeric($newPrice) || $newPrice < 0) {
            Toaster::error('السعر المدخل غير صالح');
            $this->loadOrderDetails($this->orderId); // لإعادة السعر الأصلي في الحقل
            return;
        }

        // 2. إيجاد المنتج في الطلب (نستخدم بيانات الطلب المحملة)
        $lineItemToUpdate = collect($this->order['line_items'])->firstWhere('product_id', $productId);

        if (!$lineItemToUpdate) {
            Toaster::error('المنتج غير موجود في الطلب.');
            return;
        }

        // 3. جلب الكمية الحالية لحساب الإجمالي
        $quantity = (int) $lineItemToUpdate['quantity'];
        $finalPrice = (float) $newPrice;

        // 4. حساب الإجمالي الجديد بناءً على السعر الجديد والكمية الحالية
        $newSubtotal = $finalPrice * $quantity;
        $newTotal = $finalPrice * $quantity;

        // 5. تجهيز البيانات للتحديث (Payload)
        // ملاحظة: ووكوميرس تتوقع تحديث الـ total والـ subtotal لتغيير السعر
        $payload = [
            'line_items' => [
                [
                    'id' => $lineItemToUpdate['id'],
                    'quantity' => $quantity, // الحفاظ على الكمية كما هي
                    'subtotal' => strval($newSubtotal), // السعر الإجمالي الفرعي الجديد
                    'total' => strval($newTotal), // السعر الإجمالي الجديد
                ]
            ]
        ];

        // 6. إرسال الطلب
        try {
            $response = $this->wooService->put('orders/' . $this->orderId, $payload);

            // 7. إعادة تحميل بيانات الطلب (لإظهار الإجمالي المحدث)
            $this->loadOrderDetails($this->orderId);
            Toaster::success('تم تحديث السعر بنجاح');

            // 8. التأكد من أن السعر في المتغير يطابق ما تم إرساله
            $this->prices[$productId] = $finalPrice;

            return $response;

        } catch (\Exception $e) {
            Toaster::error('خطأ أثناء تحديث السعر: ' . $e->getMessage());
            $this->loadOrderDetails($this->orderId); // إعادة تحميل لإلغاء التغيير
        }
    }

    public function loadOrderDetails($orderId): void
    {
        $this->order = $this->wooService->getOrdersById($orderId);

        $this->totalAmount = collect($this->order['line_items'] ?? [])
            ->sum(function ($item) {
                return $item['total'];
            });

        $this->totalAmountAfterDiscount = ($this->totalAmount - $this->order['discount_total']) + $this->order['shipping_total'];
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        $this->wooService->updateOrderStatus($orderId, $status);
        Toaster::success('Order status updated successfully');
    }

    #[Computed()]
    public function shippingMethods()
    {
        return $this->wooService->shippingMethods();
    }

    #[Computed()]
    public function shippingZones()
    {
        return $this->wooService->shippingZones();
    }

    #[Computed()]
    public function shippingZoneMethods($zoneId)
    {
        return $this->wooService->shippingZoneMethods($zoneId);
    }

    public function updateOrder($methodId, $zoneId)
    {
        // 1. تحميل بيانات الطلب
        $order = $this->wooService->getOrdersById($this->orderId);

        if (empty($order['shipping_lines'][0])) {
            Toaster::error('No shipping line found in this order');
            return;
        }

        $shippingLine = $order['shipping_lines'][0];

        // 2. جلب وسيلة الشحن من منطقة الشحن
        $methods = $this->wooService->shippingZoneMethods($zoneId);
        $method = collect($methods)->firstWhere('id', $methodId);

        if (!$method) {
            Toaster::error('Shipping method not found');
            return;
        }

        // 3. إرسال الطلب بالتعديل
        $payload = [
            'shipping_lines' => [
                [
                    'id' => $shippingLine['id'],
                    'method_id' => $method['id'],
                    'method_title' => $method['title'],
                    'total' => $method['settings']['cost']['value'] ?? '0.00',
                ]
            ]
        ];

        $response = $this->wooService->updateOrder($this->orderId, $payload);

        $this->loadOrderDetails($this->orderId);

        Toaster::success('Shipping method updated successfully');

        return $response;
    }

    public function render()
    {
        return view('livewire.pages.order.details');
    }
}

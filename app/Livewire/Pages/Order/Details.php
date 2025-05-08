<?php

namespace App\Livewire\Pages\Order;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

class Details extends Component
{
    public $order = [];
    public $search = '';
    public $products = [];
    public $quantities = [];
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
        }
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

        $this->products = $this->wooService->getProducts($query);
    }

    public function changeQty($productId)
    {
        $newQty = $this->quantities[$productId] ?? null;

        if (is_null($newQty)) {
            return;
        }

        $this->updateProductQuantity($productId, $newQty);
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

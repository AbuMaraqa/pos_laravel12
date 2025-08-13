<?php

namespace App\Livewire\Pages\Pos;

use App\Enums\InventoryType;
use App\Models\Inventory;
use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Livewire;

class Index extends Component
{
    // These properties will be used to initially populate IndexedDB
    // but the actual UI interaction will be handled by JavaScript
    public string $search = '';
    public array $categories = [];
    public array $products = [];
    public array $variations = [];
    public array $productArray = [];

    public ?int $selectedCategory = 0;

    public array $cart = [];

    protected $wooService;

    public function boot(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
        $this->categories = $this->wooService->getCategories(['parent' => 0]);
        $this->products = $this->wooService->getProducts(
            [
                'per_page' => 100,
                'page' => 1,
            ]
        );
    }

    public function selectCategory(?int $id = null)
    {
        $this->selectedCategory = $id;

        $params = [];

        if ($id !== null) {
            $params['category'] = $id;
        }

        $this->products = $this->wooService->getProducts($params);
    }

    public function syncProductsToIndexedDB()
    {
        // Get fresh products from the API
        $products = $this->wooService->getProducts(['per_page' => 100]);
        return $products;
    }

    public function syncCategoriesToIndexedDB()
    {
        // Get fresh categories from the API
        $categories = $this->wooService->getCategories();
        return $categories;
    }

    public function updatedSearch()
    {
        $this->products = $this->wooService->getProducts(['per_page' => 100, 'search' => $this->search]);
    }

    public function openVariationsModal($id, string $type)
    {
        if ($type == 'variable') {
            $this->variations = $this->wooService->getProductVariations($id);
            $this->modal('variations-modal')->show();
        }
    }

    public function addProduct($productID, $productName, $productPrice)
    {
        $this->productArray[] = [
            'id' => $productID,
            'name' => $productName,
            'price' => $productPrice,
        ];
    }

    #[On('fetch-products-from-api')]
    public function fetchProductsFromAPI(int $perPage = 100, array $extraQuery = []): void
    {
        // نبلّغ الواجهة ببدء المزامنة (عدد الصفحات سيُحدّث لاحقًا إن لم نعرفه)
        $this->dispatch('sync-started', pages: 0);

        if (method_exists($this->wooService, 'getProductsPage')) {
            // ✅ المسار المفضّل: الخدمة تُرجع total_pages من هيدر WooCommerce
            $first = $this->wooService->getProductsPage(1, $perPage, $extraQuery);
            $totalPages = max(1, (int)($first['total_pages'] ?? 1));

            // أرسل الدفعة الأولى
            $this->dispatch('store-products-batch', products: $first['data'], i: 1, total: $totalPages);
            $this->dispatch('sync-progress', page: 1, pages: $totalPages, message: "جلب الصفحة 1 من {$totalPages}");

            // بقية الصفحات
            for ($page = 2; $page <= $totalPages; $page++) {
                $res = $this->wooService->getProductsPage($page, $perPage, $extraQuery);
                $this->dispatch('store-products-batch', products: $res['data'], i: $page, total: $totalPages);
                $this->dispatch('sync-progress', page: $page, pages: $totalPages, message: "جلب الصفحة {$page} من {$totalPages}");
            }
        } else {
            // 🔁 fallback: لف لحد ما الصفحة ترجع أقل من per_page
            $page = 1;
            do {
                $query = array_merge($extraQuery, ['per_page' => $perPage, 'page' => $page]);
                $data  = $this->wooService->getProducts($query);

                // قد تكون الخدمة تُرجع ['data'=>[]] أو مصفوفة مباشرة — طبعها إلى items
                $items = is_array($data) && array_key_exists('data', $data) ? ($data['data'] ?? []) : (is_array($data) ? $data : []);
                if (empty($items)) {
                    break;
                }

                // أرسل الدفعة الحالية
                $this->dispatch('store-products-batch', products: $items, i: $page, total: 0);
                $this->dispatch('sync-progress', page: $page, pages: 0, message: "جلب الصفحة {$page}");

                $page++;
                // نتوقف عندما تقل العناصر عن perPage — يعني آخر صفحة
            } while (count($items) === $perPage);
        }

        $this->dispatch('sync-completed', message: 'اكتملت مزامنة المنتجات بنجاح');
    }

    #[On('fetch-categories-from-api')]
    public function fetchCategoriesFromAPI()
    {
        $categories = $this->wooService->getCategories(['parent' => 0]);
        $this->dispatch('store-categories', categories: $categories);
    }

    #[On('fetch-all-variations')]
    public function fetchAllVariations()
    {
        $page = 1;
        do {
            $response = $this->wooService->getVariableProductsPaginated($page);
            $products = $response['data'] ?? $response;
            $productId = $id ?? $this->productId ?? null;

            foreach ($products as $product) {
                $variations = $this->wooService->getVariationsByProductId($product['id']);

                // أضف معرف المنتج لكل متغير
                foreach ($variations as &$v) {
                    $v['product_id'] = $product['id'];
                }


                // إرسال إلى JavaScript لتخزينهم
                $this->dispatch('store-variations', [
                    'product_id' => $productId,
                    'variations' => $variations,
                ]);
            }

            $page++;
            $hasMore = isset($response['total_pages']) && $page <= $response['total_pages'];
        } while ($hasMore);
    }

    #[On('fetch-customers-from-api')]
    public function fetchCustomersFromAPI()
    {
        $customers = $this->wooService->getCustomers();
        $this->dispatch('store-customers', customers: $customers);
    }


    #[On('add-simple-to-cart')]
    public function addSimpleToCart($product)
    {
        $productId = $product['id'] ?? null;

        if (!$productId) return;

        // فرضًا تضيف إلى this->cart[]
        $this->cart[] = [
            'id' => $productId,
            'name' => $product['name'] ?? '',
            'price' => $product['price'] ?? 0,
            'qty' => 1,
        ];
    }

    #[On('submit-order')]
    public function submitOrder($order)
    {
        $orderData = $order ?? [];

        try {
            // ✅ إذا كان هناك customer_id نضيف بيانات billing
            if (!empty($orderData['customer_id'])) {
                $customer = $this->wooService->getUserById($orderData['customer_id']);

                $orderData['billing'] = [
                    'first_name' => $customer['first_name'] ?? '',
                    'last_name'  => $customer['last_name'] ?? '',
                    'email'      => $customer['email'] ?? '',
                    'phone'      => $customer['billing']['phone'] ?? '',
                    'address_1'  => $customer['billing']['address_1'] ?? '',
                    'city'       => $customer['billing']['city'] ?? '',
                    'country'    => $customer['billing']['country'] ?? 'PS',
                ];
            }

            // إرسال الطلب بعد دمج بيانات العميل
            $order = $this->wooService->createOrder($orderData);

            foreach($orderData['line_items'] as $item) {
                Inventory::create([
                    'store_id' => 1,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'type' => InventoryType::OUTPUT,
                    'user_id' => auth()->user()->id,
                ]);
            }

            $this->dispatch('order-success');
        } catch (\Exception $e) {
            logger()->error('Order creation failed', ['error' => $e->getMessage()]);
            $this->dispatch('order-failed');
        }
    }

    #[On('fetch-shipping-methods-from-api')]
    public function fetchShippingMethods()
    {
        $methods = $this->wooService->getShippingMethods();
        $this->dispatch('store-shipping-methods', methods: $methods);
    }

    public function shippingMethods()
    {
        return $this->wooService->shippingMethods();
    }

    public function shippingZones()
    {
        return $this->wooService->shippingZones();
    }

    public function shippingZoneMethods($zoneId)
    {
        return $this->wooService->shippingZoneMethods($zoneId);
    }

    #[On('fetch-shipping-zones-and-methods')]
    public function fetchShippingZonesAndMethods()
    {
        $zones = $this->wooService->shippingZones();

        $methods = [];

        foreach ($zones as $zone) {
            $zoneMethods = $this->wooService->shippingZoneMethods($zone['id']);

            foreach ($zoneMethods as $method) {
                $methods[] = [
                    'id' => $method['id'],
                    'title' => $method['title'],
                    'zone_id' => $zone['id'],
                    'zone_name' => $zone['name'],
                    'settings' => $method['settings'] ?? [],
                ];
            }
        }

        $this->dispatch('store-shipping-zones', ['zones' => $zones]);
        $this->dispatch('store-shipping-zone-methods', $methods);
    }

    public function render()
    {
        return view('livewire.pages.pos.index');
    }
}

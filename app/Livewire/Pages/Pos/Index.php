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
    public function fetchProductsFromAPI()
    {
        $products = $this->wooService->getProducts(['per_page' => 100])['data'];
        $allProducts = [];
        foreach ($products as $product) {
            $allProducts[] = $product;

            if ($product['type'] === 'variable' && !empty($product['variations'])) {
                // اجلب تفاصيل كل variation
                foreach ($product['variations'] as $variationId) {
                    $variation = $this->wooService->getProduct($variationId);

                    if ($variation) {
                        // ضف علاقة للمنتج الأب إن أردت تتبعها لاحقًا
                        $variation['product_id'] = $product['id'];
                        $allProducts[] = $variation;
                    }
                }
            }
        }

        $this->dispatch('store-products', products: $allProducts);
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

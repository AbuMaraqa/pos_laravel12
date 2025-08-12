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
        $this->products = $this->wooService->getProducts(['per_page' => 100, 'page' => 1])['data'] ?? [];
    }

    public function selectCategory(?int $id = null)
    {
        $this->selectedCategory = $id;
        $params = ['per_page' => 100, 'page' => 1];
        if ($id !== null) {
            $params['category'] = $id;
        }
        $response = $this->wooService->getProducts($params);
        $this->products = $response['data'] ?? $response;
    }

    public function updatedSearch()
    {
        $response = $this->wooService->getProducts(['per_page' => 100, 'search' => $this->search]);
        $this->products = $response['data'] ?? $response;
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
        $perPage = 100;
        $page = 1;

        try {
            // الحصول على العدد الإجمالي
            $initialResponse = $this->wooService->getProductsWithHeaders([
                'per_page' => 1,
                'page' => 1
            ]);

            $totalProducts = isset($initialResponse['headers']['X-WP-Total'][0])
                ? (int)$initialResponse['headers']['X-WP-Total'][0]
                : 1000;
            $totalPages = ceil($totalProducts / $perPage);

            \Log::info("بدء جلب المنتجات", [
                'total_products' => $totalProducts,
                'total_pages' => $totalPages
            ]);

            $this->dispatch('sync-started', [
                'total' => $totalProducts,
                'pages' => $totalPages
            ]);

            // جلب المنتجات صفحة بصفحة
            for ($currentPage = 1; $currentPage <= $totalPages; $currentPage++) {
                $pageProducts = $this->fetchPageProductsOptimized($currentPage, $perPage);

                if (!empty($pageProducts)) {
                    // إرسال فوري للتخزين
                    $this->dispatch('store-products-batch', [
                        'products' => $pageProducts,
                        'page' => $currentPage,
                        'totalPages' => $totalPages
                    ]);
                }

                // تحديث التقدم
                $progress = ($currentPage / $totalPages) * 100;
                $this->dispatch('update-progress', [
                    'page' => $currentPage,
                    'totalPages' => $totalPages,
                    'progress' => round($progress, 1),
                    'message' => "جاري معالجة الصفحة {$currentPage} من {$totalPages}"
                ]);

                // استراحة قصيرة
                usleep(100000); // 0.1 ثانية
            }

            $this->dispatch('sync-completed', [
                'total' => $totalProducts,
                'message' => 'تم جلب جميع المنتجات بنجاح!'
            ]);

        } catch (\Exception $e) {
            \Log::error('فشل في جلب المنتجات', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('sync-error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function fetchPageProductsOptimized($page, $perPage)
    {
        $pageProducts = [];

        try {
            // جلب المنتجات الأساسية
            $response = $this->wooService->getProducts([
                'per_page' => $perPage,
                'page' => $page,
                'status' => 'publish'
            ]);

            $products = $response['data'] ?? $response;

            foreach ($products as $product) {
                // تحضير المنتج الأساسي
                $cleanProduct = $this->prepareProductForPOS($product);
                $pageProducts[] = $cleanProduct;

                // معالجة المتغيرات
                if ($product['type'] === 'variable' && !empty($product['variations'])) {
                    $variations = $this->fetchVariationsOptimized($product['id'], $product['name']);
                    if (!empty($variations)) {
                        $pageProducts = array_merge($pageProducts, $variations);
                    }
                }
            }

        } catch (\Exception $e) {
            \Log::error("فشل في جلب الصفحة {$page}", [
                'error' => $e->getMessage(),
                'page' => $page
            ]);
        }

        return $pageProducts;
    }

    private function prepareProductForPOS($product)
    {
        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'] ?? '',
            'type' => $product['type'],
            'status' => $product['status'],
            'price' => $product['price'] ?? '0',
            'regular_price' => $product['regular_price'] ?? '0',
            'sale_price' => $product['sale_price'] ?? '',
            'stock_quantity' => $product['stock_quantity'] ?? 0,
            'stock_status' => $product['stock_status'] ?? 'instock',
            'manage_stock' => $product['manage_stock'] ?? false,
            'categories' => $product['categories'] ?? [],
            'images' => $this->extractProductImages($product),
            'attributes' => $product['attributes'] ?? [],
            'variations' => $product['variations'] ?? [],
            'short_description' => $product['short_description'] ?? '',
            'synced_at' => now()->toISOString()
        ];
    }

    private function extractProductImages($product)
    {
        $images = [];
        if (!empty($product['images'])) {
            foreach ($product['images'] as $image) {
                $images[] = [
                    'id' => $image['id'] ?? null,
                    'src' => $image['src'] ?? '',
                    'alt' => $image['alt'] ?? $product['name'] ?? ''
                ];
            }
        }
        return $images;
    }

    private function fetchVariationsOptimized($productId, $productName)
    {
        $variations = [];

        try {
            $variationData = $this->wooService->getProductVariations($productId);

            if (is_array($variationData)) {
                foreach ($variationData as $variation) {
                    $cleanVariation = [
                        'id' => $variation['id'],
                        'product_id' => $productId,
                        'type' => 'variation',
                        'name' => $this->buildVariationName($variation, $productName),
                        'sku' => $variation['sku'] ?? '',
                        'price' => $variation['price'] ?? '0',
                        'regular_price' => $variation['regular_price'] ?? '0',
                        'sale_price' => $variation['sale_price'] ?? '',
                        'stock_quantity' => $variation['stock_quantity'] ?? 0,
                        'stock_status' => $variation['stock_status'] ?? 'instock',
                        'manage_stock' => $variation['manage_stock'] ?? false,
                        'attributes' => $variation['attributes'] ?? [],
                        'images' => $this->extractVariationImage($variation),
                        'description' => $variation['description'] ?? '',
                        'synced_at' => now()->toISOString()
                    ];

                    $variations[] = $cleanVariation;
                }
            }

        } catch (\Exception $e) {
            \Log::error("فشل في جلب متغيرات المنتج {$productId}", [
                'error' => $e->getMessage(),
                'product_id' => $productId
            ]);
        }

        return $variations;
    }

    private function buildVariationName($variation, $parentName = '')
    {
        $name = $parentName ? $parentName . ' - ' : 'متغير ';

        if (!empty($variation['attributes'])) {
            $attributeNames = [];
            foreach ($variation['attributes'] as $attribute) {
                if (!empty($attribute['option'])) {
                    $attributeNames[] = $attribute['option'];
                }
            }
            if (!empty($attributeNames)) {
                $name .= implode(' - ', $attributeNames);
            }
        } else {
            $name .= '#' . $variation['id'];
        }

        return $name;
    }

    private function extractVariationImage($variation)
    {
        $images = [];
        if (!empty($variation['image'])) {
            $images[] = [
                'id' => $variation['image']['id'] ?? null,
                'src' => $variation['image']['src'] ?? '',
                'alt' => $variation['image']['alt'] ?? ''
            ];
        }
        return $images;
    }

    #[On('quick-sync-products')]
    public function quickSyncProducts()
    {
        try {
            $perPage = 100;
            $page = 1;
            $allProducts = [];

            do {
                $response = $this->wooService->getProducts([
                    'per_page' => $perPage,
                    'page' => $page,
                    'status' => 'publish',
                    'type' => 'simple'
                ]);

                $products = $response['data'] ?? $response;

                if (!empty($products)) {
                    foreach ($products as $product) {
                        $allProducts[] = $this->prepareProductForPOS($product);
                    }

                    $this->dispatch('store-products-batch', [
                        'products' => $allProducts,
                        'page' => $page
                    ]);

                    $allProducts = [];
                }

                $page++;
            } while (!empty($products) && count($products) == $perPage);

            $this->dispatch('quick-sync-completed', [
                'message' => 'تم جلب المنتجات الأساسية بسرعة!'
            ]);

        } catch (\Exception $e) {
            $this->dispatch('sync-error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    #[On('fetch-variations-background')]
    public function fetchVariationsInBackground()
    {
        try {
            $variableProducts = $this->wooService->getProducts([
                'type' => 'variable',
                'status' => 'publish',
                'per_page' => 50
            ]);

            $products = $variableProducts['data'] ?? $variableProducts;

            foreach ($products as $product) {
                if (!empty($product['variations'])) {
                    $variations = $this->fetchVariationsOptimized($product['id'], $product['name']);

                    if (!empty($variations)) {
                        $this->dispatch('store-variations-batch', [
                            'variations' => $variations,
                            'product_id' => $product['id']
                        ]);
                    }
                }
            }

            $this->dispatch('variations-sync-completed', [
                'message' => 'تم جلب جميع المتغيرات!'
            ]);

        } catch (\Exception $e) {
            \Log::error('فشل في جلب المتغيرات', [
                'error' => $e->getMessage()
            ]);
        }
    }

    #[On('fetch-categories-from-api')]
    public function fetchCategoriesFromAPI()
    {
        $categories = $this->wooService->getCategories(['parent' => 0]);
        $this->dispatch('store-categories', ['categories' => $categories]);
    }

    #[On('fetch-customers-from-api')]
    public function fetchCustomersFromAPI()
    {
        $response = $this->wooService->getCustomers();
        $customers = $response['data'] ?? $response;
        $this->dispatch('store-customers', ['customers' => $customers]);
    }

    #[On('submit-order')]
    public function submitOrder($order)
    {
        $orderData = $order ?? [];

        try {
            if (!empty($orderData['customer_id'])) {
                $customer = $this->wooService->getUserById($orderData['customer_id']);

                $orderData['billing'] = [
                    'first_name' => $customer['first_name'] ?? '',
                    'last_name' => $customer['last_name'] ?? '',
                    'email' => $customer['email'] ?? '',
                    'phone' => $customer['billing']['phone'] ?? '',
                    'address_1' => $customer['billing']['address_1'] ?? '',
                    'city' => $customer['billing']['city'] ?? '',
                    'country' => $customer['billing']['country'] ?? 'PS',
                ];
            }

            $order = $this->wooService->createOrder($orderData);

            foreach ($orderData['line_items'] as $item) {
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
        $this->dispatch('store-shipping-methods', ['methods' => $methods]);
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
        $this->dispatch('store-shipping-zone-methods', [$methods]);
    }

    public function render()
    {
        return view('livewire.pages.pos.index');
    }
}

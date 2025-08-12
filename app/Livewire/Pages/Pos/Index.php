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

    #[On('fetch-products-from-api')]
    public function fetchProductsFromAPI()
    {
        $perPage = 100;
        $page = 1;

        try {
            // جلب العدد الإجمالي
            $initialResponse = $this->wooService->getProductsWithHeaders([
                'per_page' => 1,
                'page' => 1
            ])['data'];

            $totalProducts = isset($initialResponse['headers']['X-WP-Total'][0])
                ? (int)$initialResponse['headers']['X-WP-Total'][0]
                : 1000;
            $totalPages = ceil($totalProducts / $perPage);

            \Log::info("بدء جلب سريع للمنتجات", [
                'total_products' => $totalProducts,
                'total_pages' => $totalPages
            ]);

            $this->dispatch('sync-started', [
                'total' => $totalProducts,
                'pages' => $totalPages
            ]);

            // جلب المنتجات الأساسية فقط أولاً (بدون متغيرات)
            for ($currentPage = 1; $currentPage <= $totalPages; $currentPage++) {
                $pageProducts = $this->fetchPageProductsUltraFast($currentPage, $perPage);

                if (!empty($pageProducts)) {
                    $this->dispatch('store-products-batch', [
                        'products' => $pageProducts,
                        'page' => $currentPage,
                        'totalPages' => $totalPages
                    ]);
                }

                $progress = ($currentPage / $totalPages) * 100;
                $this->dispatch('update-progress', [
                    'page' => $currentPage,
                    'totalPages' => $totalPages,
                    'progress' => round($progress, 1),
                    'message' => "جلب سريع - الصفحة {$currentPage} من {$totalPages}"
                ]);

                // استراحة صغيرة جداً
                usleep(50000); // 0.05 ثانية
            }

            $this->dispatch('sync-completed', [
                'total' => $totalProducts,
                'message' => 'تم جلب المنتجات الأساسية بسرعة! المتغيرات ستُحمل في الخلفية.'
            ]);

            // بدء جلب المتغيرات في الخلفية (اختياري)
            $this->dispatch('start-background-variations');

        } catch (\Exception $e) {
            \Log::error('فشل في جلب المنتجات', [
                'error' => $e->getMessage()
            ]);

            $this->dispatch('sync-error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function fetchPageProductsUltraFast($page, $perPage)
    {
        $pageProducts = [];

        try {
            // جلب المنتجات مع حقول محددة فقط لتوفير البيانات
            $response = $this->wooService->getProducts([
                'per_page' => $perPage,
                'page' => $page,
                'status' => 'publish',
                '_fields' => 'id,name,type,price,regular_price,sale_price,sku,stock_quantity,stock_status,categories,images,variations'
            ]);

            $products = $response['data'] ?? $response;

            foreach ($products as $product) {
                // تحضير المنتج بأقل البيانات المطلوبة
                $cleanProduct = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'sku' => $product['sku'] ?? '',
                    'type' => $product['type'],
                    'price' => $product['price'] ?? '0',
                    'regular_price' => $product['regular_price'] ?? '0',
                    'sale_price' => $product['sale_price'] ?? '',
                    'stock_quantity' => $product['stock_quantity'] ?? 0,
                    'stock_status' => $product['stock_status'] ?? 'instock',
                    'categories' => $product['categories'] ?? [],
                    'images' => $this->extractProductImagesQuick($product),
                    'variations' => $product['variations'] ?? [],
                    'synced_at' => now()->toISOString(),
                    'has_variations' => $product['type'] === 'variable' && !empty($product['variations'])
                ];

                $pageProducts[] = $cleanProduct;
            }

        } catch (\Exception $e) {
            \Log::error("فشل في جلب الصفحة {$page}", [
                'error' => $e->getMessage(),
                'page' => $page
            ]);
        }

        return $pageProducts;
    }

    private function extractProductImagesQuick($product)
    {
        if (empty($product['images'])) return [];

        // جلب أول صورة فقط لتوفير الذاكرة
        $firstImage = $product['images'][0] ?? null;
        if (!$firstImage) return [];

        return [[
            'id' => $firstImage['id'] ?? null,
            'src' => $firstImage['src'] ?? '',
            'alt' => $firstImage['alt'] ?? $product['name'] ?? ''
        ]];
    }

    #[On('fetch-variations-on-demand')]
    public function fetchVariationsOnDemand($productId)
    {
        try {
            $variations = $this->wooService->getProductVariations($productId);
            $processedVariations = [];

            foreach ($variations as $variation) {
                $processedVariations[] = [
                    'id' => $variation['id'],
                    'product_id' => $productId,
                    'type' => 'variation',
                    'name' => $this->buildVariationNameQuick($variation, $productId),
                    'sku' => $variation['sku'] ?? '',
                    'price' => $variation['price'] ?? '0',
                    'regular_price' => $variation['regular_price'] ?? '0',
                    'sale_price' => $variation['sale_price'] ?? '',
                    'stock_quantity' => $variation['stock_quantity'] ?? 0,
                    'stock_status' => $variation['stock_status'] ?? 'instock',
                    'attributes' => $variation['attributes'] ?? [],
                    'images' => $this->extractVariationImageQuick($variation),
                    'synced_at' => now()->toISOString()
                ];
            }

            $this->dispatch('store-variations-for-product', [
                'product_id' => $productId,
                'variations' => $processedVariations
            ]);

        } catch (\Exception $e) {
            \Log::error("فشل في جلب متغيرات المنتج {$productId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function buildVariationNameQuick($variation, $productId)
    {
        if (!empty($variation['attributes'])) {
            $attributeNames = [];
            foreach ($variation['attributes'] as $attribute) {
                if (!empty($attribute['option'])) {
                    $attributeNames[] = $attribute['option'];
                }
            }
            if (!empty($attributeNames)) {
                return implode(' - ', $attributeNames);
            }
        }

        return 'متغير #' . $variation['id'];
    }

    private function extractVariationImageQuick($variation)
    {
        if (empty($variation['image'])) return [];

        return [[
            'id' => $variation['image']['id'] ?? null,
            'src' => $variation['image']['src'] ?? '',
            'alt' => $variation['image']['alt'] ?? ''
        ]];
    }

    #[On('quick-sync-products')]
    public function quickSyncProducts()
    {
        try {
            $perPage = 100;
            $page = 1;
            $totalFetched = 0;

            $this->dispatch('sync-started', [
                'total' => 'غير محدد',
                'pages' => 'جلب سريع'
            ]);

            do {
                $response = $this->wooService->getProducts([
                    'per_page' => $perPage,
                    'page' => $page,
                    'status' => 'publish',
                    'type' => 'simple', // فقط المنتجات البسيطة
                    '_fields' => 'id,name,type,price,sku,stock_quantity,images,categories'
                ]);

                $products = $response['data'] ?? $response;

                if (!empty($products)) {
                    $processedProducts = [];
                    foreach ($products as $product) {
                        $processedProducts[] = [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'sku' => $product['sku'] ?? '',
                            'type' => $product['type'],
                            'price' => $product['price'] ?? '0',
                            'stock_quantity' => $product['stock_quantity'] ?? 0,
                            'categories' => $product['categories'] ?? [],
                            'images' => $this->extractProductImagesQuick($product),
                            'variations' => [],
                            'synced_at' => now()->toISOString()
                        ];
                    }

                    $this->dispatch('store-products-batch', [
                        'products' => $processedProducts,
                        'page' => $page
                    ]);

                    $totalFetched += count($products);

                    $this->dispatch('update-progress', [
                        'page' => $page,
                        'totalPages' => '؟',
                        'progress' => min(($totalFetched / 500) * 100, 99), // تقدير
                        'message' => "جلب سريع - {$totalFetched} منتج"
                    ]);
                }

                $page++;
                usleep(30000); // 0.03 ثانية

            } while (!empty($products) && count($products) == $perPage && $page <= 20); // حد أقصى 20 صفحة

            $this->dispatch('sync-completed', [
                'total' => $totalFetched,
                'message' => "تم جلب {$totalFetched} منتج بسرعة!"
            ]);

        } catch (\Exception $e) {
            $this->dispatch('sync-error', [
                'error' => $e->getMessage()
            ]);
        }
    }

    #[On('background-variations-sync')]
    public function backgroundVariationsSync()
    {
        try {
            // جلب المنتجات المتغيرة بحد أقصى 10 منتجات
            $variableProducts = $this->wooService->getProducts([
                'type' => 'variable',
                'status' => 'publish',
                'per_page' => 10 // قليل عشان ما ياخذ وقت
            ]);

            $products = $variableProducts['data'] ?? $variableProducts;
            $processedCount = 0;

            foreach ($products as $product) {
                if (!empty($product['variations']) && $processedCount < 5) { // حد أقصى 5 منتجات
                    $this->fetchVariationsOnDemand($product['id']);
                    $processedCount++;
                    usleep(200000); // 0.2 ثانية بين كل منتج
                }
            }

            $this->dispatch('background-sync-completed', [
                'message' => "تم جلب متغيرات {$processedCount} منتجات في الخلفية"
            ]);

        } catch (\Exception $e) {
            \Log::error('فشل في المزامنة الخلفية', [
                'error' => $e->getMessage()
            ]);
        }
    }

    // باقي الدوال كما هي...
    #[On('fetch-categories-from-api')]
    public function fetchCategoriesFromAPI()
    {
        $categories = $this->wooService->getCategories(['parent' => 0]);
        $this->dispatch('store-categories', ['categories' => $categories]);
    }

    #[On('fetch-customers-from-api')]
    public function fetchCustomersFromAPI()
    {
        $response = $this->wooService->getCustomers(['per_page' => 100]);
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

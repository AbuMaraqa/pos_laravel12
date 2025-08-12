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

        try {
            // Pass 1: Fetch Products
            $initialResponse = $this->wooService->getProductsWithHeaders(['per_page' => 1, 'page' => 1]);
            $totalProducts = isset($initialResponse['headers']['X-WP-Total'][0]) ? (int)$initialResponse['headers']['X-WP-Total'][0] : 0;

            if ($totalProducts === 0) {
                $this->dispatch('sync-completed', ['message' => 'لا توجد منتجات لجلبها.']);
                return;
            }

            $totalPages = ceil($totalProducts / $perPage);

            $this->dispatch('sync-started', ['total' => $totalProducts, 'pages' => $totalPages]);

            $productsToStore = [];
            $variableProductIds = [];

            for ($currentPage = 1; $currentPage <= $totalPages; $currentPage++) {
                $response = $this->wooService->getProducts([
                    'per_page' => $perPage,
                    'page' => $currentPage,
                    'status' => 'publish',
                    '_fields' => 'id,name,type,price,regular_price,sale_price,sku,stock_quantity,stock_status,categories,images,variations'
                ]);
                $products = $response['data'] ?? $response;

                foreach ($products as $product) {
                    if ($product['type'] === 'variable') {
                        $variableProductIds[] = $product['id'];
                    }
                    $productsToStore[] = $this->buildProductData($product);
                }

                $progress = ($currentPage / $totalPages) * 50;
                $this->dispatch('update-progress', [
                    'page' => $currentPage,
                    'totalPages' => $totalPages,
                    'progress' => round($progress, 1),
                    'message' => "جلب المنتجات الأساسية - صفحة {$currentPage} من {$totalPages}"
                ]);
                usleep(50000);
            }

            // Dispatch all products at once after fetching is complete
            if (!empty($productsToStore)) {
                $this->dispatch('store-products-batch', ['products' => $productsToStore]);
            }

            // Pass 2: Fetch and store Variations
            $totalVariationsToFetch = count($variableProductIds);
            $allVariations = [];
            if ($totalVariationsToFetch > 0) {
                $fetchedCount = 0;
                foreach ($variableProductIds as $productId) {
                    $variations = $this->fetchAndProcessVariations($productId);
                    if (!empty($variations)) {
                        $allVariations = array_merge($allVariations, $variations);
                    }
                    $fetchedCount++;
                    $progress = 50 + (($fetchedCount / $totalVariationsToFetch) * 50);
                    $this->dispatch('update-progress', [
                        'page' => $fetchedCount,
                        'totalPages' => $totalVariationsToFetch,
                        'progress' => round($progress, 1),
                        'message' => "جلب المتغيرات للمنتج {$fetchedCount} من {$totalVariationsToFetch}"
                    ]);
                    usleep(200000);
                }
            }

            // Dispatch all variations at once after fetching is complete
            if (!empty($allVariations)) {
                $this->dispatch('store-products-batch', ['products' => $allVariations]);
            }

            $this->dispatch('sync-completed', ['message' => 'اكتملت المزامنة بنجاح! تم جلب جميع المنتجات والمتغيرات.']);

        } catch (\Exception $e) {
            \Log::error('فشل في جلب المنتجات', ['error' => $e->getMessage()]);
            $this->dispatch('sync-error', ['error' => $e->getMessage()]);
        }
    }

    #[On('fetch-variations-on-demand')]
    public function fetchVariationsOnDemand($productId)
    {
        try {
            $variations = $this->fetchAndProcessVariations($productId);
            if (!empty($variations)) {
                $this->dispatch('store-products-batch', ['products' => $variations]);
                $this->dispatch('variations-synced-on-demand', ['productId' => $productId]);
            }
        } catch (\Exception $e) {
            \Log::error("فشل في جلب متغيرات المنتج {$productId} عند الطلب", ['error' => $e->getMessage()]);
            $this->dispatch('sync-error', ['error' => $e->getMessage()]);
        }
    }

    private function buildProductData($product)
    {
        return [
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
    }

    private function fetchAndProcessVariations($productId)
    {
        try {
            $variations = $this->wooService->getProductVariations($productId);
            $processedVariations = [];

            foreach ($variations as $variation) {
                $processedVariations[] = [
                    'id' => $variation['id'],
                    'product_id' => $productId, // Key change: store the parent product ID
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
            return $processedVariations;

        } catch (\Exception $e) {
            \Log::error("فشل في جلب متغيرات المنتج {$productId}", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function extractProductImagesQuick($product)
    {
        if (empty($product['images'])) return [];

        $firstImage = $product['images'][0] ?? null;
        if (!$firstImage) return [];

        return [[
            'id' => $firstImage['id'] ?? null,
            'src' => $firstImage['src'] ?? '',
            'alt' => $firstImage['alt'] ?? $product['name'] ?? ''
        ]];
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

            $processedProducts = [];
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

            if (!empty($processedProducts)) {
                $this->dispatch('store-products-batch', ['products' => $processedProducts]);
            }

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
                    $variations = $this->fetchAndProcessVariations($product['id']);
                    if (!empty($variations)) {
                        $this->dispatch('store-products-batch', ['products' => $variations]);
                    }
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

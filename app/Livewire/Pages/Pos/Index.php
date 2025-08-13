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

    // إضافات للـ Pagination
    public int $currentPage = 1;
    public int $perPage = 50; // حجم صفحة أفضل للأداء
    public int $totalPages = 1;
    public int $totalProducts = 0;
    public bool $isLoading = false;
    public bool $isBackgroundSyncing = false;

    protected $wooService;

    public function boot(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
        // جلب الكاتيجوريز فقط في البداية
        $this->categories = $this->wooService->getCategories(['parent' => 0]);

        // جلب أول صفحة من المنتجات بحجم صغير للعرض السريع
        $this->loadProducts();
    }

    // 📍 تحميل المنتجات مع Pagination
    public function loadProducts($append = false)
    {
        $this->isLoading = true;

        $params = [
            'per_page' => $this->perPage,
            'page' => $this->currentPage,
        ];

        // إضافة فلتر الكاتيجوري إذا كان محدد
        if ($this->selectedCategory !== null && $this->selectedCategory > 0) {
            $params['category'] = $this->selectedCategory;
        }

        // إضافة فلتر البحث إذا موجود
        if (!empty($this->search)) {
            $params['search'] = $this->search;
        }

        try {
            $response = $this->wooService->getProducts($params);

            // معالجة الاستجابة حسب هيكل الـ API
            if (isset($response['data'])) {
                $products = $response['data'];
                $this->totalPages = $response['total_pages'] ?? 1;
                $this->totalProducts = $response['total'] ?? count($products);
            } else {
                $products = $response;
                // حساب التقريبي للصفحات
                $this->totalPages = ceil(count($products) / $this->perPage);
            }

            if ($append) {
                // إضافة المنتجات الجديدة للموجودة (للـ infinite scroll)
                $this->products = array_merge($this->products, $products);
            } else {
                // استبدال المنتجات (للـ pagination عادي)
                $this->products = $products;
            }

            $this->isLoading = false;

            // إرسال للجافا سكريبت للتخزين في IndexedDB
            $this->dispatch('products-loaded', [
                'products' => $products,
                'currentPage' => $this->currentPage,
                'totalPages' => $this->totalPages,
                'append' => $append,
                'totalProducts' => $this->totalProducts
            ]);

        } catch (\Exception $e) {
            $this->isLoading = false;
            logger()->error('Error loading products: ' . $e->getMessage());
            $this->dispatch('api-error', [
                'message' => 'حدث خطأ في تحميل المنتجات. الرجاء المحاولة مرة أخرى.'
            ]);
        }
    }

    // 📍 Background Sync مع معالجة أفضل للأخطاء
    #[On('start-background-sync')]
    public function startBackgroundSync()
    {
        if ($this->isBackgroundSyncing) {
            return;
        }

        $this->isBackgroundSyncing = true;
        $this->currentPage = 1;

        $this->dispatch('sync-started');
        $this->fetchProductsChunk();
    }

    #[On('fetch-products-chunk')]
    public function fetchProductsChunk()
    {
        try {
            $response = $this->wooService->getProducts([
                'per_page' => $this->perPage,
                'page' => $this->currentPage,
            ]);

            $products = $response['data'] ?? $response;
            $totalPages = $response['total_pages'] ?? null;

            // معالجة المتغيرات للمنتجات المتغيرة (بشكل محدود لتجنب البطء)
            $enrichedProducts = [];
            foreach ($products as $product) {
                $enrichedProducts[] = $product;

                // جلب المتغيرات فقط للمنتجات المهمة وبحد أقصى
                if ($product['type'] === 'variable' && !empty($product['variations'])) {
                    $variations = array_slice($product['variations'], 0, 20); // أول 20 متغير فقط
                    foreach ($variations as $variationId) {
                        try {
                            $variation = $this->wooService->getProduct($variationId);
                            if ($variation) {
                                $variation['product_id'] = $product['id'];
                                $enrichedProducts[] = $variation;
                            }
                        } catch (\Exception $e) {
                            // تجاهل الأخطاء وأكمل
                            logger()->warning('Failed to fetch variation ' . $variationId . ': ' . $e->getMessage());
                            continue;
                        }
                    }
                }
            }

            // إرسال للجافا سكريبت للتخزين
            $this->dispatch('store-products-chunk', [
                'products' => $enrichedProducts,
                'page' => $this->currentPage,
                'totalPages' => $totalPages
            ]);

            // هل يوجد صفحات أخرى؟
            $hasMore = $totalPages ? $this->currentPage < $totalPages : count($products) === $this->perPage;

            $this->dispatch('sync-progress', [
                'page' => $this->currentPage,
                'totalPages' => $totalPages,
                'hasMore' => $hasMore,
                'progress' => $totalPages ? ($this->currentPage / $totalPages * 100) : 0
            ]);

            if ($hasMore) {
                $this->currentPage++;
                // استدعاء الصفحة التالية بتأخير قصير لتجنب إرهاق الخادم
                $this->dispatch('schedule-next-chunk');
            } else {
                $this->isBackgroundSyncing = false;
                $this->dispatch('sync-completed');
            }

        } catch (\Exception $e) {
            $this->isBackgroundSyncing = false;
            logger()->error('Error in background sync: ' . $e->getMessage());
            $this->dispatch('sync-error', [
                'message' => 'حدث خطأ أثناء المزامنة: ' . $e->getMessage()
            ]);
        }
    }

    // 📍 Load More للصفحات الإضافية
    #[On('load-more')]
    public function loadMore()
    {
        if ($this->currentPage < $this->totalPages && !$this->isLoading) {
            $this->currentPage++;
            $this->loadProducts(true); // append = true
        }
    }

    // 📍 البحث مع Debouncing
    public function updatedSearch()
    {
        // إعادة تعيين للصفحة الأولى عند البحث
        $this->currentPage = 1;
        $this->loadProducts();
    }

    #[On('perform-search')]
    public function performSearch($searchTerm = null)
    {
        if ($searchTerm !== null) {
            $this->search = $searchTerm;
        }

        $this->currentPage = 1;
        $this->loadProducts();
    }

    public function selectCategory(?int $id = null)
    {
        $this->selectedCategory = $id;
        $this->currentPage = 1; // إعادة تعيين للصفحة الأولى
        $this->search = ''; // مسح البحث

        $this->loadProducts();
    }

    // 📍 الدوال الأصلية مع تحسينات
    public function syncProductsToIndexedDB()
    {
        try {
            $products = $this->wooService->getProducts(['per_page' => 100]);
            return $products;
        } catch (\Exception $e) {
            logger()->error('Error syncing products: ' . $e->getMessage());
            return [];
        }
    }

    public function syncCategoriesToIndexedDB()
    {
        try {
            $categories = $this->wooService->getCategories();
            return $categories;
        } catch (\Exception $e) {
            logger()->error('Error syncing categories: ' . $e->getMessage());
            return [];
        }
    }

    public function openVariationsModal($id, string $type)
    {
        if ($type == 'variable') {
            try {
                $this->variations = $this->wooService->getProductVariations($id);
                $this->dispatch('show-variations-modal', [
                    'variations' => $this->variations,
                    'product_id' => $id
                ]);
            } catch (\Exception $e) {
                logger()->error('Error loading variations: ' . $e->getMessage());
                $this->dispatch('api-error', [
                    'message' => 'حدث خطأ في تحميل المتغيرات'
                ]);
            }
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
    public function fetchProductsFromAPI(int $page = 1, int $perPage = 50): void
    {
        try {
            $response = $this->wooService->getProducts([
                'per_page' => $perPage,
                'page' => $page,
            ]);

            $products = $response['data'] ?? $response;
            $totalPages = $response['total_pages'] ?? null;

            $chunk = [];

            foreach ($products as $product) {
                $chunk[] = $product;

                // معالجة المتغيرات بحذر
                if ($product['type'] === 'variable' && !empty($product['variations'])) {
                    // جلب المتغيرات بشكل محدود
                    $variations = array_slice($product['variations'], 0, 10); // أول 10 متغيرات فقط
                    foreach ($variations as $variationId) {
                        try {
                            $variation = $this->wooService->getProduct($variationId);
                            if ($variation) {
                                $variation['product_id'] = $product['id'];
                                $chunk[] = $variation;
                            }
                        } catch (\Exception $e) {
                            logger()->warning('Failed to fetch variation: ' . $e->getMessage());
                            continue;
                        }
                    }
                }
            }

            $this->dispatch('store-products', ['products' => $chunk]);

            $hasMore = $totalPages
                ? $page < (int) $totalPages
                : (is_array($products) && count($products) === $perPage);

            $this->dispatch('products-chunk-progress', [
                'page' => $page,
                'hasMore' => $hasMore,
                'perPage' => $perPage,
                'totalPages' => $totalPages
            ]);

            if (!$hasMore) {
                $this->dispatch('products-chunk-finished');
            }

        } catch (\Exception $e) {
            logger()->error('Error fetching products from API: ' . $e->getMessage());
            $this->dispatch('api-error', [
                'message' => 'حدث خطأ في جلب المنتجات من API'
            ]);
        }
    }

    #[On('fetch-categories-from-api')]
    public function fetchCategoriesFromAPI()
    {
        try {
            $categories = $this->wooService->getCategories(['parent' => 0]);
            $this->dispatch('store-categories', ['categories' => $categories]);
        } catch (\Exception $e) {
            logger()->error('Error fetching categories: ' . $e->getMessage());
            $this->dispatch('api-error', [
                'message' => 'حدث خطأ في جلب التصنيفات'
            ]);
        }
    }

    #[On('fetch-all-variations')]
    public function fetchAllVariations()
    {
        try {
            $page = 1;
            do {
                $response = $this->wooService->getVariableProductsPaginated($page);
                $products = $response['data'] ?? $response;

                foreach ($products as $product) {
                    try {
                        $variations = $this->wooService->getVariationsByProductId($product['id']);

                        foreach ($variations as &$v) {
                            $v['product_id'] = $product['id'];
                        }

                        $this->dispatch('store-variations', [
                            'product_id' => $product['id'],
                            'variations' => $variations,
                        ]);
                    } catch (\Exception $e) {
                        logger()->warning('Failed to fetch variations for product ' . $product['id'] . ': ' . $e->getMessage());
                        continue;
                    }
                }

                $page++;
                $hasMore = isset($response['total_pages']) && $page <= $response['total_pages'];
            } while ($hasMore);

        } catch (\Exception $e) {
            logger()->error('Error fetching all variations: ' . $e->getMessage());
            $this->dispatch('api-error', [
                'message' => 'حدث خطأ في جلب المتغيرات'
            ]);
        }
    }

    #[On('fetch-customers-from-api')]
    public function fetchCustomersFromAPI()
    {
        try {
            $customers = $this->wooService->getCustomers();
            $this->dispatch('store-customers', ['customers' => $customers]);
        } catch (\Exception $e) {
            logger()->error('Error fetching customers: ' . $e->getMessage());
            $this->dispatch('api-error', [
                'message' => 'حدث خطأ في جلب العملاء'
            ]);
        }
    }

    #[On('add-simple-to-cart')]
    public function addSimpleToCart($product)
    {
        $productId = $product['id'] ?? null;

        if (!$productId) return;

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
            $this->dispatch('order-failed', [
                'message' => 'فشل في إنشاء الطلب: ' . $e->getMessage()
            ]);
        }
    }

    #[On('fetch-shipping-methods-from-api')]
    public function fetchShippingMethods()
    {
        try {
            $methods = $this->wooService->getShippingMethods();
            $this->dispatch('store-shipping-methods', ['methods' => $methods]);
        } catch (\Exception $e) {
            logger()->error('Error fetching shipping methods: ' . $e->getMessage());
        }
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
        try {
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
        } catch (\Exception $e) {
            logger()->error('Error fetching shipping zones and methods: ' . $e->getMessage());
        }
    }

    // 📍 Cache المنتجات محليًا
    public function getCachedProducts()
    {
        $cacheKey = "pos_products_{$this->selectedCategory}_{$this->search}_{$this->currentPage}";

        return cache()->remember($cacheKey, now()->addMinutes(5), function() {
            return $this->loadProducts();
        });
    }

    // 📍 إحصائيات للمراقبة
    #[On('get-sync-status')]
    public function getSyncStatus()
    {
        $this->dispatch('sync-status', [
            'isLoading' => $this->isLoading,
            'isBackgroundSyncing' => $this->isBackgroundSyncing,
            'currentPage' => $this->currentPage,
            'totalPages' => $this->totalPages,
            'totalProducts' => $this->totalProducts
        ]);
    }

    public function render()
    {
        return view('livewire.pages.pos.index');
    }
}

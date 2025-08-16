<?php
// ================================================
// FILENAME: Index.php
// الوصف: يحتوي على منطق Livewire لإدارة نقطة البيع
// ================================================

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

    /**
     * ✅ دالة جديدة للبحث عن منتج واحد من API
     * هذه الدالة هي المسؤولة عن البحث عن المنتج الأب أو المتغير
     */
    #[On('search-product-from-api')]
    public function searchProductFromAPI($searchTerm)
    {
        try {
            logger()->info('Searching for product in API', ['term' => $searchTerm]);

            $foundProduct = null;
            $foundVariation = null;
            $specificVariation = null; // 🔥 المتغير المحدد الذي تم العثور عليه

            // ================================================
            // ✅ البحث المباشر عن المتغير أولاً
            // ================================================
            try {
                $searchResult = $this->wooService->getProduct($searchTerm);

                if ($searchResult && isset($searchResult['type']) && $searchResult['type'] === 'variation') {
                    logger()->info('Variation found directly by ID/SKU', ['id' => $searchResult['id']]);

                    // 🔥 احفظ تفاصيل المتغير المحدد
                    $specificVariation = $searchResult;

                    // 🔥 اجلب المنتج الأب للمتغير
                    $parentProductId = $searchResult['parent_id'] ?? null;
                    if ($parentProductId) {
                        $foundProduct = $this->wooService->getProductsById($parentProductId);
                        logger()->info('Parent product found for variation', [
                            'variation_id' => $searchResult['id'],
                            'parent_id' => $parentProductId
                        ]);
                    } else {
                        // إذا لم نجد parent_id، ابحث عنه في جميع المنتجات المتغيرة
                        $foundProduct = $this->findParentProductForVariation($searchResult['id']);
                    }

                    if ($foundProduct) {
                        // ✅ إرسال المنتج مع المتغير المحدد مباشرة
                        return $this->sendFoundProductWithSpecificVariation($foundProduct, $specificVariation, $searchTerm);
                    }
                }
            } catch (\Exception $e) {
                logger()->warning('Direct variation search failed, continuing with product search', [
                    'term' => $searchTerm,
                    'error' => $e->getMessage()
                ]);
            }

            // ================================================
            // إذا لم يتم العثور على متغير، ابحث عن المنتج الأب
            // ================================================

            // 1. البحث بالـ ID أولاً (للباركود)
            if (is_numeric($searchTerm)) {
                try {
                    $foundProduct = $this->wooService->getProductsById($searchTerm);
                    if ($foundProduct && isset($foundProduct['id'])) {
                        logger()->info('Product found by ID', ['product_id' => $foundProduct['id']]);
                    }
                } catch (\Exception $e) {
                    logger()->info('Product not found by ID', ['id' => $searchTerm]);
                    $foundProduct = null;
                }
            }

            // 2. إذا لم نجد بالـ ID، نبحث بالاسم أو SKU
            if (!$foundProduct) {
                $searchResults = $this->wooService->getProducts([
                    'search' => $searchTerm,
                    'per_page' => 10
                ]);

                if (!empty($searchResults)) {
                    $data = isset($searchResults['data']) ? $searchResults['data'] : $searchResults;
                    if (count($data) > 0) {
                        $foundProduct = $data[0];
                        logger()->info('Product found by search', ['product_id' => $foundProduct['id']]);
                    }
                }

                // البحث بـ SKU إذا لم نجد شيئاً
                if (!$foundProduct) {
                    $skuResults = $this->wooService->getProducts([
                        'sku' => $searchTerm,
                        'per_page' => 5
                    ]);

                    if (!empty($skuResults)) {
                        $data = isset($skuResults['data']) ? $skuResults['data'] : $skuResults;
                        if (count($data) > 0) {
                            $foundProduct = $data[0];
                            logger()->info('Product found by SKU', ['product_id' => $foundProduct['id']]);
                        }
                    }
                }
            }

            // 3. إذا لم نجد المنتج، نحاول البحث في المتغيرات
            if (!$foundProduct) {
                $variationSearchResult = $this->searchInVariationsAPI($searchTerm);

                if ($variationSearchResult) {
                    $foundProduct = $variationSearchResult['parent_product'];
                    $specificVariation = $variationSearchResult['found_variation'];

                    logger()->info('Product found through variation search', [
                        'parent_product_id' => $foundProduct['id'],
                        'found_variation_id' => $specificVariation['id']
                    ]);
                }
            }

            if ($foundProduct) {
                return $this->sendFoundProductWithSpecificVariation($foundProduct, $specificVariation, $searchTerm);
            } else {
                logger()->info('Product not found in API', ['term' => $searchTerm]);
                $this->dispatch('product-not-found', ['term' => $searchTerm]);
                return null;
            }

        } catch (\Exception $e) {
            logger()->error('Error searching product from API', [
                'term' => $searchTerm,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('search-error', [
                'message' => 'حدث خطأ أثناء البحث: ' . $e->getMessage()
            ]);

            return null;
        }
    }

    private function sendFoundProductWithSpecificVariation($foundProduct, $specificVariation, $searchTerm)
    {
        try {
            // ✅ إذا كان المنتج متغير، اجلب متغيراته كاملة
            if ($foundProduct['type'] === 'variable' && !empty($foundProduct['variations'])) {
                $variationsData = $this->fetchCompleteVariations($foundProduct['id'], $foundProduct['variations']);

                // إضافة المتغيرات للمنتج
                $foundProduct['variations_full'] = $variationsData['variations_full'];

                // 🔥 إذا تم العثور على متغير محدد، ضعه في المقدمة
                if ($specificVariation) {
                    $foundProduct['target_variation'] = [
                        'id' => $specificVariation['id'],
                        'name' => $this->generateVariationName($specificVariation),
                        'price' => $specificVariation['price'] ?? $specificVariation['regular_price'] ?? 0,
                        'sku' => $specificVariation['sku'] ?? '',
                        'attributes' => $specificVariation['attributes'] ?? [],
                        'stock_status' => $specificVariation['stock_status'] ?? 'instock',
                        'stock_quantity' => $specificVariation['stock_quantity'] ?? 0,
                        'type' => 'variation',
                        'product_id' => $foundProduct['id']
                    ];

                    logger()->info('Product prepared with target variation', [
                        'product_id' => $foundProduct['id'],
                        'target_variation_id' => $specificVariation['id'],
                        'search_term' => $searchTerm
                    ]);
                }

                // إرسال المتغيرات للتخزين في IndexedDB
                if (!empty($variationsData['for_storage'])) {
                    $this->dispatch('store-variations', [
                        'product_id' => $foundProduct['id'],
                        'variations' => $variationsData['for_storage'],
                    ]);
                }
            }

            // إرسال المنتج الموجود للـ JavaScript
            $this->dispatch('product-found-from-api', [
                'product' => $foundProduct,
                'search_term' => $searchTerm,
                'has_target_variation' => isset($foundProduct['target_variation'])
            ]);

            return $foundProduct;

        } catch (\Exception $e) {
            logger()->error('Error preparing found product', [
                'product_id' => $foundProduct['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function findParentProductForVariation($variationId)
    {
        try {
            // البحث في المنتجات المتغيرة للعثور على المنتج الأب
            $variableProducts = $this->wooService->getProducts([
                'type' => 'variable',
                'per_page' => 100,
                'status' => 'publish'
            ]);

            $products = isset($variableProducts['data']) ? $variableProducts['data'] : $variableProducts;

            foreach ($products as $product) {
                if (!empty($product['variations']) && in_array($variationId, $product['variations'])) {
                    logger()->info('Parent product found for variation', [
                        'variation_id' => $variationId,
                        'parent_product_id' => $product['id']
                    ]);
                    return $product;
                }
            }

            logger()->warning('Parent product not found for variation', ['variation_id' => $variationId]);
            return null;
        } catch (\Exception $e) {
            logger()->error('Error finding parent product for variation', [
                'variation_id' => $variationId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    private function fetchCompleteVariations($productId, $variationIds)
    {
        $variationsForDisplay = [];
        $variationsForStorage = [];

        try {
            // جلب تفاصيل كل متغير
            foreach ($variationIds as $variationId) {
                $variation = $this->wooService->getProductsById($variationId);

                if ($variation) {
                    // إضافة product_id للمتغير
                    $variation['product_id'] = $productId;

                    // تحضير للعرض (مع اسم محسن)
                    $displayVariation = [
                        'id' => $variation['id'],
                        'name' => $this->generateVariationName($variation),
                        'price' => $variation['price'] ?? 0,
                        'sku' => $variation['sku'] ?? '',
                        'images' => $variation['images'] ?? [],
                        'attributes' => $variation['attributes'] ?? [],
                        'stock_status' => $variation['stock_status'] ?? 'instock',
                        'stock_quantity' => $variation['stock_quantity'] ?? 0,
                        'type' => 'variation',
                        'product_id' => $productId
                    ];

                    $variationsForDisplay[] = $displayVariation;
                    $variationsForStorage[] = $variation; // البيانات الكاملة للتخزين
                }
            }

            logger()->info('Fetched complete variations', [
                'product_id' => $productId,
                'variations_count' => count($variationsForDisplay)
            ]);

        } catch (\Exception $e) {
            logger()->error('Error fetching complete variations', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }

        return [
            'variations_full' => $variationsForDisplay,
            'for_storage' => $variationsForStorage
        ];
    }

    private function generateVariationName($variation)
    {
        $baseName = $variation['name'] ?? 'منتج متغير';

        if (empty($variation['attributes'])) {
            return $baseName;
        }

        $attributeParts = [];
        foreach ($variation['attributes'] as $attribute) {
            if (!empty($attribute['option'])) {
                $attributeParts[] = $attribute['option'];
            }
        }

        if (!empty($attributeParts)) {
            return $baseName . ' - ' . implode(', ', $attributeParts);
        }

        return $baseName;
    }

    /**
     * ✅ دالة البحث عن المنتج الأب بناءً على المتغير (Variation)
     */
    private function searchInVariationsAPI($searchTerm)
    {
        try {
            // جلب المنتجات المتغيرة
            $variableProducts = $this->wooService->getProducts([
                'type' => 'variable',
                'per_page' => 50,
                'status' => 'publish'
            ]);

            $products = isset($variableProducts['data']) ? $variableProducts['data'] : $variableProducts;

            foreach ($products as $product) {
                if (!empty($product['variations'])) {
                    // جلب تفاصيل المتغيرات
                    $variations = $this->wooService->getProductVariations($product['id']);

                    foreach ($variations as $variation) {
                        // فحص SKU أو ID للمتغير
                        $skuMatch = !empty($variation['sku']) && strcasecmp($variation['sku'], $searchTerm) === 0;
                        $idMatch = ctype_digit($searchTerm) && $variation['id'] == (int)$searchTerm;

                        if ($skuMatch || $idMatch) {
                            logger()->info('Variation found in search', [
                                'parent_product_id' => $product['id'],
                                'variation_id' => $variation['id'],
                                'variation_sku' => $variation['sku'] ?? 'no_sku'
                            ]);

                            return [
                                'parent_product' => $product,
                                'found_variation' => $variation
                            ];
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('Error searching in variations via API', [
                'term' => $searchTerm,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    #[On('fetch-products-from-api')]
    public function fetchProductsFromAPI()
    {
        $products = $this->wooService->getProducts(['per_page' => 15])['data'];
        $allProducts = [];
        foreach ($products as $product) {
            $allProducts[] = $product;

            if ($product['type'] === 'variable' && !empty($product['variations'])) {
                // اجلب تفاصيل كل variation
                foreach ($product['variations'] as $variationId) {
                    $variation = $this->wooService->getProduct($variationId);

                    if ($variation) {
                        // ضع علاقة للمنتج الأب إن أردت تتبعها لاحقًا
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
        try {
            $orderData = $order ?? [];

            logger()->info('=== ORDER SUBMISSION START ===', [
                'order_data' => $orderData,
                'user_id' => auth()->id()
            ]);

            // التحقق من البيانات الأساسية
            if (empty($orderData['customer_id'])) {
                throw new \Exception('معرف العميل مطلوب');
            }

            if (empty($orderData['line_items']) || !is_array($orderData['line_items'])) {
                throw new \Exception('يجب إضافة منتجات للطلب');
            }

            // ✅ التحقق من صحة معرف العميل
            $customerId = (int) $orderData['customer_id'];
            logger()->info('Checking customer ID', ['customer_id' => $customerId]);

            try {
                // محاولة جلب بيانات العميل من WooCommerce
                $customer = $this->wooService->getCustomerById($customerId);

                if (!$customer || !isset($customer['id'])) {
                    logger()->warning('Customer not found in WooCommerce, creating guest order', [
                        'attempted_customer_id' => $customerId
                    ]);

                    // إنشاء طلب كضيف بدلاً من رفض الطلب
                    $orderData = $this->createGuestOrder($orderData);
                } else {
                    logger()->info('Customer found, adding billing data', [
                        'customer_id' => $customer['id'],
                        'customer_email' => $customer['email'] ?? 'no_email'
                    ]);

                    // إضافة بيانات العميل الموجود
                    $orderData['billing'] = [
                        'first_name' => $customer['first_name'] ?? 'عميل',
                        'last_name' => $customer['last_name'] ?? 'POS',
                        'email' => $customer['email'] ?? 'pos@example.com',
                        'phone' => $customer['billing']['phone'] ?? '',
                        'address_1' => $customer['billing']['address_1'] ?? '',
                        'city' => $customer['billing']['city'] ?? '',
                        'state' => $customer['billing']['state'] ?? '',
                        'postcode' => $customer['billing']['postcode'] ?? '',
                        'country' => $customer['billing']['country'] ?? 'PS',
                    ];

                    $orderData['shipping'] = [
                        'first_name' => $customer['shipping']['first_name'] ?? $customer['first_name'] ?? 'عميل',
                        'last_name' => $customer['shipping']['last_name'] ?? $customer['last_name'] ?? 'POS',
                        'address_1' => $customer['shipping']['address_1'] ?? $customer['billing']['address_1'] ?? '',
                        'city' => $customer['shipping']['city'] ?? $customer['billing']['city'] ?? '',
                        'state' => $customer['shipping']['state'] ?? $customer['billing']['state'] ?? '',
                        'postcode' => $customer['shipping']['postcode'] ?? $customer['billing']['postcode'] ?? '',
                        'country' => $customer['shipping']['country'] ?? $customer['billing']['country'] ?? 'PS',
                    ];
                }

            } catch (\Exception $e) {
                logger()->warning('Failed to fetch customer, creating guest order', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);

                // إنشاء طلب كضيف
                $orderData = $this->createGuestOrder($orderData);
            }

            // إعداد بيانات الطلب الأساسية
            $orderData['payment_method'] = $orderData['payment_method'] ?? 'cod';
            $orderData['payment_method_title'] = $orderData['payment_method_title'] ?? 'الدفع عند الاستلام';
            $orderData['set_paid'] = $orderData['set_paid'] ?? false;
            $orderData['created_via'] = 'pos';
            $orderData['status'] = $orderData['status'] ?? 'processing';

            // إضافة metadata
            $orderData['meta_data'] = array_merge($orderData['meta_data'] ?? [], [
                ['key' => '_pos_order', 'value' => 'true'],
                ['key' => '_order_source', 'value' => 'POS System'],
                ['key' => '_pos_user_id', 'value' => auth()->id()],
                ['key' => '_pos_timestamp', 'value' => now()->toISOString()],
            ]);

            // تنظيف بيانات المنتجات
            foreach ($orderData['line_items'] as &$item) {
                $item['quantity'] = max(1, intval($item['quantity'] ?? 1));

                if (empty($item['price'])) {
                    $item['price'] = 0;
                }
            }

            logger()->info('Final order data prepared', [
                'has_customer_id' => isset($orderData['customer_id']),
                'customer_id' => $orderData['customer_id'] ?? 'guest',
                'line_items_count' => count($orderData['line_items']),
                'has_billing' => isset($orderData['billing']),
                'has_shipping' => isset($orderData['shipping'])
            ]);

            // إرسال الطلب إلى WooCommerce
            logger()->info('Sending order to WooCommerce API...');

            $createdOrder = $this->wooService->createOrder($orderData);

            if (!$createdOrder || !isset($createdOrder['id'])) {
                logger()->error('WooCommerce returned invalid response', [
                    'response' => $createdOrder
                ]);
                throw new \Exception('فشل في إنشاء الطلب في WooCommerce');
            }

            logger()->info('Order created successfully', [
                'order_id' => $createdOrder['id'],
                'order_number' => $createdOrder['number'] ?? $createdOrder['id'],
                'status' => $createdOrder['status']
            ]);

            // تحديث المخزون
            foreach ($orderData['line_items'] as $item) {
                try {
                    Inventory::create([
                        'store_id' => 1,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'type' => InventoryType::OUTPUT,
                        'user_id' => auth()->id(),
                        'notes' => 'POS Order #' . $createdOrder['id'],
                        'created_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    logger()->error('Failed to create inventory record', [
                        'product_id' => $item['product_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // إرسال استجابة النجاح
            $this->dispatch('order-success', [
                'order' => $createdOrder,
                'message' => 'تم إنشاء الطلب بنجاح',
                'order_id' => $createdOrder['id'],
                'order_number' => $createdOrder['number'] ?? $createdOrder['id']
            ]);

            return $createdOrder;

        } catch (\Exception $e) {
            logger()->error('Order creation failed', [
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'stack_trace' => $e->getTraceAsString(),
                'order_data' => $orderData ?? null
            ]);

            $this->dispatch('order-failed', [
                'message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ]);

            return null;
        }
    }

    private function createGuestOrder(array $orderData): array
    {
        logger()->info('Creating guest order');

        // إزالة معرف العميل غير الصالح
        unset($orderData['customer_id']);

        // إضافة بيانات ضيف افتراضية
        $orderData['billing'] = [
            'first_name' => 'عميل',
            'last_name' => 'POS',
            'email' => 'guest-' . time() . '@pos.local',
            'phone' => '',
            'address_1' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => 'PS',
        ];

        $orderData['shipping'] = $orderData['billing'];

        // إضافة معلومة أنه طلب ضيف
        $orderData['meta_data'] = array_merge($orderData['meta_data'] ?? [], [
            ['key' => '_pos_guest_order', 'value' => 'true'],
            ['key' => '_original_customer_id', 'value' => $orderData['customer_id'] ?? 'unknown']
        ]);

        logger()->info('Guest order prepared', [
            'billing_email' => $orderData['billing']['email']
        ]);

        return $orderData;
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

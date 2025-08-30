<?php
// ================================================
// FILENAME: Index.php
// الوصف: يحتوي على منطق Livewire لإدارة نقطة البيع - نسخة قاعدة البيانات
// ================================================

namespace App\Livewire\Pages\Pos;

use App\Enums\InventoryType;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Store;
use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

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

    public function mount()
    {
        $this->categories = []; // إزالة الفئات مؤقتاً

        // جلب جميع المنتجات النشطة بدلاً من تحديد عدد معين
        $this->products = Product::where('status', 'active')->get()->toArray();
    }

    public function boot(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    public function selectCategory(?int $id = null)
    {
        $this->selectedCategory = $id;

        // تعطيل تصفية الفئات مؤقتاً - عرض جميع المنتجات
        $this->products = Product::where('status', 'active')->get()->toArray();
    }

    public function syncProductsToIndexedDB()
    {
        // جلب جميع المنتجات النشطة للتخزين المحلي
        $products = Product::where('status', 'active')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'stock_quantity' => $product->stock_quantity,
                    'stock_status' => $product->stock_status,
                    'manage_stock' => $product->manage_stock,
                    'sku' => $product->sku,
                    'type' => $product->type,
                    'status' => $product->status,
                    'remote_wp_id' => $product->remote_wp_id,
                    'variations' => $product->variations ? $product->variations->pluck('id')->toArray() : [],
                    'images' => [] // يمكن إضافة الصور إذا كانت متاحة
                ];
            })
            ->toArray();

        return $products;
    }

    public function syncCategoriesToIndexedDB()
    {
        // إزالة الفئات مؤقتاً - إرجاع مصفوفة فارغة
        return [];
    }

    public function updatedSearch()
    {
        $this->products = Product::where('name', 'like', '%' . $this->search . '%')
            ->orWhere('sku', 'like', '%' . $this->search . '%')
            ->orWhere('remote_wp_id', $this->search)
            ->where('status', 'active')
            ->get()
            ->toArray();
    }

    public function openVariationsModal($id, string $type)
    {
        if ($type == 'variable') {
            $this->variations = Product::where('parent_id', $id)
                ->where('type', 'variation')
                ->get()
                ->map(function ($variation) {
                    return [
                        'id' => $variation->id,
                        'name' => $variation->name,
                        'price' => $variation->price,
                        'stock_quantity' => $variation->stock_quantity,
                        'stock_status' => $variation->stock_status,
                        'sku' => $variation->sku,
                        'attributes' => json_decode($variation->attributes ?? '[]', true),
                    ];
                })
                ->toArray();
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

    #[On('search-product-from-api')]
    public function searchProductFromAPI($searchTerm)
    {
        try {
            logger()->info('Searching for product in database', ['term' => $searchTerm]);

            $foundProduct = null;
            $foundVariation = null;
            $specificVariation = null;

            // البحث المباشر عن المتغير أولاً باستخدام remote_wp_id
            $searchResult = Product::where('remote_wp_id', $searchTerm)
                ->orWhere('sku', $searchTerm)
                ->first();

            if ($searchResult && $searchResult->type === 'variation') {
                logger()->info('Variation found directly by remote_wp_id/SKU', ['id' => $searchResult->id]);

                $specificVariation = [
                    'id' => $searchResult->id,
                    'name' => $searchResult->name,
                    'price' => $searchResult->price,
                    'stock_quantity' => $searchResult->stock_quantity,
                    'stock_status' => $searchResult->stock_status,
                    'sku' => $searchResult->sku,
                    'type' => 'variation',
                    'parent_id' => $searchResult->parent_id,
                    'attributes' => json_decode($searchResult->attributes ?? '[]', true)
                ];

                // جلب المنتج الأب مع جميع متغيراته
                if ($searchResult->parent_id) {
                    $foundProduct = Product::find($searchResult->parent_id);
                    if ($foundProduct) {
                        $foundProduct = $this->formatProductForResponseWithAllVariations($foundProduct, $specificVariation);
                        return $this->sendFoundProductWithSpecificVariation($foundProduct, $specificVariation, $searchTerm);
                    }
                }
            }

            // البحث عن المنتج الأب باستخدام remote_wp_id أولاً
            if (!$foundProduct) {
                $foundProduct = Product::where('remote_wp_id', $searchTerm)
                    ->orWhere('sku', $searchTerm)
                    ->orWhere('name', 'like', '%' . $searchTerm . '%')
                    ->where('status', 'active')
                    ->first();

                // إذا وُجد المنتج، جلب جميع متغيراته
                if ($foundProduct) {
                    $foundProduct = $this->formatProductForResponseWithAllVariations($foundProduct);
                }
            }

            // البحث في المتغيرات إذا لم نجد المنتج باستخدام remote_wp_id
            if (!$foundProduct) {
                $variation = Product::where('type', 'variation')
                    ->where(function ($query) use ($searchTerm) {
                        $query->where('remote_wp_id', $searchTerm)
                            ->orWhere('sku', $searchTerm);
                    })
                    ->first();

                if ($variation && $variation->parent_id) {
                    $specificVariation = [
                        'id' => $variation->id,
                        'name' => $variation->name,
                        'price' => $variation->price,
                        'stock_quantity' => $variation->stock_quantity,
                        'stock_status' => $variation->stock_status,
                        'sku' => $variation->sku,
                        'type' => 'variation',
                        'parent_id' => $variation->parent_id,
                        'attributes' => json_decode($variation->attributes ?? '[]', true)
                    ];

                    $foundProduct = Product::find($variation->parent_id);
                    if ($foundProduct) {
                        $foundProduct = $this->formatProductForResponseWithAllVariations($foundProduct, $specificVariation);
                    }
                }
            }

            if ($foundProduct) {
                return $this->sendFoundProductWithSpecificVariation($foundProduct, $specificVariation, $searchTerm);
            } else {
                logger()->info('Product not found in database', ['term' => $searchTerm]);
                $this->dispatch('product-not-found', ['term' => $searchTerm]);
                return null;
            }

        } catch (\Exception $e) {
            logger()->error('Error searching product from database', [
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

    private function formatProductForResponseWithAllVariations($product, $specificVariation = null)
    {
        $formattedProduct = [
            'id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'stock_quantity' => $product->stock_quantity,
            'stock_status' => $product->stock_status,
            'manage_stock' => $product->manage_stock,
            'sku' => $product->sku,
            'type' => $product->type,
            'status' => $product->status,
            'remote_wp_id' => $product->remote_wp_id,
            'images' => []
        ];

        // جلب جميع المتغيرات بغض النظر عن نوع المنتج
        $variations = Product::where('parent_id', $product->id)
            ->where('type', 'variation')
            ->get();

        if ($variations->count() > 0) {
            $formattedProduct['variations'] = $variations->pluck('id')->toArray();
            $formattedProduct['variations_full'] = $variations->map(function ($variation) {
                return [
                    'id' => $variation->id,
                    'name' => $variation->name,
                    'price' => $variation->price,
                    'stock_quantity' => $variation->stock_quantity,
                    'stock_status' => $variation->stock_status,
                    'sku' => $variation->sku,
                    'type' => 'variation',
                    'product_id' => $variation->parent_id,
                    'attributes' => json_decode($variation->attributes ?? '[]', true)
                ];
            })->toArray();

            // تحديث نوع المنتج إلى variable إذا كان له متغيرات
            $formattedProduct['type'] = 'variable';

            // إضافة المتغير المحدد إذا وجد
            if ($specificVariation) {
                $formattedProduct['target_variation'] = $specificVariation;
            }
        }

        return $formattedProduct;
    }

    private function sendFoundProductWithSpecificVariation($foundProduct, $specificVariation, $searchTerm)
    {
        try {
            // إرسال المنتج للـ JavaScript
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

    #[On('fetch-products-from-api')]
    public function fetchProductsFromAPI()
    {
        // جلب جميع المنتجات النشطة بدلاً من تحديد عدد معين
        $products = Product::where('status', 'active')->get();

        $allProducts = [];

        foreach ($products as $product) {
            $formattedProduct = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
                'stock_status' => $product->stock_status,
                'manage_stock' => $product->manage_stock,
                'sku' => $product->sku,
                'type' => $product->type,
                'status' => $product->status,
                'remote_wp_id' => $product->remote_wp_id,
                'images' => []
            ];

            $allProducts[] = $formattedProduct;

            // إضافة المتغيرات إذا كان المنتج متغير
            if ($product->type === 'variable') {
                $variations = Product::where('parent_id', $product->id)
                    ->where('type', 'variation')
                    ->get();

                foreach ($variations as $variation) {
                    $formattedVariation = [
                        'id' => $variation->id,
                        'name' => $variation->name,
                        'price' => $variation->price,
                        'stock_quantity' => $variation->stock_quantity,
                        'stock_status' => $variation->stock_status,
                        'sku' => $variation->sku,
                        'type' => 'variation',
                        'product_id' => $product->id,
                        'parent_id' => $product->id,
                        'attributes' => json_decode($variation->attributes ?? '[]', true)
                    ];

                    $allProducts[] = $formattedVariation;
                }
            }
        }

        $this->dispatch('store-products', products: $allProducts);
    }

    #[On('fetch-categories-from-api')]
    public function fetchCategoriesFromAPI()
    {
        // إزالة الفئات مؤقتاً - إرسال مصفوفة فارغة
        $this->dispatch('store-categories', categories: []);
    }

    #[On('fetch-all-variations')]
    public function fetchAllVariations()
    {
        $variableProducts = Product::where('type', 'variable')
            ->where('status', 'active')
            ->with('variations')
            ->get();

        foreach ($variableProducts as $product) {
            $variations = $product->variations->map(function ($variation) use ($product) {
                return [
                    'id' => $variation->id,
                    'name' => $variation->name,
                    'price' => $variation->price,
                    'stock_quantity' => $variation->stock_quantity,
                    'stock_status' => $variation->stock_status,
                    'sku' => $variation->sku,
                    'type' => 'variation',
                    'product_id' => $product->id,
                    'parent_id' => $product->id,
                    'attributes' => json_decode($variation->attributes ?? '[]', true)
                ];
            })->toArray();

            // إرسال إلى JavaScript لتخزينهم
            $this->dispatch('store-variations', [
                'product_id' => $product->id,
                'variations' => $variations,
            ]);
        }
    }

    #[On('fetch-customers-from-api')]
    public function fetchCustomersFromAPI()
    {
        try {
            // جلب العملاء من WooCommerce API
            if (!$this->wooService) {
                logger()->warning('WooCommerce service not available');
                $this->dispatch('store-customers', customers: []);
                return;
            }

            // جلب جميع العملاء من WooCommerce
            $customers = $this->wooService->getAllCustomers();

            // تأكد من أنها مصفوفة
            if (!is_array($customers)) {
                $customers = [];
            }

            // تنسيق بيانات العملاء
            $formattedCustomers = [];
            foreach ($customers as $customer) {
                if (isset($customer['id']) && !empty($customer['first_name'] . $customer['last_name'])) {
                    $formattedCustomers[] = [
                        'id' => $customer['id'],
                        'first_name' => $customer['first_name'] ?? '',
                        'last_name' => $customer['last_name'] ?? '',
                        'email' => $customer['email'] ?? '',
                        'phone' => $customer['billing']['phone'] ?? '',
                        'billing' => [
                            'phone' => $customer['billing']['phone'] ?? '',
                            'address_1' => $customer['billing']['address_1'] ?? '',
                            'city' => $customer['billing']['city'] ?? '',
                            'state' => $customer['billing']['state'] ?? '',
                            'postcode' => $customer['billing']['postcode'] ?? '',
                            'country' => $customer['billing']['country'] ?? 'PS'
                        ],
                        'shipping' => [
                            'first_name' => $customer['shipping']['first_name'] ?? $customer['first_name'] ?? '',
                            'last_name' => $customer['shipping']['last_name'] ?? $customer['last_name'] ?? '',
                            'address_1' => $customer['shipping']['address_1'] ?? $customer['billing']['address_1'] ?? '',
                            'city' => $customer['shipping']['city'] ?? $customer['billing']['city'] ?? '',
                            'state' => $customer['shipping']['state'] ?? $customer['billing']['state'] ?? '',
                            'postcode' => $customer['shipping']['postcode'] ?? $customer['billing']['postcode'] ?? '',
                            'country' => $customer['shipping']['country'] ?? $customer['billing']['country'] ?? 'PS'
                        ]
                    ];
                }
            }

            logger()->info('Fetched customers from WooCommerce', [
                'total_customers' => count($formattedCustomers)
            ]);

            $this->dispatch('store-customers', customers: $formattedCustomers);

        } catch (\Throwable $e) {
            logger()->error('Failed to fetch customers from WooCommerce', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->dispatch('store-customers', customers: []);
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
        try {
            $orderData = $order ?? [];

            logger()->info('=== ORDER SUBMISSION START ===', [
                'order_data' => $orderData,
                'user_id' => auth()->id()
            ]);

            // التحقق من البيانات الأساسية
            if (empty($orderData['customer_id']) && empty($orderData['guest_info'])) {
                throw new \Exception('معرف العميل أو بيانات الضيف مطلوبة');
            }

            if (empty($orderData['line_items']) || !is_array($orderData['line_items'])) {
                throw new \Exception('يجب إضافة منتجات للطلب');
            }

            DB::beginTransaction();

            // إنشاء الطلب
            $orderRecord = DB::table('orders')->insertGetId([
                'customer_id' => $orderData['customer_id'] ?? null,
                'status' => $orderData['status'] ?? 'processing',
                'total' => $this->calculateOrderTotal($orderData['line_items'], $orderData['shipping_lines'] ?? []),
                'payment_method' => $orderData['payment_method'] ?? 'cod',
                'payment_method_title' => $orderData['payment_method_title'] ?? 'الدفع عند الاستلام',
                'customer_note' => $orderData['customer_note'] ?? '',
                'created_via' => 'pos',
                'user_id' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // إضافة عناصر الطلب
            foreach ($orderData['line_items'] as $item) {
                DB::table('order_items')->insert([
                    'order_id' => $orderRecord,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'total' => $item['price'] * $item['quantity'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // تحديث المخزون
                $product = Product::find($item['product_id']);
                if ($product && $product->manage_stock) {
                    $newStock = max(0, $product->stock_quantity - $item['quantity']);
                    $product->update([
                        'stock_quantity' => $newStock,
                        'stock_status' => $newStock > 0 ? 'instock' : 'outofstock'
                    ]);
                }

                // إنشاء سجل المخزون
                Inventory::create([
                    'store_id' => 1,
                    'product_id' => $item['product_id'],
                    'quantity' => -$item['quantity'], // سالب للإشارة للخروج
                    'type' => InventoryType::OUTPUT,
                    'user_id' => auth()->id(),
                    'notes' => 'POS Order #' . $orderRecord,
                ]);
            }

            // إضافة معلومات الشحن
            if (!empty($orderData['shipping_lines'])) {
                foreach ($orderData['shipping_lines'] as $shipping) {
                    DB::table('order_shipping')->insert([
                        'order_id' => $orderRecord,
                        'method_id' => $shipping['method_id'],
                        'method_title' => $shipping['method_title'],
                        'total' => $shipping['total'],
                        'created_at' => now()
                    ]);
                }
            }

            // إضافة البيانات الإضافية
            if (!empty($orderData['meta_data'])) {
                foreach ($orderData['meta_data'] as $meta) {
                    DB::table('order_meta')->insert([
                        'order_id' => $orderRecord,
                        'meta_key' => $meta['key'],
                        'meta_value' => $meta['value'],
                        'created_at' => now()
                    ]);
                }
            }

            DB::commit();

            $createdOrder = [
                'id' => $orderRecord,
                'number' => $orderRecord,
                'status' => $orderData['status'] ?? 'processing',
                'total' => $this->calculateOrderTotal($orderData['line_items'], $orderData['shipping_lines'] ?? [])
            ];

            logger()->info('Order created successfully', [
                'order_id' => $orderRecord,
                'status' => $createdOrder['status']
            ]);

            // إرسال استجابة النجاح
            $this->dispatch('order-success', [
                'order' => $createdOrder,
                'message' => 'تم إنشاء الطلب بنجاح',
                'order_id' => $orderRecord,
                'order_number' => $orderRecord
            ]);

            return $createdOrder;

        } catch (\Exception $e) {
            DB::rollBack();

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

    private function calculateOrderTotal($lineItems, $shippingLines = [])
    {
        $subtotal = 0;
        foreach ($lineItems as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $shippingTotal = 0;
        foreach ($shippingLines as $shipping) {
            $shippingTotal += floatval($shipping['total']);
        }

        return $subtotal + $shippingTotal;
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

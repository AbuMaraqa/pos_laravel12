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

    public int $perPage = 100;     // حجم الدُفعة
    public int $throttleMs = 500;  // تأخير بين الصفحات (مللي ثانية)
    private bool $isFetching = false; // حارس لمنع الجلب المتوازي
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

        // اعرض أول صفحة فقط لسرعة الواجهة
        $this->products = $this->wooService->getProducts([
            'per_page' => $this->perPage,
            'page'     => 1,
        ]);
    }

    public function selectCategory(?int $id = null)
    {
        $this->selectedCategory = $id;

        $params = [
            'per_page' => $this->perPage,
            'page'     => 1,
        ];
        if ($id !== null) {
            $params['category'] = $id;
        }

        // اعرض أول صفحة فقط
        $this->products = $this->wooService->getProducts($params);

        // ثم اجلب بقية الصفحات بالخلفية عبر نفس الفنكشن (بدون تغيير اسم)
        $this->fetchProductsFromAPI();
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
        $this->products = $this->wooService->getProducts([
            'per_page' => $this->perPage,
            'page'     => 1,
            'search'   => $this->search,
            ...($this->selectedCategory ? ['category' => $this->selectedCategory] : []),
        ]);

        // بقية النتائج على دفعات
        $this->fetchProductsFromAPI();
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
        if ($this->isFetching) {
            return; // منع الجلب المتكرر بالتوازي
        }
        $this->isFetching = true;

        $page = 1;
        $maxPages = 1000; // أمان؛ لو ما رجع إجمالي صفحات من API

        // بارامترات أساسية حسب الحالة الحالية (بدون تغيير على API)
        $baseParams = [
            'per_page' => $this->perPage,
        ];
        if (!empty($this->search)) {
            $baseParams['search'] = $this->search;
        }
        if (!empty($this->selectedCategory)) {
            $baseParams['category'] = $this->selectedCategory;
        }

        try {
            while (true) {
                $params = $baseParams + ['page' => $page];

                // نفس دالة الـ API الموجودة عندك
                $chunk = $this->wooService->getProducts($params);

                // دعم الشكلين (بعض الخدمات ترجع ['data'=>[]] وبعضها ترجع [] مباشرة)
                $items = is_array($chunk) && array_key_exists('data', $chunk) ? ($chunk['data'] ?? []) : $chunk;
                $count = is_countable($items) ? count($items) : 0;

                if ($count === 0) {
                    break; // انتهت البيانات
                }

                // مهم: لا نجلب variations هنا. خليها Lazy في openVariationsModal
                // أرسل الدُفعة مباشرة للواجهة لتخزينها محليًا (نفس الحدث ونفس الاسم)
                $this->dispatch('store-products', products: $items);

                // لو الصفحة ممتلئة تمامًا، أكمل؛ غير ذلك توقف
                if ($count < $this->perPage || $page >= $maxPages) {
                    break;
                }

                // تخفيف الضغط على السيرفر
                usleep($this->throttleMs * 1000);
                $page++;
            }
        } catch (\Throwable $e) {
            logger()->error('Chunked fetch failed', ['error' => $e->getMessage()]);
            // بإمكانك إرسال إشعار للواجهة إن رغبت
        } finally {
            $this->isFetching = false;
        }
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

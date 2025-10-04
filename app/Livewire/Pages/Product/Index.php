<?php

namespace App\Livewire\Pages\Product;

use App\Jobs\SyncProduct;
use App\Services\WooCommerceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Isolate;
use Livewire\Component;
use Livewire\Attributes\Url;
use Masmerise\Toaster\Toaster;
use PDF;

#[Isolate]
class Index extends Component
{
    #[Url(as: 'page')]
    public int $page = 1;

    #[Url]
    public string $search = '';

    public $categoryId = null;
    public $categories = [];

    public int $perPage = 10;
    public int $total = 0;

    public $product = [];
    public $variations = [];
    public $quantities = [];

    public $productVariations = [];
    public $roles = [];
    public $variationValues = [];
    public $productData = [];
    public $parentRoleValues = [];

    public $price = 0;
    public $sale_price = 0;
    public $main_price = 0;
    public $main_sale_price = 0;

    public $showVariationTable = false;

    protected WooCommerceService $wooService;

    public $columnPrices = []; // <-- أضف هذه الخاصية الجديدة


    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount(): void
    {
        $response = $this->wooService->getCategories(['parent' => 0]);
//        dd($this->wooService->getProducts());
        $this->categories = $response['data'] ?? $response;
    }

    /**
     * يتم استدعاؤها عند تغيير قيمة البحث
     * تعيد تعيين الصفحة إلى الأولى لعرض نتائج البحث الجديدة
     * يدعم البحث بالاسم والباركود (SKU) والـ ID
     */
    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function resetCategory(): void
    {
        $this->categoryId = null;
        $this->page = 1;
    }

    public function updateShowVariationTable(): void
    {
        $this->showVariationTable = !$this->showVariationTable;
    }

    public function setCategory($categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->page = 1;
    }

    public function openPrintBarcodeModal($productId)
    {
        $product = $this->wooService->getProductsById($productId);
        $this->product = $product;
        $this->quantities = ['main' => 1];
        $this->variations = [];

        foreach ($product['variations'] ?? [] as $variationId) {
            $variation = $this->wooService->getProductsById($variationId);
            $this->variations[] = $variation;
            $this->quantities[$variationId] = 1;
        }

        $this->modal('barcode-product-modal')->show();
    }

    public function printBarcodes()
    {
        $pdf = Pdf::loadView('livewire.pages.product.pdf.index', [
            'product' => $this->product,
            'variations' => $this->variations,
            'quantities' => $this->quantities,
        ], [], [
            'format' => [60, 40]
        ]);

        return response()->streamDownload(function () use ($pdf) {
            $pdf->stream();
        }, 'barcode.pdf');
    }

    #[Computed]
    public function getMrbpRole($productId)
    {
        $result = $this->wooService->getMrbpRoleById($productId);
        return $result;
    }

    public function deleteProduct($productId)
    {
        $this->wooService->deleteProductById($productId);
    }

    public function updateProductFeatured($productId, $featured)
    {
        $this->wooService->updateProductFeatured($productId, $featured);
        Toaster::success('تم تحديث المنتج بنجاح');
    }

    public function syncProduct()
    {
//        $subId = optional(Auth::user())->subscription_id;
//        abort_unless($subId, 403, 'No subscription assigned to the current user.');
//
//
        SyncProduct::dispatch((int) Auth::id());
    }

    public function openListVariationsModal($productId)
    {
        try {
            $product = $this->wooService->getProduct($productId);
            $this->productData = $product;
            $this->main_price = $product['regular_price'];
            $this->main_sale_price = $product['sale_price'];

            $metaData = $product['meta_data'] ?? [];
            $this->showVariationTable = false;
            foreach ($metaData as $meta) {
                if ($meta['key'] == 'mrbp_metabox_user_role_enable') {
                    $this->showVariationTable = ($meta['value'] == 'yes');
                }
            }

            // تهيئة قيم الأدوار للمنتج الأب
            $this->parentRoleValues = [];
            $roles = $this->wooService->getRoles();
            foreach ($roles as $role) {
                if (isset($role['role'])) {
                    $this->parentRoleValues[$role['role']] = ''; // تهيئة بفارغ
                }
            }

            // استخراج أسعار الأدوار المحفوظة (مع فلتر للبيانات التالفة)
            foreach ($metaData as $meta) {
                if ($meta['key'] === 'mrbp_role' && is_array($meta['value'])) {
                    foreach ($meta['value'] as $roleEntry) {
                        if (!is_array($roleEntry)) continue;
                        $roleKey = array_key_first($roleEntry);

                        // ✨ فلتر ذكي لتجاهل أي بيانات محفوظة بشكل خاطئ
                        if ($roleKey && !in_array(strtolower($roleKey), ['id', 'name'])) {
                            $priceValue = $roleEntry['mrbp_regular_price'] ?? null;
                            if ($priceValue !== null) {
                                $this->parentRoleValues[$roleKey] = $priceValue;
                            }
                        }
                    }
                }
            }

            $variations = $this->wooService->getProductVariationsWithRoles($productId);
            $this->productVariations = $variations;
            $this->variationValues = [];
            $this->price = [];

            foreach ($variations as $variationIndex => $variation) {
                $this->price[$variationIndex] = $variation['regular_price'];
                $this->variationValues[$variationIndex] = $variation['role_values'] ?? [];
            }

            $this->modal('list-variations')->show();
        } catch (\Exception $e) {
            logger()->error('Error opening variations modal', ['error' => $e->getMessage()]);
            Toaster::error('حدث خطأ أثناء جلب البيانات: ' . $e->getMessage());
        }
    }

    #[Computed()]
    public function getRoles()
    {
        $roles = $this->wooService->getRoles();
        $this->roles = $roles;
        return $roles;
    }

    public function updateVariationMrbpRole($variationId, $roleKey, $value)
    {
        $this->wooService->updateVariationMrbpRole($variationId, $roleKey, $value);
        Toaster::success('تم تحديث المنتج بنجاح');
    }

    public function updatePrice($value, $key)
    {
        try {
            $this->wooService->updateProductVariation($this->productData['id'], $value, [
                'price' => $key,
                'regular_price' => $key
            ]);
            Toaster::success('تم تحديث المنتج بنجاح');
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'woocommerce_rest_invalid_product_id')) {
                Toaster::error('المنتج غير موجود أو تم حذفه.');
            } else {
                throw $e; // غير هيك ارمي الخطأ
            }
        }
    }


    /**
     * تحديث سعر الدور للمنتج الأساسي
     */
    public function updateProductMrbpRole($roleKey, $value)
    {
        // dd($this->productData);
        try {
            // التحقق من أن معرف المنتج صالح
            if (empty($this->productData['id']) || $this->productData['id'] == 0) {
                // استخدام معرف المنتج من productData إذا كان متاحًا
                if (isset($this->productData['id']) && !empty($this->productData['id'])) {
                    logger()->info('Using product ID from productData', ['productId' => $this->productData['id']]);
                } else {
                    logger()->error('Invalid product ID and no productData available', ['providedId' => $this->productData['id']]);
                    Toaster::error('معرف المنتج غير صالح.');
                    return;
                }
            }

            // تسجيل المعلومات قبل تحديث سعر الدور
            // logger()->info('Updating product role price', [
            //     'productId' => $productId,
            //     'roleKey' => $roleKey,
            //     'value' => $value
            // ]);

            // تحديث سعر الدور للمنتج
            $result = $this->wooService->updateProductRolePrice($this->productData['id'], $roleKey, $value);

            // تحديث القيمة في مصفوفة parentRoleValues
            $this->parentRoleValues[$roleKey] = $value;

            // تسجيل نتيجة التحديث
            logger()->info('Product role price update result', [
                'productId' => $this->productData['id'],
                'roleKey' => $roleKey,
                'success' => $result !== false
            ]);

            // عرض رسالة نجاح للمستخدم
            Toaster::success('تم تحديث سعر الدور بنجاح.');
        } catch (\Exception $e) {
            // تسجيل الخطأ وعرض رسالة للمستخدم
            // logger()->error('Error updating product role price', [
            //     'productId' => $productId,
            //     'roleKey' => $roleKey,
            //     'error' => $e->getMessage(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            Toaster::error('حدث خطأ أثناء تحديث سعر الدور: ' . $e->getMessage());
        }
    }

    public function setAllPricesForRole($roleKey)
    {
        // احصل على السعر من الخاصية الجديدة
        $value = $this->columnPrices[$roleKey] ?? null;

        if (!is_numeric($value)) {
            Toaster::warning('الرجاء إدخال سعر رقمي صالح.');
            return;
        }

        // تحديث أسعار المنتج الأب في الذاكرة
        $this->parentRoleValues[$roleKey] = $value;

        // تحديث قيم المتغيرات في الذاكرة فقط، بدون إرسالها
        foreach ($this->productVariations as $index => $variation) {
            $this->variationValues[$index][$roleKey] = $value;
        }

        Toaster::info('تم تطبيق السعر مؤقتاً. اضغط "حفظ كل التغييرات" لتأكيد.');
    }

    public function updateMainProductPrice()
    {
        $this->wooService->updateMainProductPrice($this->productData['id'], $this->main_price);
        Toaster::success('تم تحديث سعر المنتج بنجاح');
    }

    // Index.php

// ✨ الدالة الأساسية الجديدة لحفظ كل التغييرات دفعة واحدة
    public function saveAllChanges()
    {
        try {
            $updatePayload = [];

            // 1. تجميع تحديثات المتغيرات (أسعار عادية وأسعار أدوار)
            foreach ($this->productVariations as $index => $variation) {
                $variationId = $variation['id'];
                $metaData = $variation['meta_data'] ?? [];

                $newRoleValuesForVariation = $this->variationValues[$index] ?? [];
                $mrbpRoleFound = false;

                foreach ($metaData as &$meta) {
                    if ($meta['key'] === 'mrbp_role') {
                        $mrbpRoleFound = true;
                        $updatedRoles = [];
                        foreach ($newRoleValuesForVariation as $roleKey => $price) {
                            if (is_numeric($price) && $price !== '') {
                                $updatedRoles[] = [
                                    $roleKey => ucfirst($roleKey),
                                    'mrbp_regular_price' => $price, 'mrbp_sale_price' => '', 'mrbp_make_empty_price' => ""
                                ];
                            }
                        }
                        $meta['value'] = $updatedRoles;
                        break;
                    }
                }

                if (!$mrbpRoleFound) {
                    $updatedRoles = [];
                    foreach ($newRoleValuesForVariation as $roleKey => $price) {
                        if (is_numeric($price) && $price !== '') {
                            $updatedRoles[] = [
                                $roleKey => ucfirst($roleKey),
                                'mrbp_regular_price' => $price, 'mrbp_sale_price' => '', 'mrbp_make_empty_price' => ""
                            ];
                        }
                    }
                    if (!empty($updatedRoles)) {
                        $metaData[] = ['key' => 'mrbp_role', 'value' => $updatedRoles];
                    }
                }

                $updatePayload[] = [
                    'id' => $variationId,
                    'regular_price' => $this->price[$index] ?? $variation['regular_price'],
                    'meta_data' => $metaData
                ];
            }

            if (!empty($updatePayload)) {
                $this->wooService->batchUpdateVariations($this->productData['id'], ['update' => $updatePayload]);
            }

            // 2. تحديث بيانات المنتج الرئيسي
            $this->wooService->updateMainProductPrice($this->productData['id'], $this->main_price);
            $this->wooService->updateMainSalePrice($this->productData['id'], $this->main_sale_price);
            foreach($this->parentRoleValues as $roleKey => $value) {
                $this->wooService->updateProductRolePrice($this->productData['id'], $roleKey, $value);
            }

            Toaster::success('🎉 تم حفظ جميع التغييرات بنجاح!');
            $this->modal('list-variations')->close();

        } catch (\Exception $e) {
            logger()->error('Error saving all variation changes', ['error' => $e->getMessage()]);
            Toaster::error('حدث خطأ فادح أثناء الحفظ: ' . $e->getMessage());
        }
    }

    public function updateMainSalePrice()
    {
        $this->wooService->updateMainSalePrice($this->productData['id'], $this->main_sale_price);
        Toaster::success('تم تحديث سعر المنتج بنجاح');
    }

    public function updateMrbpMetaboxUserRoleEnable()
    {
        $yes = $this->showVariationTable ? 'yes' : 'no';
        $this->wooService->updateMrbpMetaboxUserRoleEnable($this->productData['id'], $yes);
        Toaster::success('تم تحديث سعر المنتج بنجاح');
    }

    public function updateProductStatus($productId, $status)
    {
        $status = $status == 'publish' ? 'publish' : 'draft';

        // 1. غير حالة المنتج الأساسي
        $this->wooService->updateProductStatus($productId, $status);

        // 2. جيب الترجمات المرتبطة
        // $translations = $this->wooService->getProductTranslations($productId);

        // if (!empty($translations)) {
        //     foreach ($translations as $lang => $translatedProductId) {
        //         if ($translatedProductId != $productId) { // تأكد أنه مش هو نفس المنتج
        //             $this->wooService->updateProductStatus($translatedProductId, $status);
        //         }
        //     }
        // }

        Toaster::success('تم تحديث حالة المنتج وجميع الترجمات بنجاح');
    }

    public function render()
    {
        $query = [
            'per_page' => $this->perPage,
            'page' => $this->page,
            'status' => 'any', // يبحث في كل الحالات (منشور، مسودة، ..)
            'lang' => app()->getLocale(), // لجلب المنتجات باللغة الحالية
        ];

        // إذا كان هناك نص في مربع البحث
        if (!empty(trim($this->search))) {
            // نستخدم معامل 'search' الذي يوفره ووكومرس للبحث السريع في اسم المنتج وغيره
            $query['search'] = trim($this->search);
        }
        // إذا لم يكن هناك بحث، ولكن تم اختيار تصنيف معين
        elseif ($this->categoryId) {
            $query['category'] = $this->categoryId;
        }

        // نقوم بإرسال طلب واحد فقط وسريع للـ API
        $response = $this->wooService->getProducts($query);

        $collection = collect($response['data'] ?? $response);
        $total = $response['total'] ?? $collection->count();

        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $collection,
            $total,
            $this->perPage,
            $this->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.pages.product.index', [
            'products' => $products,
        ]);
    }
    /**
     * البحث في متغيرات المنتجات وإرجاع المنتج الأب
     */
    private function searchInVariations(string $searchTerm): ?array
    {
        try {
            // جلب المنتجات المتغيرة
            $variableProducts = $this->wooService->getProducts([
                'type' => 'variable',
                'per_page' => 50,
                'status' => 'any'
            ]);

            $products = $variableProducts['data'] ?? $variableProducts;

            foreach ($products as $product) {
                if (!empty($product['variations'])) {
                    // البحث في متغيرات هذا المنتج
                    $variations = $this->wooService->getProductVariations($product['id']);

                    foreach ($variations as $variation) {
                        // فحص SKU للمتغير
                        if (!empty($variation['sku']) && strcasecmp($variation['sku'], $searchTerm) === 0) {
                            return $product; // إرجاع المنتج الأب
                        }

                        // فحص ID للمتغير
                        if (is_numeric($searchTerm) && $variation['id'] == (int)$searchTerm) {
                            return $product;
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            logger()->error('Error searching in variations', [
                'searchTerm' => $searchTerm,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}

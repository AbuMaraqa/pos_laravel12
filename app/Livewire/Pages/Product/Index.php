<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount(): void
    {
        $response = $this->wooService->getCategories(['parent' => 0]);
        $this->categories = $response['data'] ?? []; // 🔥 المهم
    }

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
            'format' => [80, 30]
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

    public function openListVariationsModal($productId)
    {
        try {
            // جلب بيانات المنتج الأساسي
            $product = $this->wooService->getProduct($productId);

            $this->price = $product['regular_price'];
            $this->sale_price = $product['sale_price'];
            $this->main_price = $product['regular_price'];
            $this->main_sale_price = $product['sale_price'];

            foreach ($product['meta_data'] as $meta) {
                if ($meta['key'] == 'mrbp_metabox_user_role_enable') {
                    $this->showVariationTable = $meta['value'] == 'yes';
                }
            }
            // تسجيل البيانات المستلمة من API للتصحيح
            logger()->info('Product data from API', [
                'productId' => $productId,
                'hasId' => isset($product['id']),
                'hasMetaData' => isset($product['meta_data'])
            ]);

            // التأكد من أن المنتج موجود وله معرف
            if (!isset($product['id'])) {
                logger()->error('Product data missing id', ['productId' => $productId]);
                $this->productData = ['name' => 'المنتج الأساسي', 'id' => $productId];
            } else {
                // استخدام معرف المنتج المرسل كمعلمة وليس المعرف من البيانات
                $product['id'] = $productId;
                $this->productData = $product;
            }

            // تهيئة قيم أدوار المنتج الأساسي
            $this->parentRoleValues = [];

            // الحصول على قائمة الأدوار المتاحة
            $roles = $this->wooService->getRoles();

            // تهيئة قيم فارغة لكل الأدوار
            foreach ($roles as $role) {
                if (isset($role['role'])) {
                    $this->parentRoleValues[$role['role']] = '';
                }
            }

            // استخراج قيم الأدوار من meta_data الخاصة بالمنتج الأساسي
            if (isset($product['meta_data']) && is_array($product['meta_data'])) {
                foreach ($product['meta_data'] as $meta) {
                    if ($meta['key'] === 'mrbp_role' && is_array($meta['value'])) {
                        foreach ($meta['value'] as $roleEntry) {
                            $roleKey = array_key_first($roleEntry);

                            // التنسيق القديم - قيم داخل قوسين إضافيين
                            if ($roleKey && isset($roleEntry[$roleKey]) && isset($roleEntry[$roleKey]['mrbp_regular_price'])) {
                                $this->parentRoleValues[$roleKey] = $roleEntry[$roleKey]['mrbp_regular_price'];
                            }
                            // التنسيق الجديد - القيم مباشرة
                            else if ($roleKey && isset($roleEntry['mrbp_regular_price'])) {
                                $this->parentRoleValues[$roleKey] = $roleEntry['mrbp_regular_price'];
                            }
                        }
                    }
                }
            }

            // تسجيل قيم الأدوار المستخرجة للتصحيح
            logger()->info('Extracted role values for parent product', [
                'productId' => $productId,
                'parentRoleValues' => $this->parentRoleValues
            ]);

            // استخدام الدالة المحسّنة لجلب جميع المتغيرات مع قيمها مرة واحدة
            $variations = $this->wooService->getProductVariationsWithRoles($productId);
            $this->productVariations = $variations;

            // تهيئة مصفوفة لتخزين قيم كل متغير
            $this->variationValues = [];

            $this->price = [];

            // استخراج قيم roles مباشرة من المتغيرات
            foreach ($variations as $variationIndex => $variation) {

                $this->price[$variationIndex] = $variation['regular_price'];
                $this->variationValues[$variationIndex] = $variation['role_values'] ?? [];
            }

            // عرض النافذة المنبثقة
            $this->modal('list-variations')->show();
        } catch (\Exception $e) {
            logger()->error('Error opening variations modal', [
                'productId' => $productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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

    public function updateMainProductPrice()
    {
        $this->wooService->updateMainProductPrice($this->productData['id'], $this->main_price);
        Toaster::success('تم تحديث سعر المنتج بنجاح');
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
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'lang' => app()->getLocale(), // اللغة النشطة
            'status' => 'any', // خليه 'any' عادي، بس اللغة بتحدد
            'wpml_language' => app()->getLocale(), // مهمة جداً
        ];

        if ($this->categoryId) {
            $query['category'] = $this->categoryId;
        }

        $response = $this->wooService->getProducts($query);
        $collection = collect($response['data'] ?? $response);
        $total = $response['total'] ?? 1000;

        $products = new LengthAwarePaginator(
            $collection,
            $total,
            $this->perPage,
            $this->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.pages.product.index', [
            'products' => $products,
            'categories' => $this->categories,
        ]);
    }
}

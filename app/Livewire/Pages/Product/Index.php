<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Attributes\Url;
use Masmerise\Toaster\Toaster;

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
        $pdf = \PDF::loadView('livewire.pages.product.pdf.index', [
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

    // #[Computed]
    // public function getMrbpRole($productId){
    //     $result = $this->wooService->getMrbpRoleById($productId);

    //     // التحقق من نوع البيانات المرجعة وتحويلها إلى نص إذا كانت مصفوفة
    //     if (is_array($result)) {
    //         return isset($result['error']) ? 'خطأ: ' . $result['error'] : 'مصفوفة غير محددة';
    //     }

    //     // إذا كانت القيمة فارغة
    //     if (is_null($result)) {
    //         return 'غير محدد';
    //     }

    //     return (string) $result;
    // }


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

            // التأكد من أن المنتج موجود وله معرف
            if (!isset($product['id'])) {
                logger()->error('Product data missing id', ['productId' => $productId, 'product' => $product]);
                $this->productData = ['name' => 'المنتج الأساسي', 'id' => $productId];
            } else {
                $this->productData = $product;
            }

            // تهيئة قيم أدوار المنتج الأساسي
            $this->parentRoleValues = [];

            // استخراج قيم الأدوار من meta_data الخاصة بالمنتج الأساسي
            if (isset($product['meta_data']) && is_array($product['meta_data'])) {
                foreach ($product['meta_data'] as $meta) {
                    if ($meta['key'] === 'mrbp_role' && is_array($meta['value'])) {
                        foreach ($meta['value'] as $roleEntry) {
                            $roleKey = array_key_first($roleEntry);
                            if ($roleKey) {
                                $this->parentRoleValues[$roleKey] = $roleEntry[$roleKey]['mrbp_regular_price'] ?? '';
                            }
                        }
                    }
                }
            }

            // استخدام الدالة المحسّنة لجلب جميع المتغيرات مع قيمها مرة واحدة
            $variations = $this->wooService->getProductVariationsWithRoles($productId);
            $this->productVariations = $variations;

            // تهيئة مصفوفة لتخزين قيم كل متغير
            $this->variationValues = [];

            // استخراج قيم roles مباشرة من المتغيرات
            foreach ($variations as $variationIndex => $variation) {
                $this->variationValues[$variationIndex] = $variation['role_values'] ?? [];
            }

            // عرض النافذة المنبثقة
            $this->modal('list-variations')->show();
        } catch (\Exception $e) {
            logger()->error('Error opening variations modal', [
                'productId' => $productId,
                'error' => $e->getMessage()
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

    /**
     * تحديث سعر الدور للمنتج الأساسي
     */
    public function updateProductMrbpRole($productId, $roleKey, $value)
    {
        try {
            $this->wooService->updateProductMrbpRole($productId, $roleKey, $value);
            Toaster::success('تم تحديث سعر المنتج بنجاح');
        } catch (\Exception $e) {
            Toaster::error('حدث خطأ: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = [
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
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

<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Livewire;
use Masmerise\Toaster\Toaster;
use Spatie\LivewireFilepond\WithFilePond;
use Illuminate\Support\Facades\Log;

class Add extends Component
{
    use WithFilePond;

    public $productId;

    // بيانات المنتج الأساسية
    public $productName;
    public $productDescription;
    public $productType = 'simple';

    public $regularPrice;
    public $salePrice;
    public $sku;

    // إدارة المخزون
    public $isStockManagementEnabled = false;
    public $stockQuantity = null;
    public $allowBackorders = 'no';
    public $stockStatus = 'instock';
    public $soldIndividually = false;

    // تحميل الصور
    public $file;
    public $files = [];
    public $featuredImage = null;
    public $galleryImages = [];

    // الخصائص والمتغيرات - تبسيط البنية
    public $productAttributes = [];
    public $attributeTerms = [];
    public $selectedAttributes = []; // مصفوفة بسيطة: [attribute_id => [term_ids]]

    #[Locked]
    public $attributeMap = [];
    #[Locked]
    public $variations = [];

    // حالة الحفظ
    public $isSaving = false;
    public array $selectedCategories = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
        $this->fetchProductAttributes();
    }

    public function updated($field, $value)
    {
        if ($field === 'productType') {
            Log::info('Product Type Updated: ' . $value);
            $this->dispatch('productTypeChanged', $value)->to('tabs-component');

            // إعادة تعيين البيانات عند تغيير نوع المنتج
            if ($value !== 'variable') {
                $this->selectedAttributes = [];
                $this->variations = [];
                $this->attributeMap = [];
            }
        }
    }

    #[On('updateMultipleFieldsFromTabs')]
    public function updateFieldsFromTabs($data)
    {
        $this->regularPrice = $data['regularPrice'] ?? $this->regularPrice;
        $this->salePrice = $data['salePrice'] ?? $this->salePrice;
        $this->sku = $data['sku'] ?? $this->sku;
    }

    public function fetchProductAttributes()
    {
        try {
            $this->productAttributes = $this->wooService->getAttributes();

            foreach ($this->productAttributes as $attr) {
                $this->attributeTerms[$attr['id']] = $this->wooService->getTermsForAttribute($attr['id']);
            }
        } catch (\Exception $e) {
            Log::error('خطأ في جلب الخصائص: ' . $e->getMessage());
            $this->productAttributes = [];
            $this->attributeTerms = [];
        }
    }

    #[Computed]
    public function getCategories(): array
    {
        try {
            $categories = $this->wooService->getCategories(['per_page' => 100]);
            $grouped = [];

            foreach ($categories as $cat) {
                $grouped[$cat['parent']][] = $cat;
            }

            $buildTree = function ($parentId = 0) use (&$buildTree, $grouped) {
                $tree = [];
                if (isset($grouped[$parentId])) {
                    foreach ($grouped[$parentId] as $cat) {
                        $cat['children'] = $buildTree($cat['id']);
                        $tree[] = $cat;
                    }
                }
                return $tree;
            };

            return $buildTree();
        } catch (\Exception $e) {
            Log::error('خطأ في جلب التصنيفات: ' . $e->getMessage());
            return [];
        }
    }

    #[On('attributesSelected')]
    public function handleAttributesSelected($data)
    {
        $this->selectedAttributes = $data['selectedAttributes'] ?? [];
        $this->generateVariations();
    }

    #[On('variationsUpdated')]
    public function handleVariationsUpdated($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
    }

    public function generateVariations()
    {
        try {
            // تنظيف البيانات المحددة
            $filteredAttributes = [];
            foreach ($this->selectedAttributes as $attributeId => $termIds) {
                if (is_array($termIds) && !empty($termIds)) {
                    $filteredAttributes[$attributeId] = array_filter($termIds);
                }
            }

            if (empty($filteredAttributes)) {
                $this->variations = [];
                $this->attributeMap = [];
                return;
            }

            // بناء خريطة الخصائص
            $this->attributeMap = [];
            $attributeOptions = [];

            foreach ($filteredAttributes as $attributeId => $termIds) {
                $attribute = collect($this->productAttributes)->firstWhere('id', $attributeId);
                if (!$attribute) continue;

                $terms = $this->attributeTerms[$attributeId] ?? [];
                $termNames = [];

                foreach ($termIds as $termId) {
                    $term = collect($terms)->firstWhere('id', $termId);
                    if ($term) {
                        $termNames[] = $term['name'];
                    }
                }

                if (!empty($termNames)) {
                    $attributeOptions[$attributeId] = $termNames;
                    $this->attributeMap[] = [
                        'id' => $attributeId,
                        'name' => $attribute['name']
                    ];
                }
            }

            // توليد التركيبات
            if (!empty($attributeOptions)) {
                $combinations = $this->cartesian(array_values($attributeOptions));

                $this->variations = array_map(function($combo) {
                    return [
                        'options' => is_array($combo) ? $combo : [$combo],
                        'sku' => '',
                        'regular_price' => '',
                        'sale_price' => '',
                        'stock_quantity' => 0,
                        'manage_stock' => true,
                        'active' => true,
                        'description' => '',
                    ];
                }, $combinations);
            }

            // إرسال البيانات للمكون الفرعي
            $this->dispatch('variationsGenerated', [
                'variations' => $this->variations,
                'attributeMap' => $this->attributeMap
            ])->to('variation-manager');

        } catch (\Exception $e) {
            Log::error('خطأ في توليد المتغيرات: ' . $e->getMessage());
            Toaster::error('حدث خطأ في توليد المتغيرات');
        }
    }

    public function syncBeforeSave()
    {
        if ($this->isSaving) return;

        try {
            $this->validateBasicFields();

            $this->isSaving = true;

            if ($this->productType === 'variable') {
                // طلب آخر تحديث للمتغيرات
                $this->dispatch('requestLatestVariations')->to('variation-manager');
                return; // ننتظر الرد
            }

            $this->saveProduct();

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSaving = false;
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Toaster::error($message);
                }
            }
        } catch (\Exception $e) {
            $this->isSaving = false;
            Log::error('خطأ في التحقق من البيانات: ' . $e->getMessage());
            Toaster::error('حدث خطأ: ' . $e->getMessage());
        }
    }

    private function validateBasicFields()
    {
        $rules = [
            'productName' => 'required|string|min:3',
            'productType' => 'required|in:simple,variable,grouped,external',
            'selectedCategories' => 'required|array|min:1',
        ];

        $messages = [
            'productName.required' => 'اسم المنتج مطلوب',
            'productName.min' => 'يجب أن يكون اسم المنتج 3 أحرف على الأقل',
            'productType.required' => 'نوع المنتج مطلوب',
            'selectedCategories.required' => 'يجب اختيار تصنيف واحد على الأقل',
        ];

        // إضافة قواعد حسب نوع المنتج
        if ($this->productType === 'simple') {
            $rules['regularPrice'] = 'required|numeric|min:0';
            $messages['regularPrice.required'] = 'السعر العادي مطلوب للمنتج البسيط';
        }

        $this->validate($rules, $messages);
    }

    #[On('latestVariationsSent')]
    public function handleLatestVariations($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];

        // التحقق من صحة المتغيرات
        if ($this->productType === 'variable' && empty($this->variations)) {
            $this->isSaving = false;
            Toaster::error('يجب إنشاء متغيرات للمنتج المتعدد');
            return;
        }

        $this->saveProduct();
    }

    public function saveProduct()
    {
        try {
            Log::info('بدء حفظ المنتج', [
                'product_type' => $this->productType,
                'variations_count' => count($this->variations)
            ]);

            // إعداد بيانات المنتج الأساسية
            $productData = $this->prepareProductData();

            // إنشاء المنتج
            $product = $this->wooService->post('products', $productData);
            $this->productId = $product['id'];

            Log::info('تم إنشاء المنتج بنجاح', ['product_id' => $this->productId]);

            // إنشاء المتغيرات للمنتجات المتعددة
            if ($this->productType === 'variable' && !empty($this->variations)) {
                $this->createVariations($this->productId);
            }

            $this->isSaving = false;
            Toaster::success('✅ تم حفظ المنتج بنجاح');
            $this->redirectRoute('product.index');

        } catch (\Exception $e) {
            $this->isSaving = false;
            Log::error('فشل في حفظ المنتج: ' . $e->getMessage());
            Toaster::error('❌ حدث خطأ في حفظ المنتج: ' . $e->getMessage());
        }
    }

    private function prepareProductData(): array
    {
        $data = [
            'name' => $this->productName,
            'type' => $this->productType,
            'description' => $this->productDescription ?? '',
            'sku' => $this->sku ?: null,
            'status' => 'publish',
            'manage_stock' => $this->isStockManagementEnabled,
            'stock_quantity' => $this->stockQuantity ?? null,
            'backorders' => $this->allowBackorders,
            'stock_status' => $this->stockStatus,
            'sold_individually' => $this->soldIndividually,
            'categories' => array_map(fn($id) => ['id' => $id], $this->selectedCategories),
        ];

        // إضافة الأسعار للمنتجات البسيطة
        if (in_array($this->productType, ['simple', 'external'])) {
            $data['regular_price'] = $this->regularPrice;
            $data['sale_price'] = $this->salePrice ?: '';
        }

        // إعداد خصائص المنتجات المتعددة
        if ($this->productType === 'variable' && !empty($this->attributeMap)) {
            $data['attributes'] = $this->prepareProductAttributes();
        }

        // إضافة الصور
        $images = $this->prepareProductImages();
        if (!empty($images)) {
            $data['images'] = $images;
        }

        return $data;
    }

    private function prepareProductAttributes(): array
    {
        $productAttributes = [];

        foreach ($this->attributeMap as $index => $attribute) {
            $options = collect($this->variations)
                ->pluck("options.{$index}")
                ->unique()
                ->values()
                ->filter()
                ->toArray();

            if (!empty($options)) {
                $productAttributes[] = [
                    'id' => $attribute['id'],
                    'variation' => true,
                    'visible' => true,
                    'options' => $options,
                ];
            }
        }

        return $productAttributes;
    }

    private function prepareProductImages(): array
    {
        $images = [];
        $position = 0;

        // الصورة الرئيسية
        if ($this->featuredImage) {
            $images[] = [
                'src' => $this->featuredImage,
                'position' => $position++
            ];
        } elseif ($this->file) {
            $uploadedImage = $this->uploadSingleImageSync();
            if ($uploadedImage) {
                $images[] = [
                    'id' => $uploadedImage['id'],
                    'src' => $uploadedImage['src'],
                    'position' => $position++
                ];
            }
        }

        // صور المعرض
        foreach ($this->galleryImages as $imageSrc) {
            $images[] = [
                'src' => $imageSrc,
                'position' => $position++
            ];
        }

        // رفع صور إضافية
        if (!empty($this->files)) {
            foreach ($this->files as $file) {
                $uploadedImage = $this->wooService->uploadImage($file);
                if (isset($uploadedImage['id'])) {
                    $images[] = [
                        'id' => $uploadedImage['id'],
                        'src' => $uploadedImage['src'],
                        'position' => $position++
                    ];
                }
            }
        }

        return $images;
    }

    private function createVariations($productId)
    {
        $successCount = 0;
        $errorCount = 0;

        foreach ($this->variations as $index => $variation) {
            try {
                $variationData = $this->prepareVariationData($variation, $index);

                if (empty($variationData['attributes'])) {
                    Log::warning('تجاهل المتغير بدون خصائص', ['index' => $index]);
                    continue;
                }

                $response = $this->wooService->post("products/{$productId}/variations", $variationData);

                Log::info('تم إنشاء المتغير', [
                    'variation_id' => $response['id'],
                    'index' => $index
                ]);

                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('فشل إنشاء المتغير', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('انتهاء إنشاء المتغيرات', [
            'success' => $successCount,
            'errors' => $errorCount
        ]);

        if ($errorCount > 0) {
            Toaster::warning("تم إنشاء {$successCount} متغير بنجاح و {$errorCount} فشل");
        }
    }

    private function prepareVariationData($variation, $index): array
    {
        $attributes = [];

        foreach ($variation['options'] as $optIndex => $value) {
            if ($optIndex >= count($this->attributeMap)) continue;

            $attribute = $this->attributeMap[$optIndex];
            if (!empty($value) && isset($attribute['id'])) {
                $attributes[] = [
                    'id' => $attribute['id'],
                    'option' => $value,
                ];
            }
        }

        $variationData = [
            'regular_price' => !empty($variation['regular_price']) ? (string)$variation['regular_price'] : '0',
            'sale_price' => !empty($variation['sale_price']) ? (string)$variation['sale_price'] : '',
            'stock_quantity' => isset($variation['stock_quantity']) ? (int)$variation['stock_quantity'] : 0,
            'manage_stock' => $variation['manage_stock'] ?? true,
            'status' => 'publish',
            'attributes' => $attributes,
        ];

        if (!empty($variation['sku'])) {
            $variationData['sku'] = $variation['sku'];
        }

        if (!empty($variation['description'])) {
            $variationData['description'] = $variation['description'];
        }

        return $variationData;
    }

    protected function cartesian($arrays)
    {
        if (empty($arrays)) return [];
        if (count($arrays) == 1) return array_map(fn($item) => [$item], $arrays[0]);

        $result = [[]];
        foreach ($arrays as $values) {
            $tmp = [];
            foreach ($result as $combo) {
                foreach ($values as $value) {
                    $tmp[] = array_merge($combo, [$value]);
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    // دوال رفع الصور
    public function updatedFile()
    {
        if ($this->file) {
            $this->uploadSingleImage();
        }
    }

    public function updatedFiles()
    {
        if (!empty($this->files)) {
            $this->uploadGalleryImages();
        }
    }

    public function uploadSingleImage()
    {
        try {
            if (!$this->file->isValid()) {
                throw new \Exception('الملف غير صالح');
            }

            $uploadedImage = $this->wooService->uploadImage($this->file);

            if (isset($uploadedImage['src'])) {
                $this->featuredImage = $uploadedImage['src'];
                $this->file = null;
                Toaster::success('تم رفع صورة الغلاف بنجاح');
            }
        } catch (\Exception $e) {
            Log::error('خطأ في رفع الصورة: ' . $e->getMessage());
            Toaster::error('حدث خطأ في رفع الصورة');
        }
    }

    private function uploadSingleImageSync()
    {
        try {
            if ($this->file && $this->file->isValid()) {
                return $this->wooService->uploadImage($this->file);
            }
        } catch (\Exception $e) {
            Log::error('خطأ في رفع الصورة المتزامن: ' . $e->getMessage());
        }
        return null;
    }

    public function uploadGalleryImages()
    {
        try {
            $uploadedCount = 0;
            foreach ($this->files as $file) {
                if (!$file->isValid()) continue;

                $uploadedImage = $this->wooService->uploadImage($file);
                if (isset($uploadedImage['src'])) {
                    $this->galleryImages[] = $uploadedImage['src'];
                    $uploadedCount++;
                }
            }

            $this->files = [];
            if ($uploadedCount > 0) {
                Toaster::success("تم رفع {$uploadedCount} صورة للمعرض بنجاح");
            }
        } catch (\Exception $e) {
            Log::error('خطأ في رفع صور المعرض: ' . $e->getMessage());
            Toaster::error('حدث خطأ في رفع صور المعرض');
        }
    }

    public function removeFeaturedImage()
    {
        $this->file = null;
        $this->featuredImage = null;
    }

    public function removeGalleryImage($index)
    {
        if (isset($this->galleryImages[$index])) {
            unset($this->galleryImages[$index]);
            $this->galleryImages = array_values($this->galleryImages);
        }
    }

    public function render()
    {
        return view('livewire.pages.product.add');
    }
}

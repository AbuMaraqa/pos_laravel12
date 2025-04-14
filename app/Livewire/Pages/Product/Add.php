<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\Livewire;
use Masmerise\Toaster\Toaster;
use Spatie\LivewireFilepond\WithFilePond;

class Add extends Component
{
    use WithFilePond;

    public $productId;                         // ID المنتج الجديد بعد الحفظ

// بيانات المنتج الأساسية
    public $productName;                       // اسم المنتج
    public $productDescription;
    public $productType = 'simple';

    public $regularPrice;
    public $salePrice;                        // سعر الخصم
    public $sku;                              // SKU الخاص بالمنتج

// إدارة المخزون
    public $isStockManagementEnabled = false; // هل يتم إدارة المخزون؟
    public $stockQuantity = null;             // كمية المخزون
    public $allowBackorders = false;          // السماح بالطلبات الخلفية
    public $stockStatus = 'instock';          // حالة التوفر في المخزون
    public $soldIndividually = false;         // هل يباع بشكل فردي فقط؟

// تحميل الصور
    public $file;                             // صورة الغلاف
    public $files = [];                       // صور المعرض
    public $featuredImage = null;             // رابط صورة الغلاف
    public $galleryImages = [];               // روابط صور المعرض

// الخصائص والمتغيرات
    public $productAttributes = [];           // قائمة جميع الخصائص (attributes)
    public $attributeTerms = [];              // قائمة جميع القيم (terms) الخاصة بكل خاصية
    public $selectedAttributes = [];          // الخصائص والقيم التي تم اختيارها من قبل المستخدم
    #[Locked]
    public $attributeMap = [];                // خريطة الخصائص (id + name) حسب الترتيب
    #[Locked]
    public $variations = [];                  // جميع المتغيرات المولدة بناءً على الخصائص المحددة

// حالة الحفظ
    public $isSaving = false;                 // تستخدم لمنع التكرار عند الحفظ

    public array $selectedCategories = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
//        $this->fetchProductAttributes();
    }

    #[On('updateMultipleFieldsFromTabs')]
    public function updateFieldsFromTabs($data)
    {
        $this->regularPrice = $data['regularPrice'] ?? null;
        $this->salePrice = $data['salePrice'] ?? null;
        $this->sku = $data['sku'] ?? null;
    }

    public function fetchProductAttributes()
    {
        $this->productAttributes = $this->wooService->getAttributes();

        foreach ($this->productAttributes as $attr) {
            $this->attributeTerms[$attr['id']] = $this->wooService->getTermsForAttribute($attr['id']);
        }
    }

    public function generateVariations()
    {
        $this->validate([
            'named' => 'required|string|max:255',
        ]);

        $filtered = [];

        foreach ($this->selectedAttributes as $attributeId => $termIds) {
            if (is_array($termIds) && count($termIds)) {
                $filtered[$attributeId] = $termIds;
            }
        }

        $this->selectedAttributes = $filtered;

        $attributeOptions = [];
        $this->attributeMap = []; // تأكد من تصفيرها أولاً

        foreach ($this->selectedAttributes as $attributeId => $termIds) {
            $terms = $this->attributeTerms[$attributeId] ?? [];
            $termNames = [];

            foreach ($termIds as $id) {
                $term = collect($terms)->firstWhere('id', $id);
                if ($term) {
                    $termNames[] = $term['name'];
                }
            }

            if (!empty($termNames)) {
                $attributeOptions[$attributeId] = $termNames;
                $this->attributeMap[] = [
                    'id' => $attributeId,
                    'name' => collect($this->productAttributes)->firstWhere('id', $attributeId)['name'] ?? 'خاصية',
                ];
            }
        }

        $combinations = $this->cartesian(array_values($attributeOptions));
        $this->variations = array_map(fn($combo) => [
            'options' => $combo,
            'sku' => '',
            'regular_price' => '',
            'sale_price' => '',
            'stock_quantity' => '',
            'active' => true,
            'length' => '',
            'width' => '',
            'height' => '',
            'description' => '',
        ], $combinations);
    }

    #[Computed]
    public function getCategories(): array
    {
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
    }

    #[On('variationsGenerated')]
    public function setVariations($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
    }

    #[On('latestVariationsSent')]
    public function handleLatestVariations($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
        $this->selectedAttributes = $data['selectedAttributes'] ?? [];
    }

    public function syncBeforeSave()
    {
        if ($this->isSaving) return;

        try {
            // التحقق من الحقول المشتركة لجميع أنواع المنتجات
            $this->validate([
                'productName' => 'required|string|min:3',
                'productType' => 'required|in:simple,variable,grouped,external',
                'selectedCategories' => 'required|array|min:1',
            ], [
                'productName.required' => 'اسم المنتج مطلوب',
                'productName.min' => 'يجب أن يكون اسم المنتج 3 أحرف على الأقل',
                'productType.required' => 'نوع المنتج مطلوب',
                'productType.in' => 'نوع المنتج غير صالح',
                'selectedCategories.required' => 'يجب اختيار تصنيف واحد على الأقل',
                'selectedCategories.min' => 'يجب اختيار تصنيف واحد على الأقل',
            ]);

            // التحقق حسب نوع المنتج
            switch ($this->productType) {
                case 'simple':
                    $this->validate([
                        'regularPrice' => 'required|numeric|min:0',
                    ], [
                        'regularPrice.required' => 'السعر العادي مطلوب للمنتج البسيط',
                        'regularPrice.numeric' => 'السعر يجب أن يكون رقماً',
                        'regularPrice.min' => 'السعر يجب أن يكون أكبر من أو يساوي صفر',
                    ]);
                    break;

                case 'variable':
                    // التحقق من وجود صفات مختارة
                    $hasSelectedAttributes = false;
                    foreach ($this->selectedAttributes as $attributeId => $terms) {
                        if (is_array($terms)) {
                            foreach ($terms as $isSelected) {
                                if ($isSelected === true) {
                                    $hasSelectedAttributes = true;
                                    break 2;
                                }
                            }
                        }
                    }

                    if (!$hasSelectedAttributes) {
                        throw new \Exception('يجب اختيار صفات للمنتج المتغير');
                    }

                    // التحقق من وجود تباينات
                    if (empty($this->variations)) {
                        throw new \Exception('يجب توليد التباينات للمنتج المتغير');
                    }
                    break;

                case 'grouped':
                    $this->validate([
                        'groupedProducts' => 'required|array|min:1',
                    ], [
                        'groupedProducts.required' => 'يجب إضافة منتجات للمجموعة',
                        'groupedProducts.min' => 'يجب إضافة منتج واحد على الأقل للمجموعة',
                    ]);
                    break;

                case 'external':
                    $this->validate([
                        'regularPrice' => 'required|numeric|min:0',
                        'externalUrl' => 'required|url',
                    ], [
                        'regularPrice.required' => 'السعر العادي مطلوب للمنتج الخارجي',
                        'regularPrice.numeric' => 'السعر يجب أن يكون رقماً',
                        'regularPrice.min' => 'السعر يجب أن يكون أكبر من أو يساوي صفر',
                        'externalUrl.required' => 'رابط المنتج الخارجي مطلوب',
                        'externalUrl.url' => 'يجب إدخال رابط صحيح',
                    ]);
                    break;
            }

            $this->isSaving = true;
            $this->saveProduct();

        } catch (\Exception $e) {
            $this->isSaving = false;
            Toaster::error('❌ ' . $e->getMessage());
        }
    }

    public function saveProduct()
    {
        try {
            $woo = $this->wooService;

            // تجهيز بيانات المنتج الأساسية
            $data = [
                'name' => $this->productName,
                'type' => $this->productType,
                'description' => $this->productDescription ?? '',
                'sku' => $this->sku ?: null,
                'status' => 'publish',
                'manage_stock' => $this->isStockManagementEnabled,
                'stock_quantity' => $this->stockQuantity ?? null,
                'backorders' => $this->allowBackorders ? 'yes' : 'no',
                'stock_status' => $this->stockStatus,
                'sold_individually' => $this->soldIndividually,
                'categories' => array_map(fn($id) => ['id' => $id], $this->selectedCategories),
            ];

            // إضافة السعر للمنتجات البسيطة والخارجية
            if (in_array($this->productType, ['simple', 'external'])) {
                $data['regular_price'] = $this->regularPrice;
                $data['sale_price'] = $this->salePrice ?: '';
            }

            // إعدادات المنتجات المتغيرة
            if ($this->productType === 'variable') {
                $productAttributes = [];
                $defaultAttributes = [];
                $attributeMap = array_values($this->attributeMap);

                foreach ($attributeMap as $index => $attribute) {
                    $options = collect($this->variations)->pluck("options.$index")->unique()->values()->toArray();

                    if (!empty($options)) {
                        $productAttributes[] = [
                            'id' => $attribute['id'],
                            'variation' => true,
                            'visible' => true,
                            'options' => $options,
                        ];

                        $defaultAttributes[] = [
                            'id' => $attribute['id'],
                            'option' => $options[0],
                        ];
                    }
                }

                $data['attributes'] = $productAttributes;
                $data['default_attributes'] = $defaultAttributes;
            }

            // إضافة الصور إذا وجدت
            $images = [];

            // إضافة الصورة الرئيسية
            if ($this->file) {
                logger()->info('Uploading featured image');
                $uploadedImage = $this->wooService->uploadImage($this->file);
                if (isset($uploadedImage['id'])) {
                    $images[] = [
                        'id' => $uploadedImage['id'],
                        'src' => $uploadedImage['src'],
                        'position' => 0
                    ];
                    $this->featuredImage = $uploadedImage['src'];
                }
            }

            // إضافة صور المعرض
            if (!empty($this->files)) {
                logger()->info('Uploading gallery images');
                foreach ($this->files as $index => $file) {
                    $uploadedImage = $this->wooService->uploadImage($file);
                    if (isset($uploadedImage['id'])) {
                        $images[] = [
                            'id' => $uploadedImage['id'],
                            'src' => $uploadedImage['src'],
                            'position' => $index + 1
                        ];
                        $this->galleryImages[] = $uploadedImage['src'];
                    }
                }
            }

            // إضافة الصور إلى بيانات المنتج
            if (!empty($images)) {
                $data['images'] = $images;
            }

            // إنشاء المنتج
            $product = $woo->post('products', $data);
            $this->productId = $product['id'];

            // إنشاء المتغيرات للمنتجات المتغيرة
            if ($this->productType === 'variable') {
                foreach ($this->variations as $variation) {
                    $attributes = [];

                    foreach ($variation['options'] as $index => $value) {
                        $attribute = $attributeMap[$index] ?? null;

                        if ($attribute) {
                            $attributes[] = [
                                'id' => $attribute['id'],
                                'option' => $value,
                            ];
                        }
                    }

                    $variationData = [
                        'sku' => $variation['sku'],
                        'regular_price' => $variation['regular_price'] ?: '0',
                        'sale_price' => $variation['sale_price'] ?: '',
                        'stock_quantity' => $variation['stock_quantity'] ?: 0,
                        'manage_stock' => true,
                        'status' => 'publish',
                        'attributes' => $attributes,
                    ];

                    if (!empty($variation['description'])) {
                        $variationData['description'] = $variation['description'];
                    }

                    $woo->post("products/{$product['id']}/variations", $variationData);
                }
            }

            Toaster::success('✅ تم حفظ المنتج بنجاح');
            $this->redirectRoute('product.index');

        } catch (\Exception $e) {
            $this->isSaving = false;
            Toaster::error('❌ حدث خطأ في حفظ المنتج: ' . $e->getMessage());
        }
    }

    protected function cartesian($arrays)
    {
        if (empty($arrays)) return [];

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

    #[On('validation-failed')]
    public function handleValidationFailed($data)
    {
        $this->isSaving = false;

        if (isset($data['errors']) && is_array($data['errors'])) {
            foreach ($data['errors'] as $field => $errors) {
                if (is_array($errors)) {
                    foreach ($errors as $error) {
                        $this->dispatch('show-toast', [
                            'type' => 'error',
                            'message' => $error
                        ]);
                    }
                } else {
                    $this->dispatch('show-toast', [
                        'type' => 'error',
                        'message' => $errors
                    ]);
                }
            }
        }
    }

    #[On('validation-passed')]
    public function handleValidationPassed()
    {
        try {
            $this->saveProduct();
        } catch (\Exception $e) {
            $this->isSaving = false;
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'حدث خطأ أثناء حفظ المنتج: ' . $e->getMessage()
            ]);
        }
    }

    public function uploadImage()
    {
        try {
            if ($this->file) {
                logger()->info('Starting featured image upload');

                // التحقق من الملف
                if (!$this->file->isValid()) {
                    throw new \Exception('الملف غير صالح: ' . $this->file->getErrorMessage());
                }

                $uploadedImage = $this->wooService->uploadImage($this->file);

                logger()->info('Featured image upload response: ' . json_encode($uploadedImage));

                if (isset($uploadedImage['src'])) {
                    $this->featuredImage = $uploadedImage['src'];
                    $this->file = null;
                    $this->dispatch('show-toast', [
                        'type' => 'success',
                        'message' => 'تم رفع صورة الغلاف بنجاح: ' . $uploadedImage['name']
                    ]);
                } else {
                    throw new \Exception('لم يتم الحصول على رابط الصورة من الخادم');
                }
            }

            if (!empty($this->files)) {
                logger()->info('Starting gallery images upload. Count: ' . count($this->files));

                foreach ($this->files as $index => $file) {
                    if (!$file->isValid()) {
                        logger()->error('Invalid gallery file: ' . $file->getErrorMessage());
                        continue;
                    }

                    $uploadedImage = $this->wooService->uploadMedia($file);
                    logger()->info('Gallery image ' . ($index + 1) . ' upload response: ' . json_encode($uploadedImage));

                    if (isset($uploadedImage['src'])) {
                        $this->galleryImages[] = $uploadedImage['src'];
                    }
                }
                $this->files = [];
                $this->dispatch('show-toast', [
                    'type' => 'success',
                    'message' => 'تم رفع ' . count($this->galleryImages) . ' صورة للمعرض بنجاح'
                ]);
            }
        } catch (\Exception $e) {
            logger()->error('Image upload error: ' . $e->getMessage());
            $this->dispatch('show-toast', [
                'type' => 'error',
                'message' => 'حدث خطأ في رفع الصور: ' . $e->getMessage()
            ]);
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

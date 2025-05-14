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

    public $receivedVariations = [];

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
        }
    }

    public function updatedProductType($value)
    {
        $this->dispatch('productTypeChanged', $value)->to('tabs-component');
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

        // Proceed directly to saving the product
        $this->saveProduct();
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
                    // $this->validate([
                    //     'regularPrice' => 'required|numeric|min:0',
                    // ], [
                    //     'regularPrice.required' => 'السعر العادي مطلوب للمنتج البسيط',
                    //     'regularPrice.numeric' => 'السعر يجب أن يكون رقماً',
                    //     'regularPrice.min' => 'السعر يجب أن يكون أكبر من أو يساوي صفر',
                    // ]);
                    break;

                case 'variable':
                    // طلب آخر تحديث للمتغيرات قبل الحفظ
                    $this->dispatch('requestLatestVariations')->to('variation-manager');

                    return; // ننتظر الرد من مدير المتغيرات
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

            // تسجيل قيم المتغيرات ومعلوماتها للتشخيص
            logger()->info('بيانات المتغيرات قبل الحفظ', [
                'variations_count' => count($this->variations),
                'attributeMap_count' => count($this->attributeMap),
                'first_variation' => $this->variations[0] ?? 'لا يوجد',
                'attributeMap' => $this->attributeMap
            ]);

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
                    // استخدام try-catch لمنع الأخطاء في حالة وجود مشكلة في الوصول إلى البيانات
                    try {
                        $options = collect($this->variations)->pluck("options.$index")->unique()->values()->filter()->toArray();

                        // إضافة الخاصية فقط إذا كانت تحتوي على خيارات
                        if (!empty($options)) {
                            $productAttributes[] = [
                                'id' => $attribute['id'],
                                'variation' => true,
                                'visible' => true,
                                'options' => $options,
                            ];

                            // إضافة الخيار الافتراضي فقط إذا كانت هناك خيارات
                            if (count($options) > 0) {
                                $defaultAttributes[] = [
                                    'id' => $attribute['id'],
                                    'option' => $options[0],
                                ];
                            }
                            ;
                        } else {
                            logger()->warning('تم تجاهل الخاصية لأنها لا تحتوي على خيارات', [
                                'attribute_id' => $attribute['id'],
                                'attribute_name' => $attribute['name'] ?? 'غير معروف'
                            ]);
                        }
                    } catch (\Exception $e) {
                        logger()->error('خطأ في معالجة الخصائص', [
                            'error' => $e->getMessage(),
                            'attribute' => $attribute,
                            'index' => $index
                        ]);
                        continue;
                    }
                }

                // إضافة الخصائص فقط إذا كان هناك خصائص صالحة
                if (!empty($productAttributes)) {
                    $data['attributes'] = $productAttributes;

                    // إضافة الخصائص الافتراضية فقط إذا كان هناك قيم
                    if (!empty($defaultAttributes)) {
                        $data['default_attributes'] = $defaultAttributes;
                    }
                } else {
                    // إذا لم تكن هناك خصائص صالحة، نغير نوع المنتج إلى بسيط
                    logger()->warning('لا توجد خصائص صالحة للمنتج المتغير. تم تغيير نوع المنتج إلى بسيط.');
                    $data['type'] = 'simple';
                    $this->productType = 'simple';
                }
            }

            // إضافة الصور إذا وجدت
            $images = [];

            // إضافة الصورة الرئيسية مباشرة إذا كانت موجودة
            if ($this->featuredImage) {
                logger()->info('Adding featured image: ' . $this->featuredImage);
                $images[] = [
                    'src' => $this->featuredImage,
                    'position' => 0
                ];
            }
            // ثم محاولة رفع الصورة من الملف إذا كان موجوداً
            else if ($this->file) {
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

            // إضافة صور المعرض المحملة سابقاً
            if (!empty($this->galleryImages)) {
                logger()->info('Adding gallery images: ' . count($this->galleryImages));
                foreach ($this->galleryImages as $index => $imageSrc) {
                    $images[] = [
                        'src' => $imageSrc,
                        'position' => $index + 1
                    ];
                }
            }

            // ثم محاولة رفع صور إضافية إذا كانت موجودة
            if (!empty($this->files)) {
                logger()->info('Uploading gallery images');
                $startIndex = count($this->galleryImages); // البدء من بعد آخر صورة موجودة
                foreach ($this->files as $index => $file) {
                    $uploadedImage = $this->wooService->uploadImage($file);
                    if (isset($uploadedImage['id'])) {
                        $images[] = [
                            'id' => $uploadedImage['id'],
                            'src' => $uploadedImage['src'],
                            'position' => $startIndex + $index + 1
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
            if ($this->productType === 'variable' && !empty($this->variations) && !empty($this->attributeMap)) {
                logger()->info('بدء إنشاء المتغيرات', [
                    'variations_count' => count($this->variations)
                ]);

                // إذا تم تغيير نوع المنتج إلى بسيط (بسبب عدم وجود خصائص صالحة)، نتخطى إنشاء المتغيرات
                if ($data['type'] === 'simple') {
                    logger()->info('تم تخطي إنشاء المتغيرات لأن نوع المنتج تغير إلى بسيط');
                } else {
                    foreach ($this->variations as $index => $variation) {
                        try {
                            $attributes = [];
                            $attributeMap = array_values($this->attributeMap);

                            // التحقق من وجود خيارات
                            if (!isset($variation['options']) || !is_array($variation['options']) || empty($variation['options'])) {
                                logger()->warning('خيارات المتغير غير موجودة أو فارغة', [
                                    'variation_index' => $index
                                ]);
                                continue;
                            }

                            // التحقق من أن جميع الخيارات لها قيم
                            $hasEmptyOptions = false;
                            foreach ($variation['options'] as $optValue) {
                                if (empty($optValue)) {
                                    $hasEmptyOptions = true;
                                    break;
                                }
                            }

                            if ($hasEmptyOptions) {
                                logger()->warning('المتغير يحتوي على خيارات فارغة', [
                                    'variation_index' => $index,
                                    'options' => $variation['options']
                                ]);
                                continue;
                            }

                            foreach ($variation['options'] as $optIndex => $value) {
                                if ($optIndex >= count($attributeMap)) {
                                    logger()->warning('مؤشر الخيار تجاوز حجم خريطة الخصائص', [
                                        'option_index' => $optIndex,
                                        'attributeMap_size' => count($attributeMap)
                                    ]);
                                    continue;
                                }

                                $attribute = $attributeMap[$optIndex] ?? null;

                                 if ($attribute && isset($attribute['id'])) {
                                    $attributes[] = [
                                        'id' => $attribute['id'],
                                        'option' => $value,
                                    ];
                                } else {
                                    logger()->warning('بيانات الخاصية غير كاملة', [
                                        'attribute' => $attribute,
                                        'option_index' => $optIndex
                                    ]);
                                }
                            }

                            // تجاهل المتغيرات التي ليس لها خصائص
                            if (empty($attributes)) {
                                logger()->warning('تم تجاهل المتغير لأنه لا يحتوي على خصائص', [
                                    'variation_index' => $index
                                ]);
                                continue;
                            }

                            // التحقق من القيم الرقمية وتعيين قيم افتراضية إذا لزم الأمر
                            $variationData = [
                                'regular_price' => !empty($variation['regular_price']) ? (string)$variation['regular_price'] : '0',
                                'sale_price' => !empty($variation['sale_price']) ? (string)$variation['sale_price'] : '',
                                'stock_quantity' => isset($variation['stock_quantity']) ? (int)$variation['stock_quantity'] : 0,
                                'manage_stock' => true,
                                'status' => 'publish',
                                'attributes' => $attributes,
                            ];

                            // إضافة SKU إذا كان موجوداً
                            if (!empty($variation['sku'])) {
                                $variationData['sku'] = $variation['sku'];
                            }

                            // إضافة الوصف إذا كان موجوداً
                            if (!empty($variation['description'])) {
                                $variationData['description'] = $variation['description'];
                            }

                            logger()->info('إنشاء متغير جديد', [
                                'variation_index' => $index,
                                'attributes_count' => count($attributes)
                            ]);

                            $response = $woo->post("products/{$product['id']}/variations", $variationData);

                            logger()->info('تم إنشاء المتغير بنجاح', [
                                'variation_id' => $response['id'] ?? 'غير معروف'
                            ]);
                        } catch (\Exception $e) {
                            logger()->error('فشل إنشاء المتغير', [
                                'variation_index' => $index,
                                'error' => $e->getMessage()
                            ]);
                            // نستمر في الحلقة لمحاولة إنشاء المتغيرات الأخرى
                        }
                    }
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
            logger()->info('Auto uploading featured image');

            if (!$this->file->isValid()) {
                throw new \Exception('الملف غير صالح: ' . $this->file->getErrorMessage());
            }

            $uploadedImage = $this->wooService->uploadImage($this->file);

            logger()->info('Featured image upload response: ' . json_encode($uploadedImage));

            if (isset($uploadedImage['src'])) {
                $this->featuredImage = $uploadedImage['src'];
                $this->file = null;
                Toaster::success('تم رفع صورة الغلاف بنجاح');
            } else {
                throw new \Exception('لم يتم الحصول على رابط الصورة من الخادم');
            }
        } catch (\Exception $e) {
            logger()->error('Featured image upload error: ' . $e->getMessage());
            Toaster::error('حدث خطأ في رفع الصورة: ' . $e->getMessage());
        }
    }

    public function uploadGalleryImages()
    {
        try {
            logger()->info('Auto uploading gallery images. Count: ' . count($this->files));
            $uploadedCount = 0;

            foreach ($this->files as $index => $file) {
                if (!$file->isValid()) {
                    logger()->error('Invalid gallery file: ' . $file->getErrorMessage());
                    continue;
                }

                $uploadedImage = $this->wooService->uploadImage($file);
                logger()->info('Gallery image ' . ($index + 1) . ' upload response: ' . json_encode($uploadedImage));

                if (isset($uploadedImage['src'])) {
                    $this->galleryImages[] = $uploadedImage['src'];
                    $uploadedCount++;
                }
            }

            $this->files = [];

            if ($uploadedCount > 0) {
                Toaster::success('تم رفع ' . $uploadedCount . ' صورة للمعرض بنجاح');
            }
        } catch (\Exception $e) {
            logger()->error('Gallery images upload error: ' . $e->getMessage());
            Toaster::error('حدث خطأ في رفع صور المعرض: ' . $e->getMessage());
        }
    }

    public function uploadImage()
    {
        try {
            if ($this->file) {
                $this->uploadSingleImage();
            }

            if (!empty($this->files)) {
                $this->uploadGalleryImages();
            }
        } catch (\Exception $e) {
            logger()->error('Image upload error: ' . $e->getMessage());
            Toaster::error('حدث خطأ في رفع الصور: ' . $e->getMessage());
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

    #[On('variationsSentToParent')]
    public function handleVariationsFromChild($data)
    {
        $this->receivedVariations = $data['variations'] ?? [];
    }

    public function render()
    {
        return view('livewire.pages.product.add');
    }
}

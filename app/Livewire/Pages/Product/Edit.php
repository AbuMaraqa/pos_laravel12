<?php

namespace App\Livewire\Pages\Product;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Services\WooCommerceService;
use Illuminate\Support\Facades\Log;
use Masmerise\Toaster\Toaster;
use Spatie\LivewireFilepond\WithFilePond;

class Edit extends Component
{
    use WithFileUploads;
    use WithFilePond;

    public $productId;
    public $productName;
    public $productDescription;
    public $productType = 'simple';
    public $regularPrice;
    public $salePrice;
    public $sku;
    public $stockQuantity;
    public $stockStatus;
    public $soldIndividually;
    public $selectedCategories = [];
    public $featuredImage;
    public $galleryImages = [];
    public $file;
    public $files = [];
    public $variations = [];
    public $attributeMap = [];
    public $selectedAttributes = [];
    public $mrbpData = [];
    public $productAttributes = [];
    public $attributeTerms = [];
    public bool $isStockManagementEnabled;
    public $lowStockThreshold;
    public $allowBackorders;

    // خصائص لتتبع حالة الرفع
    public $uploadingFeaturedImage = false;
    public $uploadingGalleryImages = false;

    // قواعد التحقق للملفات
    protected $rules = [
        'file' => 'nullable|image|max:2048', // 2MB كحد أقصى
        'files.*' => 'nullable|image|max:2048',
    ];

    // خصائص للتحديث الجماعي للمتغيرات
    public $allRegularPrice = '';
    public $allSalePrice = '';
    public $allStockQuantity = '';

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    public function mount($id)
    {
        $this->productId = $id;
        $this->fetchProductAttributes();
        $this->loadProduct();
    }

    public function fetchProductAttributes()
    {
        // جلب كل الخصائص المتاحة
        $this->productAttributes = $this->wooService->getAttributes();

        // تسجيل للتشخيص
        \Illuminate\Support\Facades\Log::info('Fetched all product attributes', [
            'count' => count($this->productAttributes),
            'attributes' => $this->productAttributes
        ]);

        // جلب جميع قيم الخصائص (terms)
        foreach ($this->productAttributes as $attr) {
            $attributeId = $attr['id'];
            $terms = $this->wooService->getTermsForAttribute($attributeId);
            $this->attributeTerms[$attributeId] = $terms;

            \Illuminate\Support\Facades\Log::info('Fetched terms for attribute', [
                'attribute_id' => $attributeId,
                'attribute_name' => $attr['name'],
                'terms_count' => count($terms),
                'terms' => $terms
            ]);
        }
    }

    /**
     * تجهيز متغيرات المنتج بناءً على الخصائص المحددة
     */
    public function generateVariations()
    {
        if ($this->productType !== 'variable') {
            session()->flash('error', 'هذه الوظيفة متاحة فقط للمنتجات المتغيرة');
            return;
        }

        if (empty($this->selectedAttributes)) {
            session()->flash('error', 'يجب تحديد خاصية واحدة على الأقل قبل إنشاء المتغيرات');
            return;
        }

        try {
            Log::info('بدء توليد المتغيرات', [
                'product_id' => $this->productId,
                'selected_attributes' => $this->selectedAttributes
            ]);

            // تصفية الخصائص غير المحددة
            $filtered = [];
            foreach ($this->selectedAttributes as $attributeId => $termIds) {
                if (is_array($termIds) && count($termIds)) {
                    $filtered[$attributeId] = $termIds;
                }
            }
            $this->selectedAttributes = $filtered;

            // تحضير خيارات الخصائص
            $attributeOptions = [];
            $this->attributeMap = []; // تصفير الخريطة

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

            // توليد مجموعات المتغيرات
            $combinations = $this->cartesian(array_values($attributeOptions));
            $existingVariationOptions = collect($this->variations)->map(fn($v) => $v['options'] ?? [])->toArray();

            // تحضير المتغيرات الجديدة مع الاحتفاظ بالبيانات الموجودة
            $newVariations = [];

            foreach ($combinations as $combo) {
                // البحث عن متغير موجود بنفس الخيارات
                $existingVariation = null;
                foreach ($this->variations as $index => $variation) {
                    if (isset($variation['options']) && $this->areOptionsEqual($variation['options'], $combo)) {
                        $existingVariation = $variation;
                        break;
                    }
                }

                if ($existingVariation) {
                    // استخدام المتغير الموجود
                    $newVariations[] = $existingVariation;
                } else {
                    // إنشاء متغير جديد
                    $newVariations[] = [
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
                    ];
                }
            }

            $this->variations = $newVariations;

            Log::info('تم توليد المتغيرات بنجاح', [
                'product_id' => $this->productId,
                'variations_count' => count($this->variations)
            ]);

            session()->flash('success', 'تم توليد ' . count($this->variations) . ' متغير بنجاح');

        } catch (\Exception $e) {
            Log::error('خطأ في توليد المتغيرات', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            session()->flash('error', 'حدث خطأ أثناء توليد المتغيرات: ' . $e->getMessage());
        }
    }

    /**
     * مقارنة خيارات متغيرين
     */
    private function areOptionsEqual($options1, $options2)
    {
        if (count($options1) !== count($options2)) {
            return false;
        }

        foreach ($options1 as $i => $option) {
            if (isset($options2[$i]) && $option !== $options2[$i]) {
                return false;
            }
        }

        return true;
    }

    /**
     * توليد مجموعات المتغيرات (الضرب الديكارتي)
     */
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

    /**
     * تطبيق سعر موحد على جميع المتغيرات
     */
    public function applyBulkPrices()
    {
        if (empty($this->variations)) {
            session()->flash('error', 'لا توجد متغيرات لتطبيق الأسعار عليها');
            return;
        }

        try {
            $updatedCount = 0;

            foreach ($this->variations as $index => $variation) {
                if (!empty($this->allRegularPrice)) {
                    $this->variations[$index]['regular_price'] = $this->allRegularPrice;
                    $updatedCount++;
                }

                if (!empty($this->allSalePrice)) {
                    $this->variations[$index]['sale_price'] = $this->allSalePrice;
                }

                if (!empty($this->allStockQuantity)) {
                    $this->variations[$index]['stock_quantity'] = $this->allStockQuantity;
                }
            }

            Log::info('تم تطبيق التحديث الجماعي على الأسعار', [
                'regular_price' => $this->allRegularPrice,
                'sale_price' => $this->allSalePrice,
                'stock_quantity' => $this->allStockQuantity,
                'updated_variations' => $updatedCount
            ]);

            // إفراغ القيم بعد التطبيق
            $this->allRegularPrice = '';
            $this->allSalePrice = '';
            $this->allStockQuantity = '';

            session()->flash('success', 'تم تطبيق الأسعار على ' . $updatedCount . ' متغير');

        } catch (\Exception $e) {
            Log::error('خطأ في تطبيق الأسعار الجماعية', [
                'error' => $e->getMessage()
            ]);

            session()->flash('error', 'حدث خطأ أثناء تطبيق الأسعار: ' . $e->getMessage());
        }
    }

    protected function loadProduct()
    {
        $product = $this->wooService->getProduct($this->productId);

        if (!$product) {
            session()->flash('error', 'Product not found');
            return redirect()->route('products.index');
        }

        \Illuminate\Support\Facades\Log::info('Product data loaded', [
            'product_id' => $this->productId,
            'product_type' => $product['type'],
            'has_attributes' => !empty($product['attributes']),
            'attributes_count' => count($product['attributes'] ?? []),
            'attributes' => $product['attributes'] ?? []
        ]);

        // بيانات المنتج الأساسية
        $this->productName = $product['name'];
        $this->productDescription = $product['description'];
        $this->productType = $product['type'];
        $this->regularPrice = $product['regular_price'];
        $this->salePrice = $product['sale_price'];
        $this->sku = $product['sku'];

        // إدارة المخزون
        $this->isStockManagementEnabled = $product['manage_stock'] ?? false;
        $this->stockQuantity = $product['stock_quantity'] ?? null;
        $this->stockStatus = $product['stock_status'] ?? null;
        $this->soldIndividually = $product['sold_individually'] ?? false;
        $this->allowBackorders = $product['backorders'] ?? 'no';
        $this->lowStockThreshold = $product['low_stock_amount'] ?? null;

        // التصنيفات
        $this->selectedCategories = collect($product['categories'])->pluck('id')->toArray();

        // الصور
        if (!empty($product['images'])) {
            $this->featuredImage = $product['images'][0]['src'] ?? null;
            $this->galleryImages = collect($product['images'])->slice(1)->pluck('src')->toArray();
        }

        // لو المنتج متغير
        if ($this->productType === 'variable') {
            $this->attributeMap = [];
            $this->selectedAttributes = [];

            // جلب خصائص المنتج الحالية
            $productAttributes = [];
            if (!empty($product['attributes'])) {
                foreach ($product['attributes'] as $attribute) {
                    if (isset($attribute['id'])) {
                        $productAttributes[$attribute['id']] = $attribute;
                    }
                }
            }

            // جلب جميع الخصائص المتاحة في النظام
            $allAttributes = $this->wooService->getAttributes();

            \Illuminate\Support\Facades\Log::info('Processing all available attributes', [
                'total_available' => count($allAttributes),
                'product_has' => count($productAttributes)
            ]);

            // معالجة جميع الخصائص المتاحة (وليس فقط المرتبطة بالمنتج)
            foreach ($allAttributes as $attr) {
                $attributeId = $attr['id'];

                // التأكد من جلب قيم الخاصية (terms)
                if (!isset($this->attributeTerms[$attributeId])) {
                    $this->attributeTerms[$attributeId] = $this->wooService->getTermsForAttribute($attributeId);
                }

                // إضافة الخاصية إلى خريطة الخصائص
                $this->attributeMap[] = [
                    'id' => $attributeId,
                    'name' => $attr['name']
                ];

                // تهيئة مصفوفة القيم المحددة
                $this->selectedAttributes[$attributeId] = [];

                // إذا كانت الخاصية مستخدمة في المنتج، حدد القيم المناسبة
                if (isset($productAttributes[$attributeId]) && !empty($productAttributes[$attributeId]['options'])) {
                    $options = $productAttributes[$attributeId]['options'];
                    foreach ($this->attributeTerms[$attributeId] as $term) {
                        if (in_array($term['name'], $options)) {
                            $this->selectedAttributes[$attributeId][$term['id']] = true;
                        }
                    }
                }
            }

            \Illuminate\Support\Facades\Log::info('Attribute map prepared', [
                'attribute_map' => $this->attributeMap,
                'selected_attributes' => $this->selectedAttributes
            ]);

            // ✅ تحميل المتغيرات
            $existingVariations = $this->wooService->getVariationsByProductId($this->productId);
            $this->variations = [];

            foreach ($existingVariations as $variation) {
                $options = [];

                // ترتيب القيم حسب attributeMap
                foreach ($this->attributeMap as $attr) {
                    $value = null;

                    foreach ($variation['attributes'] as $vAttr) {
                        if (
                            (isset($vAttr['id']) && $vAttr['id'] == $attr['id']) ||
                            (isset($vAttr['name']) && $vAttr['name'] === $attr['name'])
                        ) {
                            $value = $vAttr['option'] ?? null;
                            break;
                        }
                    }

                    $options[] = $value ?? '';
                }

                $this->variations[] = [
                    'id' => $variation['id'] ?? null,
                    'options' => $options,
                    'regular_price' => $variation['regular_price'] ?? '',
                    'sale_price' => $variation['sale_price'] ?? '',
                    'stock_quantity' => $variation['stock_quantity'] ?? '',
                    'description' => $variation['description'] ?? '',
                    'sku' => $variation['sku'] ?? '',
                    'image' => $variation['image']['src'] ?? null,
                ];
            }

            // تسجيل بيانات المتغيرات للتشخيص
            \Illuminate\Support\Facades\Log::info('Loaded product variations', [
                'variations_count' => count($this->variations),
                'variations' => $this->variations
            ]);
        }
        // dd($product);
        // تحميل بيانات MRBP
        $this->loadMrbpData();
    }


    protected function loadMrbpData()
    {
        // Load MRBP data from your custom storage
        $mrbpData = $this->wooService->getMrbpData($this->productId);
        if ($mrbpData) {
            $this->mrbpData = $mrbpData;
        }
    }

    #[On('updateMultipleFieldsFromTabs')]
    public function handleFieldsUpdate($data)
    {
        // تحديث الحقول الأساسية
        $this->regularPrice = $data['regularPrice'] ?? $this->regularPrice;
        $this->salePrice = $data['salePrice'] ?? $this->salePrice;
        $this->sku = $data['sku'] ?? $this->sku;

        // تحديث حقول إدارة المخزون
        $this->isStockManagementEnabled = $data['isStockManagementEnabled'] ?? false;
        $this->stockQuantity = $data['stockQuantity'] ?? $this->stockQuantity;
        $this->stockStatus = $data['stockStatus'] ?? $this->stockStatus;
        $this->soldIndividually = $data['soldIndividually'] ?? $this->soldIndividually;
        $this->allowBackorders = $data['allowBackorders'] ?? $this->allowBackorders;
        $this->lowStockThreshold = $data['lowStockThreshold'] ?? $this->lowStockThreshold;

        // تسجيل البيانات المستلمة للتشخيص
        \Illuminate\Support\Facades\Log::debug('تم استلام بيانات من TabsComponent', [
            'data' => $data,
            'stockStatus' => $this->stockStatus,
            'allowBackorders' => $this->allowBackorders,
            'lowStockThreshold' => $this->lowStockThreshold
        ]);
    }

    #[On('updateMrbpPrice')]
    public function handleMrbpUpdate($data)
    {
        $this->mrbpData = $data['data'];
    }

    #[On('latestVariationsSent')]
    public function handleVariationsUpdate($data)
    {
        try {
            // تسجيل الاستجابة التي تم استلامها
            \Illuminate\Support\Facades\Log::info('Received variations', ['data' => $data]);

            $this->variations = $data['variations'] ?? [];
            $this->attributeMap = $data['attributeMap'] ?? [];
            $this->selectedAttributes = $data['selectedAttributes'] ?? [];

            // بعد تحديث البيانات، نقوم بحفظ المنتج
            $this->save();
        } catch (\Exception $e) {
            session()->flash('error', 'خطأ في handleVariationsUpdate: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Error in handleVariationsUpdate', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    #[On('attributesSelected')]
    public function handleAttributesSelected($data)
    {
        \Illuminate\Support\Facades\Log::info('Received attribute selection', [
            'data' => $data
        ]);

        if (isset($data['selectedAttributes']) && is_array($data['selectedAttributes'])) {
            $this->selectedAttributes = $data['selectedAttributes'];

            // تأكد من أن attributeTerms موجودة للخصائص المحددة
            foreach (array_keys($this->selectedAttributes) as $attributeId) {
                if (!isset($this->attributeTerms[$attributeId]) || empty($this->attributeTerms[$attributeId])) {
                    $this->attributeTerms[$attributeId] = $this->wooService->getTermsForAttribute($attributeId);

                    \Illuminate\Support\Facades\Log::info('Loaded missing terms for attribute', [
                        'attribute_id' => $attributeId,
                        'terms_count' => count($this->attributeTerms[$attributeId])
                    ]);
                }
            }
        }

        if (isset($data['attributeMap']) && is_array($data['attributeMap'])) {
            $this->attributeMap = $data['attributeMap'];

            \Illuminate\Support\Facades\Log::info('Updated attribute map', [
                'attribute_map' => $this->attributeMap
            ]);
        }
    }

    #[On('getProductStockSettings')]
    public function sendStockSettings()
    {
        $data = [
            'isStockManagementEnabled' => $this->isStockManagementEnabled,
            'stockQuantity' => $this->stockQuantity,
            'stockStatus' => $this->stockStatus,
            'soldIndividually' => $this->soldIndividually,
            'allowBackorders' => $this->allowBackorders,
            'lowStockThreshold' => $this->lowStockThreshold,
        ];

        $this->dispatch('updateStockSettings', $data)->to('tabs-component');
    }

    public function syncBeforeSave()
    {
        try {
            if ($this->productType === 'variable') {
                $this->dispatch('requestLatestVariations')->to('variation-manager');
                session()->flash('info', 'طلب تحديث المتغيرات...');
                // لا نقوم بإستدعاء save() هنا، لأن handleVariationsUpdate ستقوم بذلك
            } else {
                $this->save();
            }
        } catch (\Exception $e) {
            session()->flash('error', 'خطأ في syncBeforeSave: ' . $e->getMessage());
            // حفظ التفاصيل الكاملة للخطأ في سجل الأخطاء للتحقق لاحقاً
            \Illuminate\Support\Facades\Log::error('Error in syncBeforeSave', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function prepareAttributes()
    {
        $attributes = [];

        \Illuminate\Support\Facades\Log::info('Preparing attributes for save', [
            'selected_attributes' => $this->selectedAttributes,
            'attribute_map' => $this->attributeMap,
            'attribute_terms' => array_keys($this->attributeTerms)
        ]);

        if (empty($this->selectedAttributes) || empty($this->attributeMap)) {
            return $attributes;
        }

        foreach ($this->attributeMap as $index => $attribute) {
            $attributeId = $attribute['id'];
            $options = [];

            if (isset($this->selectedAttributes[$attributeId])) {
                $selectedTermIds = array_keys(array_filter($this->selectedAttributes[$attributeId]));

                if (!empty($selectedTermIds)) {
                    // تأكد من وجود شروط الخاصية
                    if (!isset($this->attributeTerms[$attributeId]) || empty($this->attributeTerms[$attributeId])) {
                        $this->attributeTerms[$attributeId] = $this->wooService->getTermsForAttribute($attributeId);
                    }

                    $terms = $this->attributeTerms[$attributeId];

                    foreach ($selectedTermIds as $termId) {
                        $term = collect($terms)->firstWhere('id', $termId);
                        if ($term) {
                            $options[] = $term['name'];
                        }
                    }

                    \Illuminate\Support\Facades\Log::info('Processing attribute terms', [
                        'attribute_id' => $attributeId,
                        'attribute_name' => $attribute['name'],
                        'selected_term_ids' => $selectedTermIds,
                        'options_found' => $options
                    ]);
                }
            }

            if (!empty($options)) {
                $attributes[] = [
                    'id' => $attributeId,
                    'name' => $attribute['name'],
                    'position' => $index,
                    'visible' => true,
                    'variation' => true,
                    'options' => $options
                ];
            }
        }

        \Illuminate\Support\Facades\Log::info('Final prepared attributes', [
            'count' => count($attributes),
            'attributes' => $attributes
        ]);

        return $attributes;
    }

    public function save()
    {
        try {
            Log::info('بدء حفظ المنتج', [
                'product_id' => $this->productId,
                'product_type' => $this->productType
            ]);

            // تجهيز بيانات المنتج الأساسية
            $productData = [
                'name' => $this->productName,
                'description' => $this->productDescription,
                'type' => $this->productType,
                'stock_status' => $this->stockStatus,
                'categories' => array_map(fn($id) => ['id' => $id], $this->selectedCategories),
            ];

            // أضف السعر إن وجد
            if (!empty($this->regularPrice)) {
                $productData['regular_price'] = $this->regularPrice;
            }

            if (!empty($this->salePrice)) {
                $productData['sale_price'] = $this->salePrice;
            }

            // أضف الكمية إذا محددة
            if (!is_null($this->stockQuantity)) {
                $productData['stock_quantity'] = (int) $this->stockQuantity;
            }

            // أضف SKU إذا موجود
            if (!empty($this->sku)) {
                $productData['sku'] = $this->sku;
            }

            // أضف الخصائص إذا كان المنتج متغير
            if ($this->productType === 'variable') {
                $attributes = $this->prepareAttributes();

                Log::info('تم تجهيز خصائص المنتج المتغير', [
                    'attributes_count' => count($attributes),
                    'attributes' => $attributes
                ]);

                if (empty($attributes)) {
                    session()->flash('warning', 'لم يتم إضافة أي خصائص للمنتج المتغير. سيتم حفظه كمنتج بسيط.');
                    $this->productType = 'simple';
                    $productData['type'] = 'simple';
                } else {
                    $productData['attributes'] = $attributes;
                }
            }

            else {
                // تحديث هنا لمعالجة قيمة false بشكل صحيح
                $productData['manage_stock'] = (bool)$this->isStockManagementEnabled;

                // تخزين كمية المخزون بغض النظر عن قيمتها
                $productData['stock_quantity'] = $this->stockQuantity;

                // تخزين حالة المخزون بغض النظر عن قيمتها
                $productData['stock_status'] = $this->stockStatus;

                // تخزين القيم الأخرى
                $productData['sold_individually'] = (bool)$this->soldIndividually;

                // تخزين حد المخزون المنخفض
                $productData['low_stock_amount'] = $this->lowStockThreshold;

                // تخزين حالة الطلبات المؤجلة
                $productData['backorders'] = $this->allowBackorders;

                // تسجيل البيانات قبل الإرسال للتشخيص
                \Illuminate\Support\Facades\Log::debug('بيانات المخزون قبل الحفظ', [
                    'manage_stock' => $productData['manage_stock'],
                    'stock_status' => $productData['stock_status'],
                    'backorders' => $productData['backorders'],
                    'low_stock_amount' => $productData['low_stock_amount']
                ]);
            }

            Log::info('بيانات المنتج قبل التحديث', [
                'product_id' => $this->productId,
                'product_data' => $productData
            ]);

            // تحديث المنتج
            $updatedProduct = $this->wooService->updateProduct($this->productId, $productData);


            if (!$updatedProduct) {
                throw new \Exception('فشل تحديث المنتج الأساسي');
            }

            Log::info('تم تحديث المنتج بنجاح', [
                'product_id' => $this->productId
            ]);

            // تحديث تسعيرة MRBP إن وجدت
            if (!empty($this->mrbpData)) {
                try {
                    $this->wooService->updateMrbpData($this->productId, $this->mrbpData);
                    Log::info('تم تحديث بيانات MRBP بنجاح', [
                        'product_id' => $this->productId
                    ]);
                } catch (\Exception $e) {
                    Log::error('خطأ في تحديث بيانات MRBP', [
                        'error' => $e->getMessage()
                    ]);
                    // نكمل عملية الحفظ حتى مع فشل تحديث MRBP
                }
            }

            // تحديث المتغيرات في حال كان المنتج متغير
            if ($this->productType === 'variable' && !empty($this->variations)) {
                try {
                    $syncResult = $this->wooService->syncVariations($this->productId, $this->variations);

                    Log::info('تم مزامنة المتغيرات بنجاح', [
                        'product_id' => $this->productId,
                        'created' => $syncResult['created'] ?? 0,
                        'updated' => $syncResult['updated'] ?? 0
                    ]);
                } catch (\Exception $e) {
                    Log::error('خطأ في مزامنة المتغيرات', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    // إظهار الخطأ ولكن نكمل عملية الحفظ
                    session()->flash('warning', 'تم تعديل المنتج الأساسي، لكن حدث خطأ في تحديث المتغيرات: ' . $e->getMessage());
                }
            }

            Toaster::success('تم تحديث المنتج بنجاح');


            // Toaster::success('تم تعديل المنتج بنجاح');
            session()->flash('success', 'تم تعديل المنتج بنجاح');
            return redirect()->route('product.index');

        } catch (\Exception $e) {
            // تسجيل الخطأ بشكل مفصل
            Log::error('خطأ في تحديث المنتج', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // عرض رسالة خطأ مفصلة للمستخدم
            $error = "فشل تعديل المنتج: " . $e->getMessage();
            session()->flash('error', $error);
        }
    }

    public function removeFeaturedImage()
    {
        $this->featuredImage = null;
        $this->file = null;
    }

    public function removeGalleryImage($index)
    {
        unset($this->galleryImages[$index]);
        $this->galleryImages = array_values($this->galleryImages);
    }

    public function updatedFile()
    {
        if (!$this->file) {
            Log::warning('updatedFile() تم استدعاؤها ولكن $this->file فارغ');
            return;
        }

        // تفعيل مؤشر الرفع
        $this->uploadingFeaturedImage = true;

        try {
            // التحقق من صلاحية الملف
            $this->validate([
                'file' => 'image|max:2048'
            ]);

            Log::info('بدء تحميل الصورة الرئيسية', [
                'product_id' => $this->productId,
                'file_type' => get_class($this->file),
                'file_name' => $this->file->getClientOriginalName()
            ]);

            // التحقق من وجود username و password
            if (!env('WORDPRESS_USERNAME') || !env('WORDPRESS_APPLICATION_PASSWORD')) {
                Log::error('لم يتم تعيين بيانات اعتماد WordPress', [
                    'username_set' => !empty(env('WORDPRESS_USERNAME')),
                    'password_set' => !empty(env('WORDPRESS_APPLICATION_PASSWORD'))
                ]);
                session()->flash('error', 'لم يتم تعيين بيانات الاعتماد اللازمة لرفع الصور');
                return;
            }

            // رفع الصورة إلى ووكومرس
            try {
                $result = $this->wooService->uploadImage($this->file);

                Log::debug('نتيجة رفع الصورة', [
                    'result' => $result
                ]);
            } catch (\Exception $e) {
                Log::error('خطأ أثناء استدعاء uploadImage', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                session()->flash('error', 'فشل رفع الصورة: ' . $e->getMessage());
                return;
            }

            if (!$result || !isset($result['id'])) {
                session()->flash('error', 'فشل رفع الصورة الرئيسية');
                Log::error('فشل رفع الصورة الرئيسية', ['result' => $result]);
                return;
            }

            // جلب المنتج الحالي للحصول على الصور الموجودة
            $product = $this->wooService->getProduct($this->productId);

            if (!$product) {
                session()->flash('error', 'فشل الحصول على معلومات المنتج');
                return;
            }

            // تجهيز مصفوفة الصور - الصورة الجديدة ستكون الرئيسية
            $allImages = [];
            $allImages[] = [
                'id' => $result['id'],
                'src' => $result['src'],
                'name' => $result['name'],
                'alt' => $this->productName
            ];

            // إضافة صور المعرض الحالية إن وجدت
            if (isset($product['images']) && count($product['images']) > 1) {
                // تجاهل الصورة الأولى (الرئيسية) وأخذ بقية الصور
                $galleryImages = array_slice($product['images'], 1);
                foreach ($galleryImages as $image) {
                    $allImages[] = [
                        'id' => $image['id'],
                        'src' => $image['src'],
                        'name' => $image['name'] ?? '',
                        'alt' => $image['alt'] ?? $this->productName
                    ];
                }
            }

            // تحديث المنتج بكل الصور
            $productData = [
                'images' => $allImages
            ];

            Log::info('تحديث المنتج بالصورة الرئيسية الجديدة', [
                'product_id' => $this->productId,
                'images' => count($allImages)
            ]);

            // تحديث المنتج
            try {
                $updated = $this->wooService->updateProduct($this->productId, $productData);

                if ($updated) {
                    $this->featuredImage = $result['src'];

                    // تحديث معرض الصور أيضًا إذا لزم الأمر
                    if (isset($product['images']) && count($product['images']) > 1) {
                        $this->galleryImages = collect(array_slice($product['images'], 1))
                            ->pluck('src')
                            ->toArray();
                    }

                    // تفريغ متغير الملف
                    $this->file = null;

                    session()->flash('success', 'تم رفع وتحديث الصورة الرئيسية بنجاح');

                    Log::info('تم تحديث صورة المنتج بنجاح', [
                        'product_id' => $this->productId,
                        'image_id' => $result['id']
                    ]);
                } else {
                    session()->flash('error', 'تم رفع الصورة ولكن فشل تحديث المنتج');
                    Log::error('فشل تحديث المنتج بالصورة الرئيسية', [
                        'product_id' => $this->productId
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('خطأ أثناء تحديث المنتج بالصورة الجديدة', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                session()->flash('error', 'تم رفع الصورة ولكن حدث خطأ أثناء تحديث المنتج: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            session()->flash('error', 'حدث خطأ أثناء رفع الصورة: ' . $e->getMessage());
            Log::error('خطأ في رفع الصورة الرئيسية', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            // إيقاف مؤشر الرفع بغض النظر عن النتيجة
            $this->uploadingFeaturedImage = false;
        }
    }

    public function updatedFiles()
    {
        if (empty($this->files)) {
            Log::warning('updatedFiles() تم استدعاؤها ولكن $this->files فارغ');
            return;
        }

        // تفعيل مؤشر الرفع
        $this->uploadingGalleryImages = true;

        try {
            // التحقق من صلاحية الملفات
            $this->validate([
                'files.*' => 'image|max:2048'
            ]);

            Log::info('بدء تحميل صور معرض المنتج', [
                'product_id' => $this->productId,
                'files_count' => count($this->files)
            ]);

            // التحقق من وجود username و password
            if (!env('WORDPRESS_USERNAME') || !env('WORDPRESS_APPLICATION_PASSWORD')) {
                Log::error('لم يتم تعيين بيانات اعتماد WordPress', [
                    'username_set' => !empty(env('WORDPRESS_USERNAME')),
                    'password_set' => !empty(env('WORDPRESS_APPLICATION_PASSWORD'))
                ]);
                session()->flash('error', 'لم يتم تعيين بيانات الاعتماد اللازمة لرفع الصور');
                return;
            }

            // جلب المنتج الحالي
            try {
                $product = $this->wooService->getProduct($this->productId);

                if (!$product) {
                    session()->flash('error', 'فشل الحصول على معلومات المنتج');
                    return;
                }
            } catch (\Exception $e) {
                Log::error('خطأ في جلب معلومات المنتج', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                session()->flash('error', 'فشل الحصول على معلومات المنتج: ' . $e->getMessage());
                return;
            }

            // تجهيز مصفوفة الصور مع الاحتفاظ بالصورة الرئيسية
            $allImages = [];

            // إضافة الصورة الرئيسية الحالية (إن وجدت)
            if (isset($product['images']) && !empty($product['images'])) {
                $allImages[] = $product['images'][0];
            }

            // إضافة صور المعرض الحالية
            $existingGalleryImages = [];
            if (isset($product['images']) && count($product['images']) > 1) {
                $existingGalleryImages = array_slice($product['images'], 1);
                foreach ($existingGalleryImages as $image) {
                    $allImages[] = $image;
                }
            }

            // رفع الصور الجديدة واضافتها
            $uploadedImages = [];
            $errorFiles = [];

            foreach ($this->files as $index => $file) {
                try {
                    Log::info('رفع صورة معرض', [
                        'index' => $index,
                        'file_name' => $file->getClientOriginalName()
                    ]);

                    $result = $this->wooService->uploadImage($file);

                    if ($result && isset($result['id'])) {
                        $uploadedImages[] = $result;
                        $allImages[] = [
                            'id' => $result['id'],
                            'src' => $result['src'],
                            'name' => $result['name'],
                            'alt' => $this->productName
                        ];

                        Log::info('تم رفع صورة معرض بنجاح', [
                            'image_id' => $result['id'],
                            'index' => $index
                        ]);
                    } else {
                        $errorFiles[] = $file->getClientOriginalName();
                        Log::error('فشل رفع صورة معرض', [
                            'result' => $result,
                            'index' => $index
                        ]);
                    }
                } catch (\Exception $e) {
                    $errorFiles[] = $file->getClientOriginalName();
                    Log::error('خطأ في رفع صورة معرض', [
                        'error' => $e->getMessage(),
                        'file_name' => $file->getClientOriginalName(),
                        'index' => $index
                    ]);
                }
            }

            if (empty($uploadedImages)) {
                session()->flash('error', 'فشل رفع جميع صور المعرض');
                return;
            }

            // تحديث المنتج بكل الصور
            $productData = [
                'images' => $allImages
            ];

            Log::info('تحديث المنتج بصور المعرض الجديدة', [
                'product_id' => $this->productId,
                'total_images' => count($allImages),
                'new_images' => count($uploadedImages)
            ]);

            // تحديث المنتج
            try {
                $updated = $this->wooService->updateProduct($this->productId, $productData);

                if ($updated) {
                    // تحديث المتغيرات المحلية
                    if (isset($product['images']) && !empty($product['images'])) {
                        $this->featuredImage = $product['images'][0]['src'];
                    }

                    // دمج الصور القديمة مع الجديدة
                    $newGalleryImages = array_merge(
                        collect($existingGalleryImages)->pluck('src')->toArray(),
                        collect($uploadedImages)->pluck('src')->toArray()
                    );

                    $this->galleryImages = $newGalleryImages;
                    $this->files = []; // تفريغ المصفوفة

                    $successMessage = 'تم رفع وتحديث ' . count($uploadedImages) . ' صورة للمعرض بنجاح';

                    if (!empty($errorFiles)) {
                        $successMessage .= '. فشل رفع ' . count($errorFiles) . ' صورة';
                    }

                    session()->flash('success', $successMessage);

                    Log::info('تم تحديث صور معرض المنتج بنجاح', [
                        'product_id' => $this->productId,
                        'total_images' => count($allImages),
                        'uploaded_images' => count($uploadedImages),
                        'failed_images' => count($errorFiles)
                    ]);
                } else {
                    session()->flash('error', 'تم رفع الصور ولكن فشل تحديث المنتج');
                    Log::error('فشل تحديث المنتج بصور المعرض', [
                        'product_id' => $this->productId
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('خطأ أثناء تحديث المنتج بصور المعرض', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                session()->flash('error', 'تم رفع الصور ولكن حدث خطأ أثناء تحديث المنتج: ' . $e->getMessage());
            }
        } catch (\Exception $e) {
            session()->flash('error', 'حدث خطأ أثناء رفع صور المعرض: ' . $e->getMessage());
            Log::error('خطأ في رفع صور المعرض', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            // إيقاف مؤشر الرفع بغض النظر عن النتيجة
            $this->uploadingGalleryImages = false;
        }
    }

    public function updatedProductType($value)
    {
        // تسجيل تغيير نوع المنتج
        Log::info('تم تغيير نوع المنتج', [
            'product_id' => $this->productId,
            'old_type' => $this->productType !== $value ? $this->productType : 'unknown',
            'new_type' => $value
        ]);

        // إرسال حدث تغيير نوع المنتج إلى مكونات أخرى
        $this->dispatch('productTypeChanged', $value)->to('tabs-component');

        // إذا تم التغيير من بسيط إلى متغير، تأكد من وجود خصائص
        if ($value === 'variable' && empty($this->attributeMap)) {
            // إعلام المستخدم بضرورة إضافة خصائص للمنتج المتغير
            session()->flash('info', 'للمنتج المتغير، يجب إضافة خصائص وإنشاء متغيرات');
        }

        // إذا تم التغيير من متغير إلى بسيط، تأكد من تحديث الواجهة
        if ($value === 'simple' && !empty($this->variations)) {
            // إعلام المستخدم بأن المتغيرات لن يتم حفظها
            session()->flash('warning', 'تم التغيير إلى منتج بسيط. لن يتم حفظ المتغيرات.');
            // إفراغ المتغيرات لتجنب التشوش
            $this->variations = [];
            $this->attributeMap = [];
            $this->selectedAttributes = [];
        }
    }

    /**
     * تحويل المنتج البسيط إلى متغير
     */
    public function convertToVariable()
    {
        $this->productType = 'variable';
        $this->updatedProductType('variable');

        // إعادة توجيه المستخدم إلى تبويب الخصائص
        $this->dispatch('switchTab', 'attributes')->to('tabs-component');

        session()->flash('info', 'تم تحويل المنتج إلى متغير. يرجى إضافة الخصائص والمتغيرات.');
    }

    /**
     * تحويل المنتج المتغير إلى بسيط
     */
    public function convertToSimple()
    {
        // تأكيد قبل التحويل
        if (!empty($this->variations)) {
            if (!session()->has('confirm_simple_conversion')) {
                session()->flash('confirm_simple_conversion', true);
                session()->flash('warning', 'تحويل المنتج إلى بسيط سيؤدي إلى فقدان جميع المتغيرات. هل تريد المتابعة؟');
                return;
            }

            session()->forget('confirm_simple_conversion');
        }

        $this->productType = 'simple';
        $this->updatedProductType('simple');

        // إعادة توجيه المستخدم إلى تبويب المعلومات العامة
        $this->dispatch('switchTab', 'general')->to('tabs-component');

        session()->flash('info', 'تم تحويل المنتج إلى بسيط.');
    }

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

    public function render()
    {
        return view('livewire.pages.product.edit');
    }
}

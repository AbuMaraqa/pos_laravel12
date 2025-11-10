<?php

namespace App\Livewire\Pages\Product;

use App\Enums\InventoryType;
use App\Models\Inventory;
use Livewire\Attributes\Computed;
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

    public $brandId;

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

    // حالات التحديث والحفظ
    public $isRefreshing = false;
    public $isSaving = false;

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
    /**
     * جلب جميع الخصائص من WooCommerce مع التحديث
     */
    public function fetchProductAttributes()
    {
        try {
            $this->isRefreshing = true;

            Log::info('بدء جلب الخصائص من WooCommerce');

            // جلب البيانات من API
            $response = $this->wooService->getAttributes();

            // استخراج البيانات
            if (isset($response['data']) && is_array($response['data'])) {
                $this->productAttributes = $response['data'];
            } else {
                $this->productAttributes = $response;
            }

            Log::info('تم جلب الخصائص:', [
                'attributes_count' => count($this->productAttributes)
            ]);

            // جلب المصطلحات لكل خاصية مع إزالة التكرارات
            $this->attributeTerms = [];
            foreach ($this->productAttributes as $attr) {
                if (!isset($attr['id'])) {
                    Log::warning('تجاهل خاصية بدون ID:', $attr);
                    continue;
                }

                try {
                    // استخدام الدالة المحسنة التي تزيل التكرارات
                    $filteredTerms = $this->wooService->getTermsForAttribute($attr['id'], [
                        'per_page' => 100,
                        'orderby' => 'name',
                        'order' => 'asc'
                    ]);

                    $this->attributeTerms[$attr['id']] = $filteredTerms;

                    Log::info("تم جلب مصطلحات الخاصية {$attr['name']} (مفلترة):", [
                        'attribute_id' => $attr['id'],
                        'terms_count' => count($filteredTerms),
                        'sample_terms' => array_slice(array_column($filteredTerms, 'name'), 0, 5)
                    ]);
                } catch (\Exception $e) {
                    Log::error("فشل في جلب مصطلحات الخاصية {$attr['id']}:", [
                        'error' => $e->getMessage()
                    ]);
                    $this->attributeTerms[$attr['id']] = [];
                }
            }

            $this->isRefreshing = false;

            // إذا لم تكن هذه المرة الأولى، أظهر رسالة نجاح
            if (!empty($this->productName)) {
                Toaster::success('تم تحديث الخصائص والمصطلحات بنجاح (بدون تكرارات)');
            }

            Log::info('انتهاء جلب الخصائص بنجاح:', [
                'total_attributes' => count($this->productAttributes),
                'attributes_with_terms' => count($this->attributeTerms)
            ]);

        } catch (\Exception $e) {
            $this->isRefreshing = false;
            Log::error('خطأ في جلب الخصائص: ' . $e->getMessage());
            $this->productAttributes = [];
            $this->attributeTerms = [];

            if (!empty($this->productName)) {
                Toaster::error('حدث خطأ في تحديث الخصائص: ' . $e->getMessage());
            }
        }
    }
    /**
     * تحديث شامل للخصائص والمصطلحات والمتغيرات
     */
    public function refreshAll()
    {
        try {
            Log::info('=== بدء التحديث الشامل ===', [
                'product_id' => $this->productId,
                'product_type' => $this->productType
            ]);

            // 1. تحديث الخصائص والمصطلحات أولاً
            $this->fetchProductAttributes();

            // 2. إعادة تحميل بيانات المنتج مع المتغيرات (بعد تحديث الخصائص)
            $this->loadProduct();

            // 3. إرسال التحديث للمكونات الفرعية
            $this->dispatch('attributesRefreshed', [
                'productAttributes' => $this->productAttributes,
                'attributeTerms' => $this->attributeTerms
            ]);

            // 4. إذا كان المنتج متغير، أرسل التحديث للـ VariationManager
            if ($this->productType === 'variable') {
                $this->dispatch('updateSelectedAttributes', [
                    'selectedAttributes' => $this->selectedAttributes
                ])->to('variation-manager');

                $this->dispatch('variationsGenerated', [
                    'variations' => $this->variations,
                    'attributeMap' => $this->attributeMap
                ])->to('variation-manager');

                Log::info('تم إرسال تحديث المتغيرات:', [
                    'variations_count' => count($this->variations),
                    'attributeMap_count' => count($this->attributeMap),
                    'selectedAttributes' => array_keys($this->selectedAttributes)
                ]);
            }

            Toaster::success('✅ تم التحديث الشامل بنجاح');

            Log::info('=== انتهاء التحديث الشامل بنجاح ===');

        } catch (\Exception $e) {
            Log::error('خطأ في التحديث الشامل: ' . $e->getMessage());
            Toaster::error('❌ فشل التحديث: ' . $e->getMessage());
        }
    }

    /**
     * تحديث خاصية معينة ومصطلحاتها
     */
    public function refreshAttribute($attributeId)
    {
        try {
            Log::info("بدء تحديث الخاصية: {$attributeId}");

            // تحديث مصطلحات الخاصية المحددة مع إزالة التكرارات
            $filteredTerms = $this->wooService->getTermsForAttribute($attributeId, [
                'per_page' => 100,
                'orderby' => 'name',
                'order' => 'asc'
            ]);

            $this->attributeTerms[$attributeId] = $filteredTerms;

            // البحث عن الخاصية في القائمة وتحديثها
            $attributeIndex = collect($this->productAttributes)->search(function ($attr) use ($attributeId) {
                return $attr['id'] == $attributeId;
            });

            if ($attributeIndex !== false) {
                $updatedAttribute = $this->wooService->getAttribute($attributeId);
                if ($updatedAttribute) {
                    $this->productAttributes[$attributeIndex] = $updatedAttribute;
                }
            }

            Toaster::success('تم تحديث الخاصية بنجاح (بدون تكرارات)');

            Log::info("انتهاء تحديث الخاصية {$attributeId}:", [
                'terms_count' => count($filteredTerms),
                'sample_terms' => array_slice(array_column($filteredTerms, 'name'), 0, 5)
            ]);

        } catch (\Exception $e) {
            Log::error("خطأ في تحديث الخاصية {$attributeId}: " . $e->getMessage());
            Toaster::error('فشل تحديث الخاصية');
        }
    }

    /**
     * إعادة تحميل بيانات المنتج من WooCommerce
     */
    public function reloadProduct()
    {
        try {
            $this->isRefreshing = true;

            Log::info('إعادة تحميل بيانات المنتج من WooCommerce:', [
                'product_id' => $this->productId
            ]);

            $this->loadProduct();

            $this->isRefreshing = false;
            Toaster::success('تم تحديث بيانات المنتج بنجاح');

        } catch (\Exception $e) {
            $this->isRefreshing = false;
            Log::error('خطأ في إعادة تحميل المنتج: ' . $e->getMessage());
            Toaster::error('فشل في تحديث بيانات المنتج');
        }
    }

    #[Computed()]
    public function getBrands()
    {
        return $this->wooService->getBrands();
    }

    protected function loadProduct()
    {
        $product = $this->wooService->getProduct($this->productId);

        if (!$product) {
            session()->flash('error', 'Product not found');
            return redirect()->route('products.index');
        }

        // بيانات المنتج الأساسية
        $this->productName = $product['name'];
        $this->productDescription = $product['description'];
        $this->productType = $product['type'];
        $this->regularPrice = $product['regular_price'] ?? '';
        $this->salePrice = $product['sale_price'] ?? '';
        $this->sku = $product['sku'];

        if(!empty($product['brands'])){
            $this->brandId = $product['brands'][0]['id'];
        }

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

        // إذا كان المنتج متغير، حمل البيانات
        if ($this->productType === 'variable') {
            $this->loadVariableProductData($product);
        }

        $this->loadMrbpData();
        $this->syncPriceData();
    }

    protected function loadVariableProductData($product)
    {
        Log::info('=== بدء تحميل بيانات المنتج المتغير ===', [
            'product_attributes' => $product['attributes'] ?? [],
            'system_attributes_count' => count($this->productAttributes)
        ]);

        $this->attributeMap = [];
        $this->selectedAttributes = [];

        // معالجة خصائص المنتج
        if (!empty($product['attributes'])) {
            foreach ($product['attributes'] as $attribute) {
                if (isset($attribute['id'])) {
                    $attributeId = $attribute['id'];

                    Log::info("معالجة الخاصية ID: {$attributeId}");

                    // إيجاد الخاصية في النظام
                    $systemAttribute = collect($this->productAttributes)->firstWhere('id', $attributeId);

                    if ($systemAttribute) {
                        Log::info("تم العثور على الخاصية في النظام:", [
                            'system_attribute' => $systemAttribute
                        ]);

                        $this->attributeMap[] = [
                            'id' => $attributeId,
                            'name' => $systemAttribute['name']
                        ];

                        // تحسين تحديد القيم المحددة
                        $selectedTermIds = [];

                        if (!empty($attribute['options']) && isset($this->attributeTerms[$attributeId])) {
                            Log::info("مقارنة الخيارات:", [
                                'product_options' => $attribute['options'],
                                'available_terms' => $this->attributeTerms[$attributeId]
                            ]);

                            foreach ($this->attributeTerms[$attributeId] as $term) {
                                if (in_array($term['name'], $attribute['options'])) {
                                    $selectedTermIds[] = $term['id'];
                                    Log::info("تم العثور على تطابق:", [
                                        'term_name' => $term['name'],
                                        'term_id' => $term['id']
                                    ]);
                                }
                            }
                        }

                        // حفظ IDs المحددة بطريقة صحيحة للـ UI
                        if (!empty($selectedTermIds)) {
                            // تحويل إلى format checkbox (id => true)
                            $checkboxFormat = [];
                            foreach ($selectedTermIds as $termId) {
                                $checkboxFormat[$termId] = true;
                            }
                            $this->selectedAttributes[$attributeId] = $checkboxFormat;
                        }

                        Log::info("تم حفظ الخاصية:", [
                            'attribute_id' => $attributeId,
                            'selected_terms' => $selectedTermIds,
                            'checkbox_format' => $this->selectedAttributes[$attributeId] ?? []
                        ]);
                    } else {
                        Log::warning("لم يتم العثور على الخاصية في النظام ID: {$attributeId}");
                    }
                }
            }
        }

        // تسجيل للتشخيص
        Log::info('=== انتهاء تحميل بيانات المنتج المتغير ===', [
            'selectedAttributes' => $this->selectedAttributes,
            'attributeMap' => $this->attributeMap
        ]);

        // تحميل المتغيرات
        $this->loadProductVariations();

        // إرسال البيانات لـ VariationManager بعد التحميل
        $this->dispatch('updateSelectedAttributes', [
            'selectedAttributes' => $this->selectedAttributes
        ])->to('variation-manager');
    }

    protected function loadProductVariations()
    {
        try {
            $existingVariations = $this->wooService->getVariationsByProductId($this->productId);
            $this->variations = [];

            Log::info('Loading variations', [
                'product_id' => $this->productId,
                'variations_count' => count($existingVariations),
                'attributeMap' => $this->attributeMap
            ]);

            foreach ($existingVariations as $variation) {
                $options = [];

                // ترتيب القيم حسب attributeMap
                foreach ($this->attributeMap as $attr) {
                    $value = null;
                    $attributeId = $attr['id'];

                    // البحث في attributes المتغير
                    foreach ($variation['attributes'] as $vAttr) {
                        if ((isset($vAttr['id']) && $vAttr['id'] == $attributeId) ||
                            (isset($vAttr['name']) && $vAttr['name'] === $attr['name']) ||
                            (isset($vAttr['name']) && strtolower($vAttr['name']) === strtolower($attr['name']))) {
                            $value = $vAttr['option'] ?? null;
                            break;
                        }
                    }

                    $options[] = $value ?? '';
                }

                // --- تحويل stock_quantity إلى integer أو 0 ---
                $stockQuantity = 0; // الافتراضي 0
                if (isset($variation['stock_quantity']) && is_numeric($variation['stock_quantity'])) {
                    $stockQuantity = (int)$variation['stock_quantity'];
                }

                $variationData = [
                    'id' => $variation['id'] ?? null,
                    'options' => $options,
                    'regular_price' => $variation['regular_price'] ?? '',
                    'sale_price' => $variation['sale_price'] ?? '',
                    'stock_quantity' => $stockQuantity,
                    'description' => $variation['description'] ?? '',
                    'sku' => $variation['sku'] ?? '',
                    'manage_stock' => true, // ✅ تفعيل إدارة المخزون دائماً
                    'active' => true
                ];

                $this->variations[] = $variationData;
            }

            Log::info('All variations loaded with manage_stock=true', [
                'final_variations_count' => count($this->variations),
                'sample_variation' => $this->variations[0] ?? null
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في تحميل المتغيرات', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    protected function loadMrbpData()
    {
        $mrbpData = $this->wooService->getMrbpData($this->productId);
        if ($mrbpData) {
            $this->mrbpData = $mrbpData;
        }
    }

    // إضافة listener للحصول على إعدادات المخزون
    #[On('getProductStockSettings')]
    public function sendStockSettings()
    {
        $this->dispatch('updateStockSettings', [
            'isStockManagementEnabled' => $this->isStockManagementEnabled,
            'stockQuantity' => $this->stockQuantity,
            'stockStatus' => $this->stockStatus,
            'soldIndividually' => $this->soldIndividually,
            'allowBackorders' => $this->allowBackorders,
            'lowStockThreshold' => $this->lowStockThreshold,
        ])->to('tabs-component');
    }

    #[On('updateMultipleFieldsFromTabs')]
    public function handleFieldsUpdate($data)
    {
        $this->regularPrice = $data['regularPrice'] ?? $this->regularPrice;
        $this->salePrice = $data['salePrice'] ?? $this->salePrice;
        $this->sku = $data['sku'] ?? $this->sku;
        $this->isStockManagementEnabled = $data['isStockManagementEnabled'] ?? false;
        $this->stockQuantity = $data['stockQuantity'] ?? $this->stockQuantity;
        $this->stockStatus = $data['stockStatus'] ?? $this->stockStatus;
        $this->soldIndividually = $data['soldIndividually'] ?? $this->soldIndividually;
        $this->allowBackorders = $data['allowBackorders'] ?? $this->allowBackorders;
        $this->lowStockThreshold = $data['lowStockThreshold'] ?? $this->lowStockThreshold;
    }

//    #[On('updateMrbpPrice')]
//    public function handleMrbpUpdate($data)
//    {
//        $this->mrbpData = $data['data'];
//    }

    #[On('variationsUpdated')]
    public function handleVariationsUpdated($data)
    {
        Log::info('=== استلام تحديث المتغيرات ===', [
            'received_data' => array_keys($data)
        ]);

        if (isset($data['variations'])) {
            $this->variations = $data['variations'];
            Log::info('تم تحديث المتغيرات:', [
                'variations_count' => count($this->variations)
            ]);
        }

        if (isset($data['attributeMap'])) {
            $this->attributeMap = $data['attributeMap'];
            Log::info('تم تحديث خريطة الخصائص:', [
                'attributeMap_count' => count($this->attributeMap)
            ]);
        }

        // التأكد من تزامن selectedAttributes مع attributeMap
        $this->syncSelectedAttributesWithMap();
    }

    /**
     * مزامنة selectedAttributes مع attributeMap
     */
    private function syncSelectedAttributesWithMap()
    {
        if (empty($this->attributeMap)) {
            return;
        }

        $syncedAttributes = [];
        foreach ($this->attributeMap as $attr) {
            $attributeId = $attr['id'];
            if (isset($this->selectedAttributes[$attributeId])) {
                $syncedAttributes[$attributeId] = $this->selectedAttributes[$attributeId];
            }
        }

        $this->selectedAttributes = $syncedAttributes;

        Log::info('تم مزامنة الخصائص المحددة:', [
            'synced_attributes' => array_keys($this->selectedAttributes)
        ]);
    }

    #[On('latestVariationsSent')]
    public function handleVariationsUpdate($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
        $this->selectedAttributes = $data['selectedAttributes'] ?? [];
        $this->save();
    }

    #[On('attributesSelected')]
    public function handleAttributesSelected($data)
    {
        Log::info('=== استلام تحديث الخصائص المحددة ===', [
            'received_data' => $data
        ]);

        if (isset($data['selectedAttributes'])) {
            $this->selectedAttributes = $data['selectedAttributes'];

            // تم إزالة استدعاء regenerateVariationsFromAttributes() من هنا
            // لأن VariationManager هو المسؤول عن توليد المتغيرات وإرسالها مباشرة.

            Log::info('تم تحديث الخصائص المحددة:', [
                'selectedAttributes' => $this->selectedAttributes
            ]);
        }
    }

    /**
     * إعادة توليد المتغيرات بناءً على الخصائص المحددة
     * هذه الدالة لم تعد تُستدعى مباشرة من handleAttributesSelected
     * ولكنها موجودة إذا كانت هناك حاجة لاستخدامها داخليًا.
     */
    public function regenerateVariationsFromAttributes()
    {
        try {
            Log::info('=== بدء إعادة توليد المتغيرات ===');

            // تنظيف البيانات المحددة
            $filteredAttributes = [];
            foreach ($this->selectedAttributes as $attributeId => $termData) {
                if (is_array($termData)) {
                    // إذا كانت في format checkbox (id => boolean)
                    if (!empty($termData) && isset(array_values($termData)[0]) && is_bool(array_values($termData)[0])) {
                        $selectedTermIds = array_keys(array_filter($termData));
                    } else {
                        // إذا كانت مصفوفة من IDs
                        $selectedTermIds = array_filter($termData);
                    }

                    if (!empty($selectedTermIds)) {
                        $filteredAttributes[$attributeId] = $selectedTermIds;
                    }
                }
            }

            Log::info('الخصائص المفلترة:', [
                'filteredAttributes' => $filteredAttributes
            ]);

            if (empty($filteredAttributes)) {
                $this->variations = [];
                $this->attributeMap = [];
                Log::info('لا توجد خصائص محددة - مسح المتغيرات');
                return;
            }

            // بناء خريطة الخصائص الجديدة
            $newAttributeMap = [];
            $attributeOptions = [];

            foreach ($filteredAttributes as $attributeId => $termIds) {
                $attribute = collect($this->productAttributes)->firstWhere('id', $attributeId);
                if (!$attribute) {
                    Log::warning("لم يتم العثور على الخاصية: {$attributeId}");
                    continue;
                }

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
                    $newAttributeMap[] = [
                        'id' => $attributeId,
                        'name' => $attribute['name']
                    ];
                }
            }

            // الحفاظ على البيانات الموجودة للمتغيرات عند إعادة التوليد
            $existingVariationsData = [];
            foreach ($this->variations as $variation) {
                if (isset($variation['options'])) {
                    $key = implode('|', $variation['options']);
                    $existingVariationsData[$key] = [
                        'id' => $variation['id'] ?? null,
                        'regular_price' => $variation['regular_price'] ?? '',
                        'sale_price' => $variation['sale_price'] ?? '',
                        'stock_quantity' => $variation['stock_quantity'] ?? '',
                        'sku' => $variation['sku'] ?? '',
                        'description' => $variation['description'] ?? ''
                    ];
                }
            }

            // توليد التركيبات الجديدة
            $newVariations = [];
            if (!empty($attributeOptions)) {
                $combinations = $this->cartesian(array_values($attributeOptions));

                foreach ($combinations as $combo) {
                    $options = is_array($combo) ? $combo : [$combo];
                    $key = implode('|', $options);

                    // استخدام البيانات الموجودة إذا كانت متاحة
                    $existingData = $existingVariationsData[$key] ?? [];

                    $newVariations[] = [
                        'id' => $existingData['id'] ?? null,
                        'options' => $options,
                        'regular_price' => $existingData['regular_price'] ?? '',
                        'sale_price' => $existingData['sale_price'] ?? '',
                        'stock_quantity' => $existingData['stock_quantity'] ?? '',
                        'sku' => $existingData['sku'] ?? '',
                        'description' => $existingData['description'] ?? '',
                        'manage_stock' => true,
                        'active' => true
                    ];
                }
            }

            // تحديث البيانات
            $this->attributeMap = $newAttributeMap;
            $this->variations = $newVariations;

            Log::info('تم إعادة توليد المتغيرات بنجاح:', [
                'attributeMap_count' => count($this->attributeMap),
                'variations_count' => count($this->variations),
                'preserved_data_count' => count($existingVariationsData)
            ]);

            // إرسال التحديث للمكون الفرعي
            $this->dispatch('variationsGenerated', [
                'variations' => $this->variations,
                'attributeMap' => $this->attributeMap
            ])->to('variation-manager');

        } catch (\Exception $e) {
            Log::error('خطأ في إعادة توليد المتغيرات: ' . $e->getMessage());
        }
    }

    /**
     * دالة cartesian للتركيبات
     */
    private function cartesian($arrays)
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

    public function syncBeforeSave()
    {
        if ($this->isSaving) return;

        try {
            $this->isSaving = true;

            if ($this->productType === 'variable') {
                $this->dispatch('requestLatestVariations', ['page' => 'edit'])->to('variation-manager');
            } else {
                $this->save();
            }
        } catch (\Exception $e) {
            $this->isSaving = false;
            Log::error('خطأ في التحقق من البيانات: ' . $e->getMessage());
            Toaster::error('حدث خطأ: ' . $e->getMessage());
        }
    }

    /**
     * تحديث المخزون عند تغيير الكمية
     */
    private function updateInventory($oldQuantity, $newQuantity, $productId)
    {
        try {
            // حساب الفرق
            $difference = $newQuantity - $oldQuantity;

            if ($difference == 0) {
                return; // لا يوجد تغيير
            }

            // تحديد نوع العملية
            $type = $difference > 0 ? InventoryType::INPUT : InventoryType::OUTPUT;
            $quantity = abs($difference);

            // إنشاء سجل جديد في المخزون
            Inventory::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'type' => $type,
                'user_id' => auth()->id(),
                'store_id' => $this->getDefaultStoreId(), // ستحتاج لتحديد المتجر
            ]);

            Log::info('تم تحديث المخزون:', [
                'product_id' => $productId,
                'difference' => $difference,
                'type' => $type->label(),
                'quantity' => $quantity
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في تحديث المخزون: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * الحصول على المتجر الافتراضي
     * يمكنك تعديل هذا حسب منطق التطبيق الخاص بك
     */
    private function getDefaultStoreId()
    {
        // يمكنك إضافة خاصية store_id في المكون أو الحصول عليها من المستخدم
        return $this->store_id ?? \App\Models\Store::first()->id ?? 1;
    }

    public function prepareAttributes()
    {
        $attributes = [];

        Log::info('=== بدء تجهيز الخصائص للحفظ ===', [
            'selectedAttributes_empty' => empty($this->selectedAttributes),
            'attributeMap_empty' => empty($this->attributeMap),
            'selectedAttributes' => $this->selectedAttributes,
            'attributeMap' => $this->attributeMap
        ]);

        if (empty($this->selectedAttributes) || empty($this->attributeMap)) {
            Log::info('لا توجد خصائص للتجهيز - إرجاع مصفوفة فارغة');
            return $attributes;
        }

        foreach ($this->attributeMap as $index => $attribute) {
            $attributeId = $attribute['id'];
            $options = [];

            Log::info("معالجة الخاصية {$index}:", [
                'attribute_id' => $attributeId,
                'attribute_name' => $attribute['name']
            ]);

            if (isset($this->selectedAttributes[$attributeId])) {
                $selectedData = $this->selectedAttributes[$attributeId];

                Log::info("البيانات المحددة للخاصية {$attributeId}:", [
                    'selected_data' => $selectedData,
                    'data_type' => gettype($selectedData)
                ]);

                $selectedTermIds = [];

                // تحديد نوع البيانات والتعامل معها
                if (is_array($selectedData)) {
                    // إذا كانت البيانات في شكل [id => boolean] (من UI)
                    if (!empty($selectedData) && isset(array_values($selectedData)[0]) && is_bool(array_values($selectedData)[0])) {
                        $selectedTermIds = array_keys(array_filter($selectedData));
                    } else {
                        // إذا كانت مصفوفة من IDs
                        $selectedTermIds = $selectedData;
                    }
                }

                Log::info("IDs المحددة:", [
                    'selected_term_ids' => $selectedTermIds
                ]);

                // تحويل IDs إلى أسماء المصطلحات
                if (!empty($selectedTermIds) && isset($this->attributeTerms[$attributeId])) {
                    $terms = $this->attributeTerms[$attributeId];

                    Log::info("المصطلحات المتاحة:", [
                        'available_terms' => $terms
                    ]);

                    foreach ($selectedTermIds as $termId) {
                        $term = collect($terms)->firstWhere('id', $termId);
                        if ($term) {
                            $options[] = $term['name'];
                            Log::info("تم إضافة مصطلح:", [
                                'term_id' => $termId,
                                'term_name' => $term['name']
                            ]);
                        } else {
                            Log::warning("لم يتم العثور على المصطلح:", [
                                'term_id' => $termId
                            ]);
                        }
                    }
                }
            }

            if (!empty($options)) {
                $attributeData = [
                    'id' => $attributeId,
                    'name' => $attribute['name'],
                    'position' => $index,
                    'visible' => true,
                    'variation' => true,
                    'options' => $options
                ];

                $attributes[] = $attributeData;

                Log::info("تم تجهيز الخاصية بنجاح:", [
                    'attribute_data' => $attributeData
                ]);
            } else {
                Log::info("تم تجاهل الخاصية - لا توجد خيارات:", [
                    'attribute_id' => $attributeId,
                    'attribute_name' => $attribute['name']
                ]);
            }
        }

        Log::info('=== انتهاء تجهيز الخصائص ===', [
            'prepared_attributes_count' => count($attributes),
            'prepared_attributes' => $attributes
        ]);

        return $attributes;
    }

    public function save()
    {
        try {
            Log::info('=== بدء عملية الحفظ المحدثة ===', [
                'product_id' => $this->productId,
                'product_type' => $this->productType,
            ]);

            // حفظ الكمية القديمة قبل التحديث
            $oldProduct = $this->wooService->getProduct($this->productId);
            $oldQuantity = $oldProduct['stock_quantity'] ?? 0;

            // تجهيز بيانات المنتج الأساسية
            $productData = [
                'name' => $this->productName,
                'description' => $this->productDescription,
                'type' => $this->productType,
                'stock_status' => $this->stockStatus,
                'categories' => array_map(fn($id) => ['id' => (int)$id], $this->selectedCategories),
            ];

            // معالجة الأسعار
            if (!empty($this->regularPrice)) {
                $productData['regular_price'] = (string) number_format((float) str_replace(',', '.', $this->regularPrice), 2, '.', '');
            }

            if (!empty($this->salePrice)) {
                $productData['sale_price'] = (string) number_format((float) str_replace(',', '.', $this->salePrice), 2, '.', '');
            } else {
                $productData['sale_price'] = '';
            }

            // إدارة المخزون
            if ($this->productType !== 'variable') {
                $productData['manage_stock'] = (bool) $this->isStockManagementEnabled;
                if (!is_null($this->stockQuantity)) {
                    $productData['stock_quantity'] = (int) $this->stockQuantity;
                }
                $productData['stock_status'] = $this->stockStatus;
                $productData['sold_individually'] = (bool) $this->soldIndividually;
                $productData['backorders'] = $this->allowBackorders;
                if (!is_null($this->lowStockThreshold)) {
                    $productData['low_stock_amount'] = (int) $this->lowStockThreshold;
                }
            }

            if (!empty($this->sku)) {
                $productData['sku'] = trim($this->sku);
            }

            if (!empty($this->brandId)) {
                $productData['brands'] = [['id' => (int) $this->brandId]];
            } else {
                $productData['brands'] = [];
            }

            // معالجة المنتج المتغير
            if ($this->productType === 'variable') {
                if (empty($this->selectedAttributes) || empty($this->attributeMap) || empty($this->variations)) {
                    throw new \Exception('بيانات المنتج المتغير غير مكتملة');
                }

                $attributes = $this->prepareVariableProductAttributes();
                if (empty($attributes)) {
                    throw new \Exception('فشل في تجهيز خصائص المنتج المتغير');
                }

                $productData['attributes'] = $attributes;

                if (!empty($this->variations)) {
                    $defaultAttributes = $this->prepareDefaultAttributes();
                    if (!empty($defaultAttributes)) {
                        $productData['default_attributes'] = $defaultAttributes;
                    }
                }

                $productData['manage_stock'] = false;
                $productData['stock_status'] = 'instock';
                unset($productData['regular_price'], $productData['sale_price']);
            }

            // تحديث المنتج الأساسي
            Log::info('تحديث المنتج الأساسي...');
            $updatedProduct = $this->wooService->updateProduct($this->productId, $productData);

            if (!$updatedProduct) {
                throw new \Exception('فشل تحديث المنتج الأساسي');
            }

            Log::info('✅ تم تحديث المنتج الأساسي بنجاح');

            $newQuantity = (int) $this->stockQuantity;
                    $this->updateInventory($oldQuantity, $newQuantity, $this->productId);
                    Log::info('✅ تم تحديث سجل المخزون');

            // تحديث المتغيرات للمنتج المتغير
            if ($this->productType === 'variable' && !empty($this->variations)) {
                Log::info('بدء تحديث المتغيرات...');

                $cleanedVariations = $this->prepareVariationsForSync();

                if (empty($cleanedVariations)) {
                    throw new \Exception('لا توجد متغيرات صالحة للحفظ');
                }

                $syncResult = $this->wooService->syncVariations($this->productId, $cleanedVariations);

                if (!$syncResult['success']) {
                    throw new \Exception('فشل في مزامنة المتغيرات: ' . ($syncResult['message'] ?? 'خطأ غير معروف'));
                }

                Log::info('✅ تم تحديث المتغيرات بنجاح:', $syncResult);
            }

            // تحديث MRBP
            if (!empty($this->mrbpData)) {
                try {
                    $this->wooService->updateMrbpData($this->productId, $this->mrbpData);
                    Log::info('✅ تم تحديث بيانات MRBP');
                } catch (\Exception $e) {
                    Log::warning('فشل تحديث MRBP (غير حرج):', ['error' => $e->getMessage()]);
                }
            }

            $this->isSaving = false;

            Log::info('=== انتهت عملية الحفظ بنجاح ===');

            if ($this->productType === 'variable') {
                Toaster::success('✅ تم تحديث المنتج المتغير بنجاح مع جميع الخصائص والمتغيرات');
            } else {
                Toaster::success('✅ تم تحديث المنتج والمخزون بنجاح');
            }

            return redirect()->route('product.index');

        } catch (\Exception $e) {
            $this->isSaving = false;
            Log::error('❌ فشل في حفظ المنتج:', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            Toaster::error('فشل في حفظ المنتج: ' . $e->getMessage());
        }
    }
    /**
     * تجهيز خصائص المنتج المتغير
     */
    private function prepareVariableProductAttributes(): array
    {
        $attributes = [];

        Log::info('=== تجهيز خصائص المنتج المتغير ===');

        foreach ($this->attributeMap as $index => $attribute) {
            $attributeId = $attribute['id'];
            $options = [];

            // جمع جميع القيم الفريدة لهذه الخاصية من المتغيرات
            $uniqueValues = collect($this->variations)
                ->pluck("options.{$index}")
                ->unique()
                ->filter()
                ->values()
                ->toArray();

            if (!empty($uniqueValues)) {
                $attributes[] = [
                    'id' => $attributeId,
                    'name' => $attribute['name'],
                    'position' => $index,
                    'visible' => true,
                    'variation' => true,
                    'options' => $uniqueValues
                ];

                Log::info("تم تجهيز الخاصية {$attribute['name']}:", [
                    'attribute_id' => $attributeId,
                    'options' => $uniqueValues
                ]);
            }
        }

        return $attributes;
    }

    /**
     * تجهيز الخصائص الافتراضية
     */
    private function prepareDefaultAttributes(): array
    {
        $defaultAttributes = [];

        if (!empty($this->variations) && isset($this->variations[0]['options'])) {
            $firstVariation = $this->variations[0];

            foreach ($this->attributeMap as $index => $attr) {
                if (isset($firstVariation['options'][$index])) {
                    $defaultAttributes[] = [
                        'id' => (int)$attr['id'],
                        'name' => $attr['name'],
                        'option' => $firstVariation['options'][$index]
                    ];
                }
            }
        }

        return $defaultAttributes;
    }

    /**
     * تجهيز المتغيرات للمزامنة
     * تم تحديث هذه الدالة لإضافة مصفوفة 'attributes' لكل متغير، وهي ضرورية لـ WooCommerce.
     */
    private function prepareVariationsForSync(): array
    {
        $cleanedVariations = [];

        foreach ($this->variations as $variation) {
            // تنظيف الأسعار
            $regularPrice = '';
            $salePrice = '';

            if (!empty($variation['regular_price'])) {
                $regularPrice = (string) number_format(
                    (float) str_replace(',', '.', $variation['regular_price']),
                    2, '.', ''
                );
            }

            if (!empty($variation['sale_price'])) {
                $salePrice = (string) number_format(
                    (float) str_replace(',', '.', $variation['sale_price']),
                    2, '.', ''
                );
            }

            // --- منطق محسّن لتنظيف الكمية وحالة المخزون ---
            $stockQuantity = 0; // الافتراضي 0 بدلاً من null
            if (isset($variation['stock_quantity'])) {
                // استخدام filter_var لتحويل آمن إلى int
                $filteredValue = filter_var($variation['stock_quantity'], FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                if ($filteredValue !== null) {
                    $stockQuantity = $filteredValue;
                }
            }

            // تحديد حالة المخزون بناءً على الكمية
            $stockStatus = 'instock';
            if ($stockQuantity <= 0) {
                $stockStatus = 'outofstock';
            }

            $cleanedVariation = [
                'id' => $variation['id'] ?? null,
                'regular_price' => $regularPrice,
                'sale_price' => $salePrice,
                'stock_quantity' => $stockQuantity,
                'sku' => $variation['sku'] ?? '',
                'description' => $variation['description'] ?? '',
                'manage_stock' => true, // ✅ إجباري: تفعيل إدارة المخزون
                'stock_status' => $stockStatus,
                'status' => 'publish'
            ];

            // --- إعداد خصائص المتغير ---
            $variationAttributes = [];
            if (isset($variation['options']) && is_array($variation['options'])) {
                foreach ($variation['options'] as $index => $optionName) {
                    if (isset($this->attributeMap[$index])) {
                        $attributeId = $this->attributeMap[$index]['id'];

                        if ($attributeId && $optionName !== null && $optionName !== '') {
                            $variationAttributes[] = [
                                'id' => (int) $attributeId,
                                'option' => (string) $optionName,
                            ];
                        }
                    }
                }
            }
            $cleanedVariation['attributes'] = $variationAttributes;

            $cleanedVariations[] = $cleanedVariation;
        }

        Log::info('Variations prepared for sync with manage_stock=true:', [
            'prepared_variations_count' => count($cleanedVariations),
            'sample_variation' => $cleanedVariations[0] ?? null
        ]);

        return $cleanedVariations;
    }
    // باقي الدوال (الصور، التصنيفات، إلخ) بدون تغيير
    public function getCategories(): array
    {
        $response = $this->wooService->getCategories(['per_page' => 100]);

        // استخراج البيانات من مفتاح "data"
        $categories = $response['data'] ?? $response;

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

    public function syncPriceData()
    {
        $this->dispatch('updatePricesFromEdit', [
            'regularPrice' => $this->regularPrice,
            'salePrice' => $this->salePrice,
            'sku' => $this->sku
        ])->to('tabs-component');
    }

    public function removeFeaturedImage()
    {
        $this->featuredImage = null;
    }

    public function removeGalleryImage($index)
    {
        unset($this->galleryImages[$index]);
        $this->galleryImages = array_values($this->galleryImages);
    }

    /**
     * مسح جميع المتغيرات المولدة محلياً
     */
    public function clearVariations()
    {
        $this->variations = [];
        $this->attributeMap = [];

        // إرسال التحديث للمكون الفرعي
        $this->dispatch('variationsCleared')->to('variation-manager');

        Toaster::success('تم مسح جميع المتغيرات');

        Log::info('تم مسح جميع المتغيرات محلياً');
    }

    /**
     * معاينة المتغيرات قبل الحفظ
     */
    public function previewVariations()
    {
        if (empty($this->variations)) {
            Toaster::info('لا توجد متغيرات للمعاينة');
            return;
        }

        $summary = [];
        foreach ($this->variations as $variation) {
            if (!empty($variation['options'])) {
                $summary[] = implode(' × ', $variation['options']);
            }
        }

        Log::info('معاينة المتغيرات:', [
            'total_variations' => count($this->variations),
            'variations_summary' => $summary
        ]);

        Toaster::info("المتغيرات المولدة: " . count($this->variations) . " متغير");
    }

    /**
     * إضافة دوال للحصول على حالة التحديث والحفظ
     */
    public function getRefreshingProperty()
    {
        return $this->isRefreshing;
    }

    public function getSavingProperty()
    {
        return $this->isSaving;
    }

    public function render()
    {
        return view('livewire.pages.product.edit');
    }
}

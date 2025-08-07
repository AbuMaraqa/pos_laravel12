<?php

namespace App\Livewire;

use App\Services\WooCommerceService;
use Livewire\Attributes\On;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class VariationManager extends Component
{
    public $productId;

    // البيانات المحلية (يمكن تعديلها)
    public $variations = [];
    public $attributeMap = [];
    public $selectedAttributes = [];

    // البيانات الأساسية
    public $loadedAttributes = [];
    public $attributeTerms = [];

    // حقول التحديث الجماعي
    public $allRegularPrice = '';
    public $allSalePrice = '';
    public $allStockQuantity = '';

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount($productId = null, $variations = [], $attributeMap = [], $selectedAttributes = [])
    {
        $this->productId = $productId;
        $this->variations = $variations;
        $this->attributeMap = $attributeMap;
        $this->selectedAttributes = $selectedAttributes;

        Log::info('VariationManager mounted', [
            'productId' => $productId,
            'variations_count' => count($variations),
            'attributeMap_count' => count($attributeMap),
            'selectedAttributes_count' => count($selectedAttributes),
            'selectedAttributes' => $selectedAttributes
        ]);

        $this->loadAttributes();
    }

    /**
     * معالجة selectedAttributes المرسلة من Edit component
     */
    private function processSelectedAttributes($selectedAttributes)
    {
        $processed = [];

        if (empty($selectedAttributes)) {
            return $processed;
        }

        // إذا كانت البيانات من Edit component (مصفوفة term IDs)
        foreach ($selectedAttributes as $attributeId => $termIds) {
            $processed[$attributeId] = [];

            if (is_array($termIds)) {
                // إذا كانت مصفوفة من IDs (من Edit)
                if (array_is_list($termIds)) {
                    // تهيئة جميع المصطلحات بـ false
                    if (isset($this->attributeTerms[$attributeId])) {
                        foreach ($this->attributeTerms[$attributeId] as $term) {
                            $processed[$attributeId][$term['id']] = in_array($term['id'], $termIds);
                        }
                    }
                } else {
                    // إذا كانت مصفوفة key => boolean (من VariationManager)
                    $processed[$attributeId] = $termIds;
                }
            }
        }

        return $processed;
    }

    public function loadAttributes()
    {
        try {
            $this->loadedAttributes = $this->wooService->getAttributes([
                'per_page' => 100
            ]);

            foreach ($this->loadedAttributes as $attribute) {
                $this->attributeTerms[$attribute['id']] = $this->wooService->getTermsForAttribute($attribute['id'] , [
                    'per_page' => 100,
                    'translations' => app()->getLocale()
                ]);
            }

            // ✅ بعد تحميل المصطلحات، نعيد معالجة selectedAttributes
            if (!empty($this->selectedAttributes)) {
                $this->selectedAttributes = $this->processSelectedAttributes($this->selectedAttributes);
            }

            Log::info('Attributes loaded', [
                'loadedAttributes_count' => count($this->loadedAttributes),
                'attributeTerms' => array_map(fn($terms) => count($terms), $this->attributeTerms),
                'final_selectedAttributes' => $this->selectedAttributes
            ]);

        } catch (\Exception $e) {
            Log::error('خطأ في تحميل الخصائص: ' . $e->getMessage());
            session()->flash('error', 'فشل في تحميل الخصائص');
        }
    }

    // ✅ إضافة listener لاستقبال تحديثات من Edit component
    #[On('updateSelectedAttributes')]
    public function updateSelectedAttributesFromEdit($data)
    {
        Log::info('Received selectedAttributes update from Edit', $data);

        if (isset($data['selectedAttributes'])) {
            $this->selectedAttributes = $this->processSelectedAttributes($data['selectedAttributes']);

            Log::info('Updated selectedAttributes in VariationManager', [
                'new_selectedAttributes' => $this->selectedAttributes
            ]);
        }
    }

    /**
     * يستقبل المتغيرات التي تم جلبها من WooCommerce بواسطة مكون Edit.
     * هذا يضمن أن VariationManager لديه أحدث قائمة بالمتغيرات الموجودة.
     */
    #[On('variationsGenerated')]
    public function handleVariationsGenerated($data)
    {
        Log::info('Received variationsGenerated event from Edit component.', [
            'variations_count' => count($data['variations'] ?? []),
            'attributeMap_count' => count($data['attributeMap'] ?? [])
        ]);

        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];

        Log::info('VariationManager updated with generated variations.', [
            'current_variations_count' => count($this->variations),
            'current_attributeMap_count' => count($this->attributeMap)
        ]);
    }


    public function generateVariations()
    {
        try {
            // تحضير البيانات المحددة
            $selectedData = [];
            foreach ($this->selectedAttributes as $attributeId => $terms) {
                if (is_array($terms)) {
                    $selectedTermIds = array_keys(array_filter($terms));
                    if (!empty($selectedTermIds)) {
                        $selectedData[$attributeId] = $selectedTermIds;
                    }
                }
            }

            if (empty($selectedData)) {
                session()->flash('error', 'يرجى اختيار خصائص أولاً');
                return;
            }

            // تحضير خيارات الخصائص
            $attributeOptions = [];
            $this->attributeMap = [];

            foreach ($selectedData as $attributeId => $termIds) {
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
                        'name' => collect($this->loadedAttributes)->firstWhere('id', $attributeId)['name'] ?? 'خاصية',
                    ];
                }
            }

            // توليد مجموعات المتغيرات
            $combinations = $this->cartesian(array_values($attributeOptions));
            $newVariations = [];

            // الحفاظ على البيانات الموجودة للمتغيرات عند إعادة التوليد
            $existingVariationsData = [];
            foreach ($this->variations as $variation) {
                if (isset($variation['options'])) {
                    $key = implode('|', $variation['options']);
                    $existingVariationsData[$key] = [
                        'id' => $variation['id'] ?? null,
                        'regular_price' => $variation['regular_price'] ?? '',
                        'sale_price' => $variation['sale_price'] ?? '',
                        'stock_quantity' => $variation['stock_quantity'] ?? 0, // الافتراضي 0
                        'sku' => $variation['sku'] ?? '',
                        'description' => $variation['description'] ?? ''
                    ];
                }
            }

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
                    'stock_quantity' => $existingData['stock_quantity'] ?? 0, // الافتراضي 0
                    'sku' => $existingData['sku'] ?? '',
                    'description' => $existingData['description'] ?? '',
                    'manage_stock' => true, // ✅ تفعيل إدارة المخزون دائماً
                    'active' => true
                ];
            }

            $this->variations = $newVariations;
            $this->notifyParentOfUpdate();

            session()->flash('success', 'تم توليد ' . count($this->variations) . ' متغير بنجاح مع تفعيل إدارة المخزون');

        } catch (\Exception $e) {
            Log::error('خطأ في توليد المتغيرات: ' . $e->getMessage());
            session()->flash('error', 'حدث خطأ في توليد المتغيرات');
        }
    }
    private function areOptionsEqual($options1, $options2)
    {
        if (count($options1) !== count($options2)) {
            return false;
        }

        foreach ($options1 as $i => $option) {
            if (!isset($options2[$i]) || $option !== $options2[$i]) {
                return false;
            }
        }

        return true;
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

    #[On('requestLatestVariations')]
    public function sendLatestVariations($data = [])
    {
        // التحقق من سلامة البيانات أولاً
        $this->validateVariations();

        // التحقق من صحة البيانات
        $validVariations = [];
        foreach ($this->variations as $index => $variation) {
            // التأكد من وجود العناصر المطلوبة
            if (!isset($variation['options'])) {
                Log::warning('Variation missing options, skipping', ['index' => $index]);
                continue;
            }

            if (!empty($variation['regular_price'])) {
                $validVariations[] = $variation;
            } else {
                Log::warning('Variation missing regular_price, skipping', [
                    'index' => $index,
                    'variation' => $variation
                ]);
            }
        }

        if (empty($validVariations)) {
            session()->flash('error', 'لا توجد متغيرات صالحة للحفظ. يرجى التأكد من إدخال الأسعار.');
            return;
        }

        $targetComponent = $this->productId ? 'pages.product.edit' : 'pages.product.add';

        $this->dispatch('latestVariationsSent', [
            'variations' => $validVariations,
            'attributeMap' => $this->attributeMap,
            'selectedAttributes' => $this->getSelectedAttributesForSaving()
        ])->to($targetComponent);

        Log::info('Valid variations sent successfully', [
            'total_variations' => count($this->variations),
            'valid_variations' => count($validVariations)
        ]);
    }

    private function getSelectedAttributesForSaving()
    {
        $result = [];
        foreach ($this->selectedAttributes as $attributeId => $terms) {
            if (is_array($terms)) {
                $selectedTerms = array_keys(array_filter($terms));
                if (!empty($selectedTerms)) {
                    $result[$attributeId] = $selectedTerms;
                }
            }
        }
        return $result;
    }

    // التحديث الجماعي
    public function updatedAllRegularPrice($value)
    {
        if (!empty($value) && is_numeric($value)) {
            foreach ($this->variations as $index => $variation) {
                $this->variations[$index]['regular_price'] = (string)$value; // تأكد من أنها string لـ WC API
            }
            $this->notifyParentOfUpdate();
        }
    }

    public function updatedAllSalePrice($value)
    {
        if (!empty($value) && is_numeric($value)) {
            foreach ($this->variations as $index => $variation) {
                $this->variations[$index]['sale_price'] = (string)$value; // تأكد من أنها string لـ WC API
            }
            $this->notifyParentOfUpdate();
        }
    }

    public function updatedAllStockQuantity($value)
    {
        // تحويل القيمة إلى عدد صحيح، أو 0 إذا كانت فارغة
        $cleanedValue = (empty($value) && $value !== 0 && $value !== '0') ? 0 : (int)$value;

        foreach ($this->variations as $index => $variation) {
            $this->variations[$index]['stock_quantity'] = $cleanedValue;
            $this->variations[$index]['manage_stock'] = true; // ✅ تأكد من تفعيل إدارة المخزون
        }
        $this->notifyParentOfUpdate();
    }

    /**
     * دالة لمعالجة تحديث الكمية لكل متغير على حدة
     * وتضمن تحويلها إلى عدد صحيح.
     */
    public function syncStockQuantity($value, $index)
    {
        // تحويل القيمة إلى عدد صحيح، أو 0 إذا كانت فارغة
        $cleanedValue = (empty($value) && $value !== 0 && $value !== '0') ? 0 : (int)$value;

        if (isset($this->variations[$index])) {
            $this->variations[$index]['stock_quantity'] = $cleanedValue;
            $this->variations[$index]['manage_stock'] = true; // ✅ تأكد من تفعيل إدارة المخزون

            Log::info('Individual stock quantity updated with manage_stock=true', [
                'index' => $index,
                'value' => $value,
                'cleaned_value' => $cleanedValue,
                'manage_stock' => $this->variations[$index]['manage_stock']
            ]);

            $this->notifyParentOfUpdate();
        }
    }

    public function updatedVariations($value, $name)
    {
        // ✅ تسجيل التحديثات للتشخيص
        Log::info('Variation field updated', [
            'field' => $name,
            'value' => $value,
            'variations_count' => count($this->variations)
        ]);

        // ✅ التحقق من أن المتغيرات موجودة وصالحة
        if (empty($this->variations)) {
            Log::warning('No variations found during update');
            return;
        }

        // ✅ استخراج الفهرس من اسم الحقل
        // مثال: variations.0.stock_quantity -> الفهرس = 0
        if (preg_match('/variations\.(\d+)\./', $name, $matches)) {
            $index = (int)$matches[1];

            // ✅ التحقق من وجود المتغير في الفهرس المحدد
            if (!isset($this->variations[$index])) {
                Log::warning('Variation not found at index', [
                    'index' => $index,
                    'field' => $name
                ]);
                return;
            }

            // ✅ التأكد من وجود options في المتغير
            if (!isset($this->variations[$index]['options'])) {
                Log::warning('Options not found for variation, initializing...', [
                    'index' => $index,
                    'variation_keys' => array_keys($this->variations[$index])
                ]);

                // إنشاء options فارغة إذا لم تكن موجودة
                $this->variations[$index]['options'] = [];
            }

            // ✅ تأكد من تفعيل إدارة المخزون للمتغير المحدث
            $this->variations[$index]['manage_stock'] = true;

            // ✅ معالجة خاصة لحقل stock_quantity
            if (str_contains($name, '.stock_quantity')) {
                $cleanedValue = (empty($value) && $value !== 0 && $value !== '0') ? 0 : (int)$value;
                $this->variations[$index]['stock_quantity'] = $cleanedValue;

                Log::info('Stock quantity updated for single variation', [
                    'index' => $index,
                    'original_value' => $value,
                    'cleaned_value' => $cleanedValue,
                    'manage_stock' => $this->variations[$index]['manage_stock']
                ]);
            }

            // ✅ معالجة خاصة لحقل regular_price
            if (str_contains($name, '.regular_price')) {
                $this->variations[$index]['regular_price'] = (string)$value;

                Log::info('Regular price updated for single variation', [
                    'index' => $index,
                    'value' => $value
                ]);
            }

            // ✅ معالجة خاصة لحقل sale_price
            if (str_contains($name, '.sale_price')) {
                $this->variations[$index]['sale_price'] = (string)$value;

                Log::info('Sale price updated for single variation', [
                    'index' => $index,
                    'value' => $value
                ]);
            }
        }

        $this->notifyParentOfUpdate();
    }

    public function updateSingleVariationStock($index, $value)
    {
        try {
            // التحقق من صحة الفهرس
            if (!isset($this->variations[$index])) {
                Log::error('Invalid variation index for stock update', [
                    'index' => $index,
                    'total_variations' => count($this->variations)
                ]);
                return;
            }

            // تنظيف القيمة
            $cleanedValue = (empty($value) && $value !== 0 && $value !== '0') ? 0 : (int)$value;

            // تحديث البيانات
            $this->variations[$index]['stock_quantity'] = $cleanedValue;
            $this->variations[$index]['manage_stock'] = true;

            // التأكد من وجود options
            if (!isset($this->variations[$index]['options'])) {
                $this->variations[$index]['options'] = [];
            }

            Log::info('Single variation stock updated successfully', [
                'index' => $index,
                'value' => $value,
                'cleaned_value' => $cleanedValue,
                'manage_stock' => true
            ]);

            $this->notifyParentOfUpdate();

        } catch (\Exception $e) {
            Log::error('Error updating single variation stock', [
                'index' => $index,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateSingleVariationPrice($index, $field, $value)
    {
        try {
            // التحقق من صحة الفهرس
            if (!isset($this->variations[$index])) {
                Log::error('Invalid variation index for price update', [
                    'index' => $index,
                    'field' => $field,
                    'total_variations' => count($this->variations)
                ]);
                return;
            }

            // التحقق من صحة الحقل
            if (!in_array($field, ['regular_price', 'sale_price'])) {
                Log::error('Invalid price field', [
                    'field' => $field,
                    'allowed_fields' => ['regular_price', 'sale_price']
                ]);
                return;
            }

            // تحديث البيانات
            $this->variations[$index][$field] = (string)$value;
            $this->variations[$index]['manage_stock'] = true;

            // التأكد من وجود options
            if (!isset($this->variations[$index]['options'])) {
                $this->variations[$index]['options'] = [];
            }

            Log::info('Single variation price updated successfully', [
                'index' => $index,
                'field' => $field,
                'value' => $value
            ]);

            $this->notifyParentOfUpdate();

        } catch (\Exception $e) {
            Log::error('Error updating single variation price', [
                'index' => $index,
                'field' => $field,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function validateVariations()
    {
        $fixed = false;

        foreach ($this->variations as $index => &$variation) {
            // التأكد من وجود options
            if (!isset($variation['options'])) {
                $variation['options'] = [];
                $fixed = true;
                Log::info('Fixed missing options for variation', ['index' => $index]);
            }

            // التأكد من تفعيل إدارة المخزون
            if (!isset($variation['manage_stock']) || !$variation['manage_stock']) {
                $variation['manage_stock'] = true;
                $fixed = true;
                Log::info('Fixed manage_stock for variation', ['index' => $index]);
            }

            // التأكد من وجود stock_quantity كرقم
            if (!isset($variation['stock_quantity']) || !is_numeric($variation['stock_quantity'])) {
                $variation['stock_quantity'] = 0;
                $fixed = true;
                Log::info('Fixed stock_quantity for variation', ['index' => $index]);
            }
        }

        if ($fixed) {
            Log::info('Variations data fixed and validated');
            $this->notifyParentOfUpdate();
        }

        return $fixed;
    }

    private function notifyParentOfUpdate()
    {
        $targetComponent = $this->productId ? 'pages.product.edit' : 'pages.product.add';

        $this->dispatch('variationsUpdated', [
            'variations' => $this->variations,
            'attributeMap' => $this->attributeMap
        ])->to($targetComponent);
    }

    #[On('forceUpdateSelectedAttributes')]
    public function forceUpdateSelectedAttributes($data)
    {
        Log::info('Force update received', $data);

        $this->selectedAttributes = $data['selectedAttributes'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
        $this->variations = $data['variations'] ?? [];

        Log::info('VariationManager force updated', [
            'selectedAttributes' => $this->selectedAttributes,
            'attributeMap' => $this->attributeMap,
            'variations_count' => count($this->variations)
        ]);

        // إعادة رسم المكون
        $this->render();
    }
    public function sendAttributesToVariationManager()
    {
        // إرسال البيانات للـ VariationManager بعد التأكد من تحميلها
        $this->dispatch('forceUpdateSelectedAttributes', [
            'selectedAttributes' => $this->selectedAttributes,
            'attributeMap' => $this->attributeMap,
            'variations' => $this->variations
        ])->to('variation-manager');

        Log::info('Sent attributes to VariationManager', [
            'selectedAttributes' => $this->selectedAttributes,
            'attributeMap' => $this->attributeMap
        ]);
    }

// تعديل دالة loadVariableProductData
    protected function loadVariableProductData($product)
    {
        $this->attributeMap = [];
        $this->selectedAttributes = [];

        // معالجة خصائص المنتج
        if (!empty($product['attributes'])) {
            foreach ($product['attributes'] as $attribute) {
                if (isset($attribute['id'])) {
                    $attributeId = $attribute['id'];

                    // إيجاد الخاصية في النظام
                    $systemAttribute = collect($this->productAttributes)->firstWhere('id', $attributeId);

                    if ($systemAttribute) {
                        $this->attributeMap[] = [
                            'id' => $attributeId,
                            'name' => $systemAttribute['name']
                        ];

                        // حفظ IDs المحددة كمصفوفة boolean للـ VariationManager
                        $this->selectedAttributes[$attributeId] = [];

                        // تهيئة جميع المصطلحات بـ false
                        foreach ($this->attributeTerms[$attributeId] as $term) {
                            $this->selectedAttributes[$attributeId][$term['id']] = false;
                        }

                        // تحديد المصطلحات المحددة بـ true
                        if (!empty($attribute['options'])) {
                            foreach ($this->attributeTerms[$attributeId] as $term) {
                                if (in_array($term['name'], $attribute['options'])) {
                                    $this->selectedAttributes[$attributeId][$term['id']] = true;
                                }
                            }
                        }
                    }
                }
            }
        }

        // تحميل المتغيرات
        $this->loadProductVariations();

        Log::info('Variable product data loaded', [
            'selectedAttributes' => $this->selectedAttributes,
            'attributeMap' => $this->attributeMap,
            'variations_count' => count($this->variations)
        ]);
    }



    public function render()
    {
        return view('livewire.variation-manager');
    }
}

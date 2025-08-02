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
                    'per_page' => 100
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

    public function updatedSelectedAttributes()
    {
        // تنظيف البيانات وإرسالها للمكون الرئيسي
        $cleanedAttributes = [];
        foreach ($this->selectedAttributes as $attributeId => $terms) {
            if (is_array($terms)) {
                $selectedTerms = array_keys(array_filter($terms, fn($value) => $value === true));
                if (!empty($selectedTerms)) {
                    $cleanedAttributes[$attributeId] = $selectedTerms;
                }
            }
        }

        $targetComponent = $this->productId ? 'pages.product.edit' : 'pages.product.add';
        $this->dispatch('attributesSelected', [
            'selectedAttributes' => $cleanedAttributes
        ])->to($targetComponent);
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

            foreach ($combinations as $combo) {
                // البحث عن متغير موجود بنفس الخيارات
                $existingVariation = null;
                foreach ($this->variations as $variation) {
                    if (isset($variation['options']) && $this->areOptionsEqual($variation['options'], $combo)) {
                        $existingVariation = $variation;
                        break;
                    }
                }

                if ($existingVariation) {
                    $newVariations[] = $existingVariation;
                } else {
                    $newVariations[] = [
                        'options' => $combo,
                        'sku' => '',
                        'regular_price' => '',
                        'sale_price' => '',
                        'stock_quantity' => '',
                        'description' => '',
                    ];
                }
            }

            $this->variations = $newVariations;
            $this->notifyParentOfUpdate();

            session()->flash('success', 'تم توليد ' . count($this->variations) . ' متغير بنجاح');

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
        // التحقق من صحة البيانات
        $validVariations = [];
        foreach ($this->variations as $variation) {
            if (!empty($variation['regular_price']) && !empty($variation['options'])) {
                $validVariations[] = $variation;
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
                $this->variations[$index]['regular_price'] = $value;
            }
            $this->notifyParentOfUpdate();
        }
    }

    public function updatedAllSalePrice($value)
    {
        if (!empty($value) && is_numeric($value)) {
            foreach ($this->variations as $index => $variation) {
                $this->variations[$index]['sale_price'] = $value;
            }
            $this->notifyParentOfUpdate();
        }
    }

    public function updatedAllStockQuantity($value)
    {
        if (!empty($value) && is_numeric($value)) {
            foreach ($this->variations as $index => $variation) {
                $this->variations[$index]['stock_quantity'] = $value;
            }
            $this->notifyParentOfUpdate();
        }
    }

    public function updatedVariations($value, $name)
    {
        // ✅ تسجيل التحديثات للتشخيص
        Log::info('Variation field updated', [
            'field' => $name,
            'value' => $value,
            'all_variations' => $this->variations
        ]);

        $this->notifyParentOfUpdate();
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

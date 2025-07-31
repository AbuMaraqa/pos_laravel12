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

        // ✅ نسخ البيانات للخصائص المحلية
        $this->variations = $variations;
        $this->attributeMap = $attributeMap;
        $this->selectedAttributes = $selectedAttributes;

        $this->loadAttributes();
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
        } catch (\Exception $e) {
            Log::error('خطأ في تحميل الخصائص: ' . $e->getMessage());
            session()->flash('error', 'فشل في تحميل الخصائص');
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

/*************  ✨ Windsurf Command ⭐  *************/
    /**
     * توليد مجموعات المتغيرات من الخصائص المحددة
     *
     * @return void
     */
/*******  05fc9aa1-e6f3-4f4a-b244-bc1bf130f2bd  *******/
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

    public function render()
    {
        return view('livewire.variation-manager');
    }
}

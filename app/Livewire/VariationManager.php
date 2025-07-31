<?php

namespace App\Livewire;

use App\Services\WooCommerceService;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class VariationManager extends Component
{
    public $productId;

    // البيانات الأساسية
    public $loadedAttributes = [];
    public $attributeTerms = [];
    public $selectedAttributes = []; // بنية مبسطة: [attribute_id => [term_id => boolean]]

    // بيانات المتغيرات
    public $variations = [];
    public $attributeMap = [];

    // حقول التحديث الجماعي
    public $allRegularPrice = '';
    public $allSalePrice = '';
    public $allStockQuantity = '';

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount($productId = null)
    {
        $this->productId = $productId;
        $this->loadAttributes();
    }

    public function loadAttributes()
    {
        try {
            $this->loadedAttributes = $this->wooService->getAttributes();

            foreach ($this->loadedAttributes as $attribute) {
                $this->attributeTerms[$attribute['id']] = $this->wooService->getTermsForAttribute($attribute['id']);

                // تهيئة المصفوفة للخاصية
                if (!isset($this->selectedAttributes[$attribute['id']])) {
                    $this->selectedAttributes[$attribute['id']] = [];
                }
            }
        } catch (\Exception $e) {
            Log::error('خطأ في تحميل الخصائص: ' . $e->getMessage());
            session()->flash('error', 'فشل في تحميل الخصائص');
        }
    }

    public function updatedSelectedAttributes()
    {
        // تنظيف البيانات المحددة
        $cleanedAttributes = [];

        foreach ($this->selectedAttributes as $attributeId => $terms) {
            if (is_array($terms)) {
                $selectedTerms = array_keys(array_filter($terms, fn($value) => $value === true));
                if (!empty($selectedTerms)) {
                    $cleanedAttributes[$attributeId] = $selectedTerms;
                }
            }
        }

        // إرسال البيانات للمكون الرئيسي
        $this->dispatch('attributesSelected', [
            'selectedAttributes' => $cleanedAttributes
        ])->to('pages.product.add');
    }

    #[On('variationsGenerated')]
    public function handleVariationsGenerated($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];

        Log::info('تم استلام المتغيرات المولدة', [
            'variations_count' => count($this->variations),
            'attributes_count' => count($this->attributeMap)
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

            // إرسال البيانات للمكون الرئيسي لتوليد المتغيرات
            $this->dispatch('attributesSelected', [
                'selectedAttributes' => $selectedData
            ])->to('pages.product.add');

            session()->flash('success', 'تم توليد المتغيرات بنجاح');

        } catch (\Exception $e) {
            Log::error('خطأ في توليد المتغيرات: ' . $e->getMessage());
            session()->flash('error', 'حدث خطأ في توليد المتغيرات');
        }
    }

    #[On('requestLatestVariations')]
    public function sendLatestVariations()
    {
        // التحقق من صحة البيانات قبل الإرسال
        $validVariations = [];

        foreach ($this->variations as $index => $variation) {
            // التحقق من وجود سعر أساسي
            if (empty($variation['regular_price'])) {
                Log::warning('متغير بدون سعر أساسي', ['index' => $index]);
                continue;
            }

            // التحقق من وجود خيارات
            if (empty($variation['options']) || !is_array($variation['options'])) {
                Log::warning('متغير بدون خيارات', ['index' => $index]);
                continue;
            }

            // التحقق من أن جميع الخيارات لها قيم
            $hasEmptyOptions = false;
            foreach ($variation['options'] as $option) {
                if (empty($option)) {
                    $hasEmptyOptions = true;
                    break;
                }
            }

            if ($hasEmptyOptions) {
                Log::warning('متغير يحتوي على خيارات فارغة', ['index' => $index]);
                continue;
            }

            $validVariations[] = $variation;
        }

        if (empty($validVariations)) {
            Log::error('لا توجد متغيرات صالحة للإرسال');
            return;
        }

        Log::info('إرسال المتغيرات الصالحة', [
            'total_variations' => count($this->variations),
            'valid_variations' => count($validVariations)
        ]);

        $this->dispatch('latestVariationsSent', [
            'variations' => $validVariations,
            'attributeMap' => $this->attributeMap,
            'selectedAttributes' => $this->getSelectedAttributesForSaving()
        ])->to('pages.product.add');
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

    // التحديث الجماعي للحقول
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

    // إشعار المكون الرئيسي بالتحديثات
    public function updatedVariations()
    {
        $this->notifyParentOfUpdate();
    }

    private function notifyParentOfUpdate()
    {
        $this->dispatch('variationsUpdated', [
            'variations' => $this->variations,
            'attributeMap' => $this->attributeMap
        ])->to('pages.product.add');
    }

    // إعادة تعيين البيانات
    public function resetVariations()
    {
        $this->variations = [];
        $this->attributeMap = [];
        $this->selectedAttributes = [];

        // إعادة تهيئة المصفوفات
        foreach ($this->loadedAttributes as $attribute) {
            $this->selectedAttributes[$attribute['id']] = [];
        }

        $this->notifyParentOfUpdate();
    }

    // التحقق من صحة المتغيرات
    public function validateVariations()
    {
        $errors = [];

        foreach ($this->variations as $index => $variation) {
            $variationName = 'المتغير ' . ($index + 1);

            if (empty($variation['regular_price'])) {
                $errors[] = "{$variationName}: السعر العادي مطلوب";
            } elseif (!is_numeric($variation['regular_price']) || $variation['regular_price'] < 0) {
                $errors[] = "{$variationName}: السعر العادي يجب أن يكون رقماً موجباً";
            }

            if (!empty($variation['sale_price'])) {
                if (!is_numeric($variation['sale_price']) || $variation['sale_price'] < 0) {
                    $errors[] = "{$variationName}: سعر التخفيض يجب أن يكون رقماً موجباً";
                } elseif ($variation['sale_price'] >= $variation['regular_price']) {
                    $errors[] = "{$variationName}: سعر التخفيض يجب أن يكون أقل من السعر العادي";
                }
            }

            if (isset($variation['stock_quantity']) && !is_numeric($variation['stock_quantity'])) {
                $errors[] = "{$variationName}: كمية المخزون يجب أن تكون رقماً";
            }
        }

        return $errors;
    }

    // حفظ المتغيرات مؤقتاً في الجلسة
    public function saveToSession()
    {
        session(['temp_variations' => [
            'variations' => $this->variations,
            'attributeMap' => $this->attributeMap,
            'selectedAttributes' => $this->selectedAttributes
        ]]);
    }

    // استرداد المتغيرات من الجلسة
    public function loadFromSession()
    {
        $tempData = session('temp_variations');
        if ($tempData) {
            $this->variations = $tempData['variations'] ?? [];
            $this->attributeMap = $tempData['attributeMap'] ?? [];
            $this->selectedAttributes = $tempData['selectedAttributes'] ?? [];
        }
    }

    public function render()
    {
        return view('livewire.variation-manager');
    }
}

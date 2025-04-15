<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use App\Services\WooCommerceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class VariationManager extends Component
{
    public $productAttributes = [];
    public $attributeTerms = [];
    public $selectedAttributes = [];
    public $variations = [];
    public $attributeMap = [];
    private $attributeLookup = [];
    private $termLookup = [];
    private $isLoading = false;
    public $loadedAttributes = [];
    public $currentPage = 1;
    public $perPage = 5;
    public $totalAttributes = 0;
    public $errors = [];

    protected $rules = [
        'variations.*.sku' => 'required|string|max:100',
        'variations.*.regular_price' => 'required|numeric|min:0',
        'variations.*.sale_price' => 'nullable|numeric|min:0',
        'variations.*.stock_quantity' => 'required|integer|min:0',
        'variations.*.description' => 'nullable|string|max:500',
    ];

    protected $messages = [
        'variations.*.sku.required' => 'حقل SKU مطلوب',
        'variations.*.sku.max' => 'SKU يجب أن لا يتجاوز 100 حرف',
        'variations.*.regular_price.required' => 'السعر العادي مطلوب',
        'variations.*.regular_price.numeric' => 'السعر العادي يجب أن يكون رقماً',
        'variations.*.regular_price.min' => 'السعر العادي يجب أن يكون أكبر من أو يساوي صفر',
        'variations.*.sale_price.numeric' => 'سعر الخصم يجب أن يكون رقماً',
        'variations.*.sale_price.min' => 'سعر الخصم يجب أن يكون أكبر من أو يساوي صفر',
        'variations.*.stock_quantity.required' => 'الكمية مطلوبة',
        'variations.*.stock_quantity.integer' => 'الكمية يجب أن تكون رقماً صحيحاً',
        'variations.*.stock_quantity.min' => 'الكمية يجب أن تكون أكبر من أو تساوي صفر',
        'variations.*.description.max' => 'الوصف يجب أن لا يتجاوز 500 حرف',
    ];

    public function mount()
    {
        $this->loadAttributesCount();
        $this->loadAttributesPage();
    }

    protected function loadAttributesCount()
    {
        $cacheKey = 'woocommerce_attributes_count';
        $this->totalAttributes = Cache::remember($cacheKey, 3600, function () {
            $woo = new WooCommerceService();
            return count($woo->getAttributesWithTerms());
        });
    }

    protected function loadAttributesPage()
    {
        if ($this->isLoading) {
            return;
        }

        $this->isLoading = true;

        $cacheKey = 'woocommerce_attributes_page_' . $this->currentPage;
        $attributesWithTerms = Cache::remember($cacheKey, 3600, function () {
            $woo = new WooCommerceService();
            $allAttributes = $woo->getAttributesWithTerms();
            $offset = ($this->currentPage - 1) * $this->perPage;
            return array_slice($allAttributes, $offset, $this->perPage);
        });

        foreach ($attributesWithTerms as $attribute) {
            if (!isset($attribute['id'])) {
                continue; // Skip attributes without ID
            }

            $this->loadedAttributes[] = $attribute;
            $this->attributeTerms[$attribute['id']] = $attribute['terms'] ?? [];
            $this->attributeLookup[$attribute['id']] = $attribute;

            if (isset($attribute['terms']) && is_array($attribute['terms'])) {
                foreach ($attribute['terms'] as $term) {
                    if (isset($term['id'])) {
                        $this->termLookup[$attribute['id']][$term['id']] = $term;
                    }
                }
            }
        }

        $this->isLoading = false;
    }

    public function loadMore()
    {
        if ($this->currentPage * $this->perPage < $this->totalAttributes) {
            $this->currentPage++;
            $this->loadAttributesPage();
        }
    }

    public function updated($propertyName)
    {
        if (str_starts_with($propertyName, 'selectedAttributes.')) {
            $this->generateVariations();
            $this->dispatch('attributesSelected', [
                'selectedAttributes' => $this->selectedAttributes,
                'attributeMap' => $this->attributeMap,
                'variations' => $this->variations
            ])->to('pages.product.add');
        }
        $this->validateOnly($propertyName);
    }

    public function generateVariations()
    {
        if (empty($this->selectedAttributes)) {
            $this->variations = [];
            return;
        }

        $this->variations = [];
        $attributeOptions = [];
        $this->attributeMap = [];

        // Pre-filter selected attributes to reduce processing
        $filteredAttributes = array_filter($this->selectedAttributes, function($termMap) {
            return !empty(array_filter($termMap));
        });

        if (empty($filteredAttributes)) {
            return;
        }

        foreach ($filteredAttributes as $attributeId => $termMap) {
            $termIds = array_keys(array_filter($termMap));
            if (empty($termIds)) {
                continue;
            }

            $terms = array_map(function ($termId) use ($attributeId) {
                return [
                    'id' => $termId,
                    'name' => $this->getTermName($attributeId, $termId)
                ];
            }, $termIds);

            if (!empty($terms)) {
                $attribute = $this->getAttributeById($attributeId);
                $attributeOptions[] = $terms;
                $this->attributeMap[] = [
                    'id' => $attributeId,
                    'name' => $attribute['name'] ?? ''
                ];
            }
        }

        if (!empty($attributeOptions)) {
            $combinations = $this->generateCombinations($attributeOptions);

            foreach ($combinations as $combo) {
                $this->variations[] = [
                    'sku' => '',
                    'regular_price' => '',
                    'sale_price' => '',
                    'stock_quantity' => 0,
                    'active' => true,
                    'length' => '',
                    'width' => '',
                    'height' => '',
                    'description' => '',
                    'options' => array_map(function($term) {
                        return $term['name'];
                    }, $combo)
                ];
            }
        }

        // إرسال التحديثات إلى المكون الأب
        $this->dispatch('attributesSelected', [
            'selectedAttributes' => $this->selectedAttributes,
            'attributeMap' => $this->attributeMap,
            'variations' => $this->variations
        ])->to('pages.product.add');
    }

    public function validateVariations()
    {
        if (empty($this->variations)) {
            return false;
        }

        return true;
    }

    #[On('requestLatestVariations')]
    public function sendLatestToParent()
    {
        if (!$this->validateVariations()) {
            return;
        }

        $this->dispatch('latestVariationsSent', [
            'variations' => array_map(fn($v) => (array) $v, $this->variations),
            'attributeMap' => array_map(fn($m) => (array) $m, $this->attributeMap),
        ])->to('pages.product.add');
    }

    protected function getAttributeById($id)
    {
        return $this->attributeLookup[$id] ?? null;
    }

    protected function getTermName($attributeId, $termId)
    {
        if (isset($this->termLookup[$attributeId][$termId])) {
            return $this->termLookup[$attributeId][$termId]['name'];
        }

        // Fallback to search in attributeTerms if not found in lookup
        foreach ($this->attributeTerms[$attributeId] ?? [] as $term) {
            if ($term['id'] == $termId) {
                return $term['name'];
            }
        }

        return '';
    }

    protected function generateCombinations($arrays)
    {
        if (empty($arrays)) {
            return [];
        }

        // Use a more efficient combination generation algorithm
        $result = [[]];
        $count = count($arrays);

        for ($i = 0; $i < $count; $i++) {
            $current = $arrays[$i];
            $temp = [];

            foreach ($result as $product) {
                foreach ($current as $item) {
                    $temp[] = array_merge($product, [$item]);
                }
            }

            $result = $temp;

            // If we have too many combinations, break early
            if (count($result) > 1000) {
                break;
            }
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.variation-manager');
    }
}


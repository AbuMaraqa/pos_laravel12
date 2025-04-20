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
    public $allRegularPrice = '';
    public $allSalePrice = '';
    public $allStockQuantity = '';
    public $productId;
    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

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

    public function mount($productId)
    {
        try {
            $this->productId = $productId;

            // Get product data and existing variations
            $product = $this->wooService->getProduct($this->productId);
            $existingVariations = $this->wooService->getVariationsByProductId($this->productId);

            // Get all available attributes with their terms
            $allAttributes = $this->wooService->getAttributesWithTerms();

            // Initialize attributes and terms
            foreach ($allAttributes as $attribute) {
                if (isset($attribute['id']) && isset($attribute['terms'])) {
                    $this->loadedAttributes[] = $attribute;
                    $this->attributeTerms[$attribute['id']] = $attribute['terms'];
                    $this->selectedAttributes[$attribute['id']] = [];
                }
            }

            // Process product attributes to mark which ones are used
            if (isset($product['attributes']) && is_array($product['attributes'])) {
                foreach ($product['attributes'] as $productAttr) {
                    if (isset($productAttr['id']) && isset($productAttr['options']) && is_array($productAttr['options'])) {
                        foreach ($productAttr['options'] as $option) {
                            // Find the term ID that matches this option
                            $termId = $this->findTermIdByName($productAttr['id'], $option);
                            if ($termId) {
                                $this->selectedAttributes[$productAttr['id']][$termId] = true;
                            }
                        }
                    }
                }
            }

            // Set attribute map from loaded attributes
            $this->attributeMap = array_map(function($attr) {
                return [
                    'id' => $attr['id'],
                    'name' => $attr['name']
                ];
            }, $this->loadedAttributes);

            // Generate initial variations
            $this->generateInitialVariations($existingVariations);

            logger()->info('VariationManager mounted', [
                'productId' => $this->productId,
                'selectedAttributes' => $this->selectedAttributes,
                'productAttributes' => $product['attributes'] ?? []
            ]);

        } catch (\Exception $e) {
            logger()->error('Error in VariationManager mount:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'حدث خطأ أثناء تحميل بيانات المنتج: ' . $e->getMessage());
        }
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

    public function generateVariations()
    {
        try {
            $this->generateInitialVariations();

            logger()->info('Variations generated', [
                'count' => count($this->variations),
                'variations' => $this->variations
            ]);

            session()->flash('success', sprintf('تم توليد %d متغير بنجاح', count($this->variations)));
            $this->dispatch('variationsGenerated', ['count' => count($this->variations)]);

        } catch (\Exception $e) {
            logger()->error('Error generating variations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'حدث خطأ أثناء توليد المتغيرات: ' . $e->getMessage());
        }
    }

    public function updated($propertyName)
    {
        if ($propertyName === 'allRegularPrice' && $this->allRegularPrice !== '') {
            foreach ($this->variations as $index => $variation) {
                $this->variations[$index]['regular_price'] = $this->allRegularPrice;
            }
        }

        if ($propertyName === 'allSalePrice' && $this->allSalePrice !== '') {
            foreach ($this->variations as $index => $variation) {
                $this->variations[$index]['sale_price'] = $this->allSalePrice;
            }
        }

        if ($propertyName === 'allStockQuantity' && $this->allStockQuantity !== '') {
            foreach ($this->variations as $index => $variation) {
                $this->variations[$index]['stock_quantity'] = $this->allStockQuantity;
            }
        }
    }

    public function validateVariations()
    {
        // Always return true
        return true;
    }

    #[On('requestLatestVariations')]
    public function sendLatestToParent()
    {
        // Always send data without validation
        $this->dispatch('latestVariationsSent', [
            'variations' => array_map(fn($v) => (array) $v, $this->variations),
            'attributeMap' => array_map(fn($m) => (array) $m, $this->attributeMap),
            'selectedAttributes' => $this->selectedAttributes,
        ])->to('pages.product.add');
    }

    protected function getAttributeById($id)
    {
        return $this->attributeLookup[$id] ?? null;
    }

    protected function getTermName($attributeId, $termId)
    {
        if (isset($this->attributeTerms[$attributeId])) {
            foreach ($this->attributeTerms[$attributeId] as $term) {
                if ($term['id'] == $termId) {
                    return $term['name'];
                }
            }
        }
        return null;
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

    protected function findAttributeId($attributeName)
    {
        foreach ($this->loadedAttributes as $attribute) {
            if (strtolower($attribute['name']) === strtolower($attributeName)) {
                return $attribute['id'];
            }
        }
        return null;
    }

    protected function findTermIdByName($attributeId, $termName)
    {
        if (isset($this->attributeTerms[$attributeId])) {
            foreach ($this->attributeTerms[$attributeId] as $term) {
                if (strtolower($term['name']) === strtolower($termName)) {
                    return $term['id'];
                }
            }
        }
        return null;
    }

    protected function generateInitialVariations($existingVariations = [])
    {
        try {
            // Get all selected attributes and their values
            $selectedAttributeValues = [];
            foreach ($this->selectedAttributes as $attrId => $values) {
                $selectedValues = array_keys(array_filter($values));
                if (!empty($selectedValues)) {
                    $selectedAttributeValues[$attrId] = $selectedValues;
                }
            }

            if (empty($selectedAttributeValues)) {
                return;
            }

            // Create a lookup for existing variations
            $existingVariationsLookup = [];
            foreach ($existingVariations as $variation) {
                $key = [];
                foreach ($variation['attributes'] as $attr) {
                    $key[] = $attr['option'];
                }
                $existingVariationsLookup[implode('_', $key)] = $variation;
            }

            // Generate combinations
            $combinations = $this->generateCombinations(array_values($selectedAttributeValues));
            $newVariations = [];

            foreach ($combinations as $combination) {
                $options = [];
                $attributes = [];
                $i = 0;

                foreach ($selectedAttributeValues as $attrId => $values) {
                    if (isset($combination[$i])) {
                        $termName = $this->getTermName($attrId, $combination[$i]);
                        if ($termName) {
                            $options[] = $termName;
                            $attributes[] = [
                                'id' => $attrId,
                                'name' => $this->getAttributeById($attrId)['name'] ?? '',
                                'option' => $termName
                            ];
                        }
                    }
                    $i++;
                }

                if (!empty($options)) {
                    $key = implode('_', $options);
                    if (isset($existingVariationsLookup[$key])) {
                        // Use existing variation data
                        $existingVar = $existingVariationsLookup[$key];
                        $newVariations[] = [
                            'id' => $existingVar['id'] ?? null,
                            'regular_price' => $existingVar['regular_price'] ?? '',
                            'sale_price' => $existingVar['sale_price'] ?? '',
                            'stock_quantity' => $existingVar['stock_quantity'] ?? '',
                            'description' => $existingVar['description'] ?? '',
                            'options' => $options,
                            'attributes' => $attributes
                        ];
                    } else {
                        // Create new variation
                        $newVariations[] = [
                            'regular_price' => '',
                            'sale_price' => '',
                            'stock_quantity' => '',
                            'description' => '',
                            'options' => $options,
                            'attributes' => $attributes
                        ];
                    }
                }
            }

            // Update variations
            $this->variations = $newVariations;

        } catch (\Exception $e) {
            logger()->error('Error generating initial variations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.variation-manager');
    }
}



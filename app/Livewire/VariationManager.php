<?php

namespace App\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use App\Services\WooCommerceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Livewire\WithFileUploads;

class VariationManager extends Component
{
    use WithFileUploads;

    public $productAttributes = [];
    public $attributeTerms = [];
    public $selectedAttributes = [];
    public $variations = [];
    public $attributeMap = [];
    public $variationImages = []; // To store image URLs for each variation
    public $variationImageFiles = []; // For file uploads
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

            // Debug log for variations
            logger()->info('Existing Variations', [
                'count' => count($existingVariations),
                'variations' => $existingVariations
            ]);

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

            // Debug log to see the product attributes
            logger()->info('Product Attributes', [
                'attributes' => $product['attributes'] ?? [],
                'variations' => count($existingVariations ?? []),
            ]);

            // Process product attributes to mark which ones are used
            if (isset($product['attributes']) && is_array($product['attributes'])) {
                foreach ($product['attributes'] as $productAttr) {
                    if (!isset($productAttr['id'])) {
                        logger()->warning('Attribute missing ID', ['attribute' => $productAttr]);
                        continue;
                    }

                    $attributeId = $productAttr['id'];

                    // إذا كانت الخيارات موجودة
                    if (isset($productAttr['options']) && is_array($productAttr['options'])) {
                        logger()->info('Processing attribute options', [
                            'attribute_id' => $attributeId,
                            'options' => $productAttr['options']
                        ]);

                        // للخصائص التي لها معرف، نتعامل معها بطريقة مختلفة
                        if (isset($this->attributeTerms[$attributeId])) {
                            foreach ($productAttr['options'] as $option) {
                                foreach ($this->attributeTerms[$attributeId] as $term) {
                                    if ($term['name'] === $option) {
                                        $this->selectedAttributes[$attributeId][$term['id']] = true;
                                        logger()->info('Matched option with term', [
                                            'option' => $option,
                                            'term_id' => $term['id'],
                                            'term_name' => $term['name']
                                        ]);
                                    }
                                }
                            }
                        }
                        // للخصائص المخصصة التي ليس لها معرف، نخزن القيم مباشرة
                        else {
                            $this->selectedAttributes[$attributeId] = $productAttr['options'];
                        }
                    }
                }

                // تحديث attributeMap لتعكس الخصائص المستخدمة في المنتج
                $this->attributeMap = array_map(function($attr) {
                    return [
                        'id' => $attr['id'],
                        'name' => $attr['name']
                    ];
                }, array_filter($this->loadedAttributes, function($attr) {
                    return !empty($this->selectedAttributes[$attr['id']]);
                }));
            }

            // Load existing variation images
            foreach ($existingVariations as $index => $variation) {
                // تحقق من وجود الصور للمتغير
                if (isset($variation['image']) && !empty($variation['image']['src'])) {
                    $variationId = $variation['id'];
                    $this->variationImages[$variationId] = $variation['image']['src'];

                    logger()->info('Loaded variation image', [
                        'variation_id' => $variationId,
                        'image_src' => $variation['image']['src']
                    ]);
                }
            }

            // Generate initial variations
            $this->generateInitialVariations($existingVariations);

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
        // إذا تم تحديث الخصائص المحددة، قم بتوليد المتغيرات تلقائيًا
        if (strpos($propertyName, 'selectedAttributes') === 0) {
            $this->generateInitialVariations();

            // إرسال حدث attributesSelected إلى مكون تعديل المنتج
            $this->dispatch('attributesSelected', [
                'selectedAttributes' => $this->selectedAttributes,
                'attributeMap' => $this->attributeMap
            ])->to('pages.product.edit');
        }

        // تحديث قيم المتغيرات عند تغيير القيم العامة
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
        $eventData = [
            'variations' => array_map(fn($v) => (array) $v, $this->variations),
            'attributeMap' => array_map(fn($m) => (array) $m, $this->attributeMap),
            'selectedAttributes' => $this->selectedAttributes,
        ];

        // إرسال البيانات إلى مكون إضافة المنتج
        $this->dispatch('latestVariationsSent', $eventData)->to('pages.product.add');

        // إرسال البيانات أيضاً إلى مكون تعديل المنتج
        $this->dispatch('latestVariationsSent', $eventData)->to('pages.product.edit');
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
            logger()->info('Starting generateInitialVariations', [
                'selectedAttributes' => $this->selectedAttributes
            ]);

            // Get all selected attributes and their values
            $selectedAttributeValues = [];
            foreach ($this->selectedAttributes as $attrId => $values) {
                // هناك طريقتان للعمل مع selectedAttributes
                // 1. كمصفوفة ترابطية (checkbox style: termId => true/false)
                // 2. كمصفوفة من الخيارات المحددة مسبقاً (من WooCommerce)
                logger()->info('Processing attribute values', [
                    'attribute_id' => $attrId,
                    'values' => $values
                ]);

                // إذا كانت مصفوفة ترابطية
                if (is_array($values) && !isset($values[0])) {
                    $selectedValues = array_keys(array_filter($values));
                    if (!empty($selectedValues)) {
                        $selectedAttributeValues[$attrId] = $selectedValues;
                    }
                }
                // إذا كانت مصفوفة من الخيارات
                else if (is_array($values) && !empty($values)) {
                    // نحتاج إلى تحويل أسماء الخيارات إلى معرفات الخيارات
                    $termIds = [];
                    foreach ($values as $optionName) {
                        foreach ($this->attributeTerms[$attrId] ?? [] as $term) {
                            if ($term['name'] === $optionName) {
                                $termIds[] = $term['id'];
                                break;
                            }
                        }
                    }
                    if (!empty($termIds)) {
                        $selectedAttributeValues[$attrId] = $termIds;
                    }
                }
            }

            logger()->info('Selected attribute values after processing', [
                'selectedAttributeValues' => $selectedAttributeValues
            ]);

            if (empty($selectedAttributeValues)) {
                logger()->warning('No selected attribute values found');
                return;
            }

            // تحديث attributeMap لتتضمن فقط الخصائص المحددة
            $attributeMap = [];
            foreach ($selectedAttributeValues as $attrId => $termIds) {
                foreach ($this->loadedAttributes as $attribute) {
                    if ($attribute['id'] == $attrId) {
                        $attributeMap[] = [
                            'id' => $attrId,
                            'name' => $attribute['name']
                        ];
                        break;
                    }
                }
            }
            $this->attributeMap = $attributeMap;

            logger()->info('Updated attributeMap', [
                'attributeMap' => $this->attributeMap
            ]);

            // Create a lookup for existing variations
            $existingVariationsLookup = [];
            foreach ($existingVariations as $variation) {
                $key = [];
                $variationId = $variation['id'] ?? null;

                foreach ($variation['attributes'] as $attr) {
                    $key[] = $attr['option'];
                }

                $keyString = implode('_', $key);
                $existingVariationsLookup[$keyString] = $variation;

                // Store the mapping between key and variation ID for image lookup
                if ($variationId && isset($this->variationImages[$variationId])) {
                    $existingVariationsLookup[$keyString]['image'] = $this->variationImages[$variationId];
                }
            }

            // Generate combinations based on attribute values
            $attributeValuesArray = [];
            foreach ($this->attributeMap as $index => $attribute) {
                $attrId = $attribute['id'];
                $termNames = [];

                // اذا كانت الخاصية موجودة في قائمة الخصائص المحددة
                if (isset($selectedAttributeValues[$attrId])) {
                    $termIds = $selectedAttributeValues[$attrId];

                    foreach ($termIds as $termId) {
                        foreach ($this->attributeTerms[$attrId] ?? [] as $term) {
                            if ($term['id'] == $termId) {
                                $termNames[] = $term['name'];
                                break;
                            }
                        }
                    }
                }

                if (!empty($termNames)) {
                    $attributeValuesArray[] = $termNames;
                }
            }

            logger()->info('Attribute values for combinations', [
                'attributeValuesArray' => $attributeValuesArray
            ]);

            $combinations = $this->generateCombinations($attributeValuesArray);
            logger()->info('Generated combinations', [
                'combinations' => $combinations
            ]);

            $newVariations = [];

            foreach ($combinations as $combination) {
                $options = $combination;
                $key = implode('_', $options);

                if (isset($existingVariationsLookup[$key])) {
                    // Use existing variation data
                    $existingVar = $existingVariationsLookup[$key];
                    $newVar = [
                        'id' => $existingVar['id'] ?? null,
                        'regular_price' => $existingVar['regular_price'] ?? '',
                        'sale_price' => $existingVar['sale_price'] ?? '',
                        'stock_quantity' => $existingVar['stock_quantity'] ?? '',
                        'description' => $existingVar['description'] ?? '',
                        'options' => $options,
                    ];

                    // إضافة الصورة إذا كانت موجودة
                    if (isset($existingVar['image'])) {
                        $newVar['image'] = $existingVar['image'];
                    } elseif (isset($existingVar['id']) && isset($this->variationImages[$existingVar['id']])) {
                        $newVar['image'] = $this->variationImages[$existingVar['id']];
                    }

                    $newVariations[] = $newVar;
                } else {
                    // Create new variation
                    $newVariations[] = [
                        'regular_price' => '',
                        'sale_price' => '',
                        'stock_quantity' => '',
                        'description' => '',
                        'options' => $options,
                        'image' => null
                    ];
                }
            }

            logger()->info('Created variations', [
                'count' => count($newVariations)
            ]);

            // Update variations
            $this->variations = $newVariations;

        } catch (\Exception $e) {
            logger()->error('Error generating initial variations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function uploadVariationImage($variationIndex)
    {
        try {
            if (!isset($this->variationImageFiles[$variationIndex])) {
                return;
            }

            $file = $this->variationImageFiles[$variationIndex];

            // Validate file
            $this->validate([
                "variationImageFiles.$variationIndex" => 'image|max:1024',
            ]);

            // Store temporarily
            $path = $file->store('variation-images-temp', 'public');
            $url = asset('storage/' . $path);

            // Store URL in variationImages
            $variationId = $this->variations[$variationIndex]['id'] ?? null;

            if ($variationId) {
                $this->variationImages[$variationId] = $url;
            }

            // Also store in the variation itself for display
            $this->variations[$variationIndex]['image'] = $url;

            logger()->info('Uploaded variation image', [
                'variationIndex' => $variationIndex,
                'variationId' => $variationId,
                'imageUrl' => $url
            ]);
        } catch (\Exception $e) {
            logger()->error('Error uploading variation image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function removeVariationImage($variationIndex)
    {
        try {
            $variationId = $this->variations[$variationIndex]['id'] ?? null;

            if ($variationId && isset($this->variationImages[$variationId])) {
                unset($this->variationImages[$variationId]);
            }

            // Also remove from the variation itself
            if (isset($this->variations[$variationIndex]['image'])) {
                unset($this->variations[$variationIndex]['image']);
            }

            logger()->info('Removed variation image', [
                'variationIndex' => $variationIndex,
                'variationId' => $variationId
            ]);
        } catch (\Exception $e) {
            logger()->error('Error removing variation image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function save()
    {
        try {
            if (empty($this->productId)) {
                throw new \Exception("Product ID is required");
            }

            logger()->info('Saving variations for product', [
                'productId' => $this->productId,
                'variationsCount' => count($this->variations)
            ]);

            // ١. تجهيز بيانات المتغيرات والخصائص
            $attributes = [];
            foreach ($this->attributeMap as $attribute) {
                $termNames = [];

                foreach ($this->variations as $variation) {
                    $index = array_search($attribute['name'], array_column($this->attributeMap, 'name'));
                    if ($index !== false && isset($variation['options'][$index])) {
                        $termNames[] = $variation['options'][$index];
                    }
                }

                // نزيل التكرار
                $termNames = array_unique($termNames);

                $attributes[] = [
                    'id' => $attribute['id'],
                    'variation' => true,
                    'options' => $termNames
                ];
            }

            // ٢. تجهيز بيانات المتغيرات
            $variationsData = [];
            foreach ($this->variations as $variation) {
                $variationData = [
                    'regular_price' => $variation['regular_price'],
                    'sale_price' => $variation['sale_price'],
                    'stock_quantity' => $variation['stock_quantity'],
                    'description' => $variation['description'],
                    'attributes' => []
                ];

                // إضافة البيانات إذا كان لدينا معرف للمتغيّر
                if (isset($variation['id']) && $variation['id']) {
                    $variationData['id'] = $variation['id'];
                }

                // إضافة الصورة إذا وجدت
                $variationId = $variation['id'] ?? null;
                if ($variationId && isset($this->variationImages[$variationId])) {
                    $variationData['image'] = ['src' => $this->variationImages[$variationId]];
                } elseif (isset($variation['image']) && $variation['image']) {
                    $variationData['image'] = ['src' => $variation['image']];
                }

                // إضافة الخصائص للمتغيّر
                foreach ($this->attributeMap as $index => $attribute) {
                    if (isset($variation['options'][$index])) {
                        $variationData['attributes'][] = [
                            'id' => $attribute['id'],
                            'option' => $variation['options'][$index]
                        ];
                    }
                }

                $variationsData[] = $variationData;
            }

            logger()->info('Prepared data for saving', [
                'attributesCount' => count($attributes),
                'variationsCount' => count($variationsData)
            ]);

            // ٣. حفظ البيانات في WooCommerce
            $data = [
                'attributes' => $attributes,
                'variations' => $variationsData
            ];

            $response = $this->wooService->updateProductAttributes($this->productId, $data);

            logger()->info('Saved variations', [
                'response' => $response
            ]);

            // ٤. تحديث واجهة المستخدم
            $this->dispatch('showAlert', [
                'type' => 'success',
                'message' => 'تم حفظ متغيرات المنتج بنجاح'
            ]);

            // ٥. إعادة تحميل البيانات من الخادم للتأكد من التحديث الصحيح
            $this->refreshData();

        } catch (\Exception $e) {
            logger()->error('Error saving variations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('showAlert', [
                'type' => 'error',
                'message' => 'حدث خطأ أثناء حفظ متغيرات المنتج: ' . $e->getMessage()
            ]);
        }
    }

    protected function refreshData()
    {
        try {
            // إعادة تحميل بيانات المتغيرات من الخادم
            $variations = $this->wooService->getVariationsByProductId($this->productId);
            $product = $this->wooService->getProduct($this->productId);

            logger()->info('Refreshed product data', [
                'product' => $product,
                'variationsCount' => count($variations)
            ]);

            // إعادة توليد المتغيرات
            $this->mount($this->productId);

        } catch (\Exception $e) {
            logger()->error('Error refreshing data', [
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





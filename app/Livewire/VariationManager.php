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

    public function mount($productId = null)
    {
        try {
            // تهيئة المتغيرات
            $this->productId = $productId;
            $this->variations = [];
            $this->variationImages = [];

            logger()->info('بدء تحميل مدير المتغيرات', [
                'productId' => $this->productId
            ]);

            // إذا كان هناك معرف منتج، نجلب بيانات المنتج والمتغيرات
            if ($this->productId) {
                // جلب بيانات المنتج والمتغيرات الموجودة
                $product = $this->wooService->getProduct($this->productId);
                $existingVariations = $this->wooService->getVariationsByProductId($this->productId);

                if (!$product) {
                    throw new \Exception("المنتج غير موجود");
                }

                // تسجيل عدد المتغيرات
                logger()->info('المتغيرات الموجودة', [
                    'count' => count($existingVariations)
                ]);

                // الحصول على الخصائص التي تستخدم للمتغيرات فقط
                $variationAttributes = [];
                if (isset($product['attributes']) && is_array($product['attributes'])) {
                    foreach ($product['attributes'] as $attribute) {
                        // نريد فقط خصائص المتغيرات (variation attributes)
                        if (isset($attribute['variation']) && $attribute['variation']) {
                            $variationAttributes[] = $attribute;
                        }
                    }
                }

                logger()->info('خصائص المتغيرات المستخدمة', [
                    'count' => count($variationAttributes),
                    'attributes' => $variationAttributes
                ]);
            }

            // جلب جميع الخصائص المتاحة في النظام
            $allAttributes = $this->wooService->getAttributesWithTerms(['per_page' => 100]);

            // تهيئة البيانات الضرورية
            $this->loadedAttributes = [];
            $this->attributeTerms = [];
            $this->selectedAttributes = [];
            $this->attributeMap = [];

            // تسجيل الخصائص المحملة
            logger()->info('تم تحميل الخصائص', [
                'count' => count($allAttributes)
            ]);

            // تحميل جميع الخصائص وقيمها
            foreach ($allAttributes as $attribute) {
                if (!isset($attribute['id']) || !isset($attribute['terms'])) {
                    continue;
                }

                // في حالة المنتج الجديد، نحمل جميع الخصائص المتاحة
                $isVariationAttribute = true;
                if ($this->productId) {
                    // في حالة التعديل، نتحقق مما إذا كانت الخاصية مستخدمة للمتغيرات
                    // Mohamad Maraqa This Is Important For Variable Product
                    // $isVariationAttribute = false;
                    foreach ($variationAttributes as $varAttr) {
                        if ($varAttr['id'] == $attribute['id']) {
                            $isVariationAttribute = true;
                            break;
                        }
                    }
                }

                // إذا كانت الخاصية مستخدمة للمتغيرات، أو كان هذا منتج جديد، حفظها
                if ($isVariationAttribute) {
                    $this->loadedAttributes[] = $attribute;
                    $this->attributeTerms[$attribute['id']] = $attribute['terms'];
                    $this->selectedAttributes[$attribute['id']] = [];

                    // إضافة إلى خريطة الخصائص
                    $this->attributeMap[] = [
                        'id' => $attribute['id'],
                        'name' => $attribute['name']
                    ];

                    // تهيئة نظام البحث عن الخصائص والقيم
                    $this->attributeLookup[$attribute['id']] = $attribute;
                    $this->termLookup[$attribute['id']] = [];

                    foreach ($attribute['terms'] as $term) {
                        if (isset($term['id'])) {
                            $this->termLookup[$attribute['id']][$term['id']] = $term;
                        }
                    }
                }
            }

            if ($this->productId) {
                // معالجة خصائص المتغيرات المحددة
                foreach ($variationAttributes as $varAttr) {
                    if (!isset($varAttr['id']) || !isset($varAttr['options'])) {
                        continue;
                    }

                    $attributeId = $varAttr['id'];
                    if (!isset($this->attributeTerms[$attributeId])) {
                        continue;
                    }

                    // تحديد الخيارات المحددة
                    foreach ($varAttr['options'] as $option) {
                        foreach ($this->attributeTerms[$attributeId] as $term) {
                            if ($term['name'] === $option) {
                                $this->selectedAttributes[$attributeId][$term['id']] = true;
                            }
                        }
                    }
                }

                // تحميل صور المتغيرات
                foreach ($existingVariations as $variation) {
                    // حفظ صورة المتغير حسب ID
                    if (isset($variation['id']) && isset($variation['image']) && !empty($variation['image']['src'])) {
                        $this->variationImages[$variation['id']] = $variation['image']['src'];
                    }

                    // استخراج الخيارات المرتبة حسب attributeMap
                    $options = [];
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

                    // استخراج role_values من meta_data
                    $roleValues = [];
                    if (!empty($variation['meta_data'])) {
                        foreach ($variation['meta_data'] as $meta) {
                            if ($meta['key'] === 'mrbp_role' && is_array($meta['value'])) {
                                foreach ($meta['value'] as $roleEntry) {
                                    if (is_array($roleEntry)) {
                                        $roleKey = array_key_first($roleEntry);
                                        if ($roleKey) {
                                            $roleValues[$roleKey] = $roleEntry['mrbp_regular_price'] ?? '';
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // بناء مصفوفة المتغير الجديد
                    $this->variations[] = [
                        'id' => $variation['id'] ?? null,
                        'options' => $options,
                        'regular_price' => $variation['regular_price'] ?? '',
                        'sale_price' => $variation['sale_price'] ?? '',
                        'stock_quantity' => $variation['stock_quantity'] ?? '',
                        'description' => $variation['description'] ?? '',
                        'sku' => $variation['sku'] ?? '',
                        'image' => $variation['image']['src'] ?? null,
                        'role_values' => $roleValues,
                    ];
                }


                // توليد المتغيرات
                // $this->generateInitialVariations($existingVariations);
                // dd($this->variations);
                logger()->info('اكتمل التحميل، تم توليد المتغيرات', [
                    'variationsCount' => count($this->variations)
                ]);
            } else {
                logger()->info('تم تحميل الخصائص للمنتج الجديد، انتظار اختيار المستخدم', [
                    'loadedAttributesCount' => count($this->loadedAttributes)
                ]);
            }
        } catch (\Exception $e) {
            logger()->error('خطأ في تحميل مدير المتغيرات:', [
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
            // Clear any existing variations first
            $this->variations = [];

            logger()->info('Generating variations from selected attributes', [
                'selectedAttributes' => $this->selectedAttributes,
                'attributeMap' => $this->attributeMap,
                'loadedAttributes' => $this->loadedAttributes,
                'productId' => $this->productId
            ]);

            // تحقق من وجود خصائص محددة
            $hasSelectedTerms = false;
            foreach ($this->selectedAttributes as $attrId => $terms) {
                if (is_array($terms)) {
                    foreach ($terms as $termId => $selected) {
                        if ($selected) {
                            $hasSelectedTerms = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$hasSelectedTerms) {
                logger()->warning('لا توجد خصائص محددة للمتغيرات. الرجاء اختيار خصائص أولاً.');
                session()->flash('error', 'لم يتم اختيار أي خصائص للمتغيرات. الرجاء اختيار خصائص أولاً.');
                return;
            }

            // Get existing variations
            $existingVariations = $this->wooService->getVariationsByProductId($this->productId);
            logger()->info('Existing variations retrieved', [
                'count' => count($existingVariations)
            ]);

            // Generate new variations
            $this->generateInitialVariations($existingVariations);

            logger()->info('Variations generated', [
                'count' => count($this->variations),
                'variations' => $this->variations
            ]);

            if (count($this->variations) > 0) {
                session()->flash('success', sprintf('تم توليد %d متغير بنجاح', count($this->variations)));
                $this->dispatch('variationsGenerated', ['count' => count($this->variations)]);
            } else {
                session()->flash('error', 'لم يتم اختيار أي خصائص للمتغيرات. الرجاء اختيار خصائص أولاً.');
            }

            // أرسل المتغيرات للأب
            $this->sendVariationsToParent();
        } catch (\Exception $e) {
            logger()->error('خطأ في توليد المتغيرات', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'حدث خطأ أثناء توليد المتغيرات: ' . $e->getMessage());
        }
    }

    public function sendVariationsToParent()
    {
        $this->dispatch('variationsSentToParent', ['variations' => $this->variations]);
    }

    public function updated($propertyName)
    {
        logger()->info('Property updated', [
            'property' => $propertyName
        ]);

        // If selected attributes are updated, regenerate variations
        if (strpos($propertyName, 'selectedAttributes') === 0) {
            $existingVariations = $this->wooService->getVariationsByProductId($this->productId);

            // ✅ هنا نمرر true لإعادة البناء الكامل (لأن المستخدم أزال اختيار)
            $this->generateInitialVariations($existingVariations, true);

            logger()->info('Regenerated variations after attribute selection change', [
                'count' => count($this->variations)
            ]);

            $this->dispatch('attributesSelected', [
                'selectedAttributes' => $this->selectedAttributes,
                'attributeMap' => $this->attributeMap
            ])->to('pages.product.edit');
        }

        // Update variation values when bulk values change
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
    public function sendLatestToParent($values)
    {
        try {
            logger()->info('تم استلام طلب المتغيرات من المكون الرئيسي');

            if (empty($this->variations)) {
                logger()->warning('لا توجد متغيرات لإرسالها');
                session()->flash('error', 'لا توجد متغيرات. يرجى إنشاء المتغيرات أولاً.');
                return;
            }

            // تحقق من صحة البيانات
            $hasEmptyRegularPrice = false;
            $hasEmptySku = false;
            foreach ($this->variations as $variation) {
                if (empty($variation['regular_price'])) {
                    $hasEmptyRegularPrice = true;
                }
                if (empty($variation['sku'])) {
                    $hasEmptySku = true;
                }
            }

            // للتشخيص: تسجيل بيانات عينة من المتغيرات
            logger()->info('عينة من المتغيرات المرسلة', [
                'total_variations' => count($this->variations),
                'sample_variation' => $this->variations[0] ?? [],
                'hasEmptyRegularPrice' => $hasEmptyRegularPrice,
                'hasEmptySku' => $hasEmptySku
            ]);

            // إعداد البيانات للإرسال
            $data = [
                'variations' => $this->variations,
                'attributeMap' => $this->attributeMap,
                'selectedAttributes' => $this->selectedAttributes,
                'timestamp' => now()->format('Y-m-d H:i:s')
            ];

            if ($values['page'] == 'edit') {
                $this->dispatch('latestVariationsSent', $data)->to('pages.product.edit');
            } else {
                $this->dispatch('latestVariationsSent', $data)->to('pages.product.add');
            }
            $this->dispatch('variationsReady', variations: $this->variations);
            logger()->info('تم إرسال المتغيرات بنجاح', [
                'variation_count' => count($this->variations),
                'attribute_map_count' => count($this->attributeMap)
            ]);

            if ($hasEmptyRegularPrice) {
                session()->flash('warning', 'تم إرسال المتغيرات، لكن بعضها ليس له سعر محدد. يرجى تحديد الأسعار لجميع المتغيرات.');
            } else if ($hasEmptySku) {
                session()->flash('warning', 'تم إرسال المتغيرات، لكن بعضها ليس له رمز SKU محدد. يرجى تحديد رمز SKU لجميع المتغيرات.');
            } else {
                session()->flash('success', 'تم إرسال المتغيرات بنجاح');
            }
        } catch (\Exception $e) {
            logger()->error('خطأ في إرسال المتغيرات', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'حدث خطأ أثناء إرسال المتغيرات: ' . $e->getMessage());
        }
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

    /**
     * توليد الضرب الديكارتي بين مجموعات القيم
     * @param array $arrays مصفوفة تحتوي على مجموعات القيم مع أسماء الخصائص
     * @return array نتيجة الضرب الديكارتي
     */
    protected function cartesian(array $arrays)
    {
        if (empty($arrays)) {
            return [];
        }

        // تحقق أن العناصر هي مصفوفات بالفعل
        foreach ($arrays as $key => $array) {
            if (!is_array($array) || empty($array)) {
                unset($arrays[$key]);
            }
        }

        if (empty($arrays)) {
            return [];
        }

        // الحصول على أسماء الخصائص (المفاتيح)
        $keys = array_keys($arrays);

        // نبدأ من مصفوفة فارغة
        $result = [[]];

        // لكل خاصية، نضيف قيمها إلى كل توليفة موجودة
        foreach ($arrays as $attributeName => $values) {
            $appends = [];

            foreach ($result as $combinations) {
                foreach ($values as $value) {
                    // نسخ التوليفة الحالية وإضافة القيمة الجديدة
                    $temp = $combinations;
                    $temp[$attributeName] = $value;
                    $appends[] = $temp;
                }
            }

            // نحدث مجموعة النتائج
            $result = $appends;
        }

        logger()->info('نتيجة الضرب الديكارتي', [
            'count' => count($result),
            'sample' => array_slice($result, 0, 2)
        ]);

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

    protected function generateInitialVariations($existingVariations = [], $reset = false)
    {
        try {
            logger()->info('بدء توليد المتغيرات', [
                'عدد الخصائص المحددة' => count($this->attributeMap),
                'existing_variations' => count($existingVariations)
            ]);

            if (empty($this->attributeMap)) {
                logger()->warning('لا توجد خصائص محددة للمتغيرات');
                if ($reset) {
                    $this->variations = [];
                }
                return;
            }

            $previousVariations = $this->variations;
            if ($reset) {
                $this->variations = [];
            }

            $attributeValues = [];
            foreach ($this->selectedAttributes as $attributeId => $terms) {
                $options = [];
                if (is_array($terms)) {
                    foreach ($terms as $termId => $isSelected) {
                        if ($isSelected && isset($this->attributeTerms[$attributeId])) {
                            $term = collect($this->attributeTerms[$attributeId])->firstWhere('id', $termId);
                            if ($term) {
                                $options[] = $term['name'];
                            }
                        }
                    }
                }
                if (!empty($options)) {
                    $attributeInfo = collect($this->attributeMap)->firstWhere('id', $attributeId);
                    if ($attributeInfo) {
                        $attributeValues[$attributeInfo['name']] = $options;
                    }
                }
            }

            if (empty($attributeValues)) {
                logger()->warning('لا توجد قيم خصائص محددة');
                return;
            }

            $combinations = $this->cartesian($attributeValues);
            logger()->info('تم توليد المتغيرات المحتملة', [
                'combinations' => count($combinations)
            ]);

            $newVariations = [];

            // تجهيز خريطة للقيم المدخلة يدويًا لتسريع البحث
            $manualMap = collect($previousVariations)->keyBy(fn($v) => md5(json_encode($v['options'] ?? [])));

            foreach ($combinations as $combination) {
                $options = [];
                foreach ($this->attributeMap as $attribute) {
                    $options[] = $combination[$attribute['name']] ?? '';
                }

                $variationData = [
                    'options' => $options,
                    'regular_price' => '',
                    'sale_price' => '',
                    'stock_quantity' => '',
                    'description' => '',
                    'sku' => '',
                    'image' => null,
                ];

                foreach ($existingVariations as $existingVariation) {
                    $varAttrOptions = [];
                    foreach ($this->attributeMap as $attr) {
                        $found = false;
                        foreach ($existingVariation['attributes'] as $attrItem) {
                            if ((isset($attrItem['id']) && $attrItem['id'] == $attr['id']) ||
                                (isset($attrItem['name']) && $attrItem['name'] == $attr['name'])
                            ) {
                                $varAttrOptions[] = $attrItem['option'] ?? '';
                                $found = true;
                                break;
                            }
                        }
                        if (!$found) $varAttrOptions[] = '';
                    }

                    if ($varAttrOptions === $options) {
                        $variationData['id'] = $existingVariation['id'] ?? null;
                        $variationData['regular_price'] = $existingVariation['regular_price'] ?? '';
                        $variationData['sale_price'] = $existingVariation['sale_price'] ?? '';
                        $variationData['stock_quantity'] = $existingVariation['stock_quantity'] ?? '';
                        $variationData['description'] = $existingVariation['description'] ?? '';
                        $variationData['sku'] = $existingVariation['sku'] ?? '';
                        if (isset($existingVariation['image']['src'])) {
                            $variationData['image'] = $existingVariation['image']['src'];
                            $this->variationImages[$existingVariation['id']] = $existingVariation['image']['src'];
                        }
                        break;
                    }
                }

                // دمج القيم التي أدخلها المستخدم سابقًا إن وُجدت
                $matchKey = md5(json_encode($options));
                if ($manualMap->has($matchKey)) {
                    $manualVar = $manualMap[$matchKey];
                    $variationData['regular_price'] = $manualVar['regular_price'] ?? $variationData['regular_price'];
                    $variationData['sale_price'] = $manualVar['sale_price'] ?? $variationData['sale_price'];
                    $variationData['stock_quantity'] = $manualVar['stock_quantity'] ?? $variationData['stock_quantity'];
                    $variationData['description'] = $manualVar['description'] ?? $variationData['description'];
                    $variationData['sku'] = $manualVar['sku'] ?? $variationData['sku'];
                    $variationData['image'] = $manualVar['image'] ?? $variationData['image'];
                }

                $newVariations[] = $variationData;
            }

            $this->variations = $newVariations;

            logger()->info('✅ تم توليد المتغيرات بنجاح', [
                'count' => count($this->variations),
                'sample' => $this->variations[0] ?? []
            ]);
        } catch (\Exception $e) {
            logger()->error('❌ خطأ في توليد المتغيرات', [
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
                throw new \Exception("معرف المنتج مطلوب");
            }

            logger()->info('بدء حفظ المتغيرات للمنتج', [
                'productId' => $this->productId,
                'variationsCount' => count($this->variations)
            ]);

            // التحقق من وجود متغيرات
            if (empty($this->variations)) {
                $this->dispatch('showAlert', [
                    'type' => 'error',
                    'message' => 'لا توجد متغيرات للحفظ. الرجاء توليد المتغيرات أولاً.'
                ]);
                return;
            }

            // التحقق من صحة بيانات المتغيرات المطلوبة
            $hasErrors = false;
            $errorMessages = [];

            foreach ($this->variations as $index => $variation) {
                if (empty($variation['regular_price'])) {
                    $hasErrors = true;
                    $errorMessages[] = 'يجب تحديد السعر العادي لجميع المتغيرات';
                    break;
                }

                if (!isset($variation['stock_quantity']) || $variation['stock_quantity'] === '') {
                    $hasErrors = true;
                    $errorMessages[] = 'يجب تحديد الكمية لجميع المتغيرات';
                    break;
                }

                if (empty($variation['sku'])) {
                    $hasErrors = true;
                    $errorMessages[] = 'يجب تحديد رمز SKU لجميع المتغيرات';
                    break;
                }
            }

            if ($hasErrors) {
                foreach ($errorMessages as $message) {
                    $this->dispatch('showAlert', [
                        'type' => 'error',
                        'message' => $message
                    ]);
                }
                return;
            }

            // 1. تجهيز بيانات الخصائص
            $attributes = [];
            foreach ($this->attributeMap as $attribute) {
                $attrId = $attribute['id'];
                $termNames = [];

                // جمع جميع القيم المستخدمة في المتغيرات لهذه الخاصية
                foreach ($this->variations as $variation) {
                    $optionIndex = array_search($attribute['name'], array_column($this->attributeMap, 'name'));
                    if ($optionIndex !== false && isset($variation['options'][$optionIndex])) {
                        $termNames[] = $variation['options'][$optionIndex];
                    }
                }

                // إزالة التكرار
                $termNames = array_unique($termNames);

                $attributes[] = [
                    'id' => $attrId,
                    'variation' => true,
                    'options' => $termNames
                ];
            }

            // 2. تجهيز بيانات المتغيرات
            $variationsData = [];
            foreach ($this->variations as $variation) {
                $variationData = [
                    'regular_price' => (string)$variation['regular_price'],
                    'stock_quantity' => (int)$variation['stock_quantity'],
                    'sku' => (string)$variation['sku'],
                    'attributes' => []
                ];

                // إضافة البيانات الاختيارية إذا كانت موجودة
                if (!empty($variation['sale_price'])) {
                    $variationData['sale_price'] = (string)$variation['sale_price'];
                }

                if (!empty($variation['description'])) {
                    $variationData['description'] = $variation['description'];
                }

                // إضافة المعرف إذا كان هذا متغير موجود
                if (isset($variation['id']) && !empty($variation['id'])) {
                    $variationData['id'] = $variation['id'];
                }

                // إضافة الصورة إذا كانت موجودة
                if (isset($variation['image']) && !empty($variation['image'])) {
                    $variationData['image'] = ['src' => $variation['image']];
                } elseif (isset($variation['id']) && isset($this->variationImages[$variation['id']])) {
                    $variationData['image'] = ['src' => $this->variationImages[$variation['id']]];
                }

                // إضافة خصائص هذا المتغير
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

            logger()->info('تم تجهيز البيانات للحفظ', [
                'attributesCount' => count($attributes),
                'variationsCount' => count($variationsData),
                'sample_variation' => $variationsData[0] ?? []
            ]);

            // 3. حفظ البيانات في ووكومرس
            $data = [
                'attributes' => $attributes,
                'variations' => $variationsData
            ];

            $response = $this->wooService->updateProductAttributes($this->productId, $data);

            logger()->info('تم حفظ المتغيرات', [
                'response' => $response
            ]);

            if (!isset($response['success']) || !$response['success']) {
                throw new \Exception($response['message'] ?? 'حدث خطأ غير معروف أثناء حفظ المتغيرات');
            }

            // 4. تحديث واجهة المستخدم
            $this->dispatch('showAlert', [
                'type' => 'success',
                'message' => 'تم حفظ متغيرات المنتج بنجاح'
            ]);

            // 5. تحديث البيانات من الخادم للتأكد من التحديث الصحيح
            $this->refreshData();
        } catch (\Exception $e) {
            logger()->error('خطأ في حفظ المتغيرات', [
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
            logger()->info('بدء تحديث بيانات المتغيرات');

            // إعادة تحميل المتغيرات من الخادم
            $existingVariations = $this->wooService->getVariationsByProductId($this->productId);

            logger()->info('تم الحصول على المتغيرات من الخادم', [
                'variationsCount' => count($existingVariations)
            ]);

            // مسح المتغيرات الحالية
            $this->variations = [];

            // تحديث صور المتغيرات من البيانات المحدثة
            $this->variationImages = [];
            foreach ($existingVariations as $variation) {
                if (isset($variation['id']) && isset($variation['image']) && !empty($variation['image']['src'])) {
                    $this->variationImages[$variation['id']] = $variation['image']['src'];
                }
            }

            // إعادة توليد المتغيرات مع البيانات الجديدة
            $this->generateInitialVariations($existingVariations);

            logger()->info('تم تحديث البيانات وإعادة توليد المتغيرات', [
                'count' => count($this->variations)
            ]);
        } catch (\Exception $e) {
            logger()->error('خطأ في تحديث البيانات', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('showAlert', [
                'type' => 'error',
                'message' => 'حدث خطأ أثناء تحديث البيانات: ' . $e->getMessage()
            ]);
        }
    }

    #[On('saveProduct')]
    public function handleSaveProduct()
    {
        logger()->info('تم استلام حدث حفظ المنتج في مدير المتغيرات', [
            'variations_count' => count($this->variations)
        ]);

        // تأكد من أن هناك متغيرات للحفظ
        if (empty($this->variations)) {
            logger()->warning('لا توجد متغيرات للحفظ عند حفظ المنتج');
            return;
        }

        // التحقق من بيانات المتغيرات قبل الحفظ
        $missingData = false;
        foreach ($this->variations as $index => $variation) {
            logger()->info('فحص بيانات المتغير #' . ($index + 1), [
                'regular_price' => $variation['regular_price'] ?? 'غير محدد',
                'sale_price' => $variation['sale_price'] ?? 'غير محدد',
                'stock_quantity' => $variation['stock_quantity'] ?? 'غير محدد',
                'sku' => $variation['sku'] ?? 'غير محدد',
            ]);

            if (empty($variation['regular_price']) || !isset($variation['stock_quantity']) || $variation['stock_quantity'] === '' || empty($variation['sku'])) {
                $missingData = true;
            }
        }

        if ($missingData) {
            logger()->warning('بعض المتغيرات تحتوي على بيانات ناقصة');
            $this->dispatch('showAlert', [
                'type' => 'warning',
                'message' => 'بعض المتغيرات تحتوي على بيانات غير مكتملة، يرجى التأكد من إدخال جميع البيانات المطلوبة قبل الحفظ'
            ]);
        }

        // قم بإرسال المتغيرات إلى المكون الرئيسي أولاً
        $this->sendLatestToParent();

        // ثم قم بحفظ المتغيرات
        $this->save();

        logger()->info('تم حفظ المتغيرات بنجاح عند حفظ المنتج');
    }

    public function render()
    {
        return view('livewire.variation-manager');
    }
}

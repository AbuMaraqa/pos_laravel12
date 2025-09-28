<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Livewire;
use Masmerise\Toaster\Toaster;
use Spatie\LivewireFilepond\WithFilePond;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Add extends Component
{
    use WithFilePond;

    public $productId;

    // بيانات المنتج الأساسية
    public $productName;
    public $productDescription;
    public $productType = 'simple';

    public $regularPrice;
    public $salePrice;
    public $sku;

    // إدارة المخزون
    public $isStockManagementEnabled = false;
    public $stockQuantity = null;
    public $allowBackorders = 'no';
    public $stockStatus = 'instock';
    public $soldIndividually = false;

    // تحميل الصور
    public $file;
    public $files = [];
    public $featuredImage = null;
    public $galleryImages = [];

    // الخصائص والمتغيرات - تبسيط البنية
    public $productAttributes = [];
    public $attributeTerms = [];
    public $selectedAttributes = []; // مصفوفة بسيطة: [attribute_id => [term_ids]]

    #[Locked]
    public $attributeMap = [];
    #[Locked]
    public $variations = [];

    // حالة الحفظ
    public $isSaving = false;
    public array $selectedCategories = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
        $this->fetchProductAttributes();
    }

    public function updated($field, $value)
    {
        if ($field === 'productType') {
            Log::info('Product Type Updated: ' . $value);
            $this->dispatch('productTypeChanged', $value)->to('tabs-component');

            // إعادة تعيين البيانات عند تغيير نوع المنتج
            if ($value !== 'variable') {
                $this->selectedAttributes = [];
                $this->variations = [];
                $this->attributeMap = [];
            }
        }
    }

    #[On('updateMultipleFieldsFromTabs')]
    public function updateFieldsFromTabs($data)
    {
        $this->regularPrice = $data['regularPrice'] ?? $this->regularPrice;
        $this->salePrice = $data['salePrice'] ?? $this->salePrice;
        $this->sku = $data['sku'] ?? $this->sku;
    }

    public function fetchProductAttributes()
    {
        try {
            $this->productAttributes = $this->wooService->getAttributes();

            foreach ($this->productAttributes as $attr) {
                $this->attributeTerms[$attr['id']] = $this->wooService->getTermsForAttribute($attr['id']);
            }
        } catch (\Exception $e) {
            Log::error('خطأ في جلب الخصائص: ' . $e->getMessage());
            $this->productAttributes = [];
            $this->attributeTerms = [];
        }
    }

    #[Computed]
    public function getCategories(): array
    {
        try {
            $categories = $this->wooService->getCategories(['per_page' => 100])['data'] ?? [];
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
        } catch (\Exception $e) {
            Log::error('خطأ في جلب التصنيفات: ' . $e->getMessage());
            return [];
        }
    }

    #[On('attributesSelected')]
    public function handleAttributesSelected($data)
    {
        $this->selectedAttributes = $data['selectedAttributes'] ?? [];
        $this->generateVariations();
    }

    #[On('variationsUpdated')]
    public function handleVariationsUpdated($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
    }

// أضف هذا الكود في دالة generateVariations() في Add.php

    public function generateVariations()
    {
        try {
            // تسجيل البيانات الأولية
            Log::info('=== بدء توليد المتغيرات - تشخيص مفصل ===', [
                'selectedAttributes' => $this->selectedAttributes,
                'attributeTerms_summary' => array_map(fn($terms) => [
                    'count' => count($terms),
                    'sample_names' => array_slice(array_column($terms, 'name'), 0, 10)
                ], $this->attributeTerms)
            ]);

            // تنظيف البيانات المحددة
            $filteredAttributes = [];
            foreach ($this->selectedAttributes as $attributeId => $termIds) {
                Log::info("معالجة الخاصية {$attributeId}:", [
                    'received_termIds' => $termIds,
                    'termIds_type' => gettype($termIds)
                ]);

                if (is_array($termIds) && !empty($termIds)) {
                    $cleanedTermIds = array_filter($termIds);
                    if (!empty($cleanedTermIds)) {
                        $filteredAttributes[$attributeId] = $cleanedTermIds;

                        Log::info("تم تصفية الخاصية {$attributeId}:", [
                            'original_count' => count($termIds),
                            'filtered_count' => count($cleanedTermIds),
                            'filtered_termIds' => $cleanedTermIds
                        ]);
                    }
                }
            }

            Log::info('البيانات المفلترة النهائية:', [
                'filteredAttributes' => $filteredAttributes
            ]);

            if (empty($filteredAttributes)) {
                $this->variations = [];
                $this->attributeMap = [];
                Log::warning('لا توجد خصائص محددة - إنهاء العملية');
                return;
            }

            // بناء خريطة الخصائص
            $this->attributeMap = [];
            $attributeOptions = [];

            foreach ($filteredAttributes as $attributeId => $termIds) {
                $attribute = collect($this->productAttributes)->firstWhere('id', $attributeId);
                if (!$attribute) {
                    Log::error("لم يتم العثور على الخاصية في النظام: {$attributeId}");
                    continue;
                }

                $terms = $this->attributeTerms[$attributeId] ?? [];
                $termNames = [];

                Log::info("تحويل IDs إلى أسماء للخاصية {$attributeId}:", [
                    'termIds_to_convert' => $termIds,
                    'available_terms' => array_map(fn($term) => [
                        'id' => $term['id'],
                        'name' => $term['name']
                    ], $terms)
                ]);

                foreach ($termIds as $termId) {
                    $term = collect($terms)->firstWhere('id', $termId);
                    if ($term) {
                        $termNames[] = $term['name'];
                        Log::info("✅ تم العثور على المصطلح:", [
                            'termId' => $termId,
                            'termName' => $term['name']
                        ]);
                    } else {
                        Log::error("❌ لم يتم العثور على المصطلح:", [
                            'termId' => $termId,
                            'available_term_ids' => array_column($terms, 'id')
                        ]);
                    }
                }

                if (!empty($termNames)) {
                    $attributeOptions[$attributeId] = $termNames;
                    $this->attributeMap[] = [
                        'id' => $attributeId,
                        'name' => $attribute['name']
                    ];

                    Log::info("✅ تم إعداد خيارات الخاصية {$attributeId}:", [
                        'attribute_name' => $attribute['name'],
                        'termNames' => $termNames,
                        'termNames_count' => count($termNames)
                    ]);
                } else {
                    Log::error("❌ لا توجد أسماء صالحة للخاصية {$attributeId}");
                }
            }

            Log::info('خيارات الخصائص النهائية:', [
                'attributeOptions' => $attributeOptions,
                'attributeMap' => $this->attributeMap
            ]);

            // توليد التركيبات
            if (!empty($attributeOptions)) {
                $combinations = $this->cartesian(array_values($attributeOptions));

                Log::info('تم توليد التركيبات:', [
                    'combinations_count' => count($combinations),
                    'sample_combinations' => array_slice($combinations, 0, 5)
                ]);

                $this->variations = array_map(function($combo) {
                    return [
                        'options' => is_array($combo) ? $combo : [$combo],
                        'sku' => '',
                        'regular_price' => '',
                        'sale_price' => '',
                        'stock_quantity' => 0,
                        'manage_stock' => true,
                        'active' => true,
                        'description' => '',
                    ];
                }, $combinations);

                Log::info('✅ تم توليد المتغيرات بنجاح:', [
                    'variations_count' => count($this->variations),
                    'sample_variation_options' => array_slice(array_column($this->variations, 'options'), 0, 5)
                ]);
            }

            // إرسال البيانات للمكون الفرعي
            $this->dispatch('variationsGenerated', [
                'variations' => $this->variations,
                'attributeMap' => $this->attributeMap
            ])->to('variation-manager');

            Log::info('=== انتهاء توليد المتغيرات بنجاح ===');

        } catch (\Exception $e) {
            Log::error('❌ خطأ في توليد المتغيرات: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            Toaster::error('حدث خطأ في توليد المتغيرات');
        }
    }

    public function syncBeforeSave()
    {
        if ($this->isSaving) return;

        try {
            $this->validateBasicFields();

            $this->isSaving = true;

            if ($this->productType === 'variable') {
                // طلب آخر تحديث للمتغيرات
                $this->dispatch('requestLatestVariations')->to('variation-manager');
                return; // ننتظر الرد
            }

            $this->saveProduct();

        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isSaving = false;
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    Toaster::error($message);
                }
            }
        } catch (\Exception $e) {
            $this->isSaving = false;
            Log::error('خطأ في التحقق من البيانات: ' . $e->getMessage());
            Toaster::error('حدث خطأ: ' . $e->getMessage());
        }
    }

    private function validateBasicFields()
    {
        $rules = [
            'productName' => 'required|string|min:3',
            'productType' => 'required|in:simple,variable,grouped,external',
            'selectedCategories' => 'required|array|min:1',
        ];

        $messages = [
            'productName.required' => 'اسم المنتج مطلوب',
            'productName.min' => 'يجب أن يكون اسم المنتج 3 أحرف على الأقل',
            'productType.required' => 'نوع المنتج مطلوب',
            'selectedCategories.required' => 'يجب اختيار تصنيف واحد على الأقل',
        ];

        // إضافة قواعد حسب نوع المنتج
        if ($this->productType === 'simple') {
            $rules['regularPrice'] = 'required|numeric|min:0';
            $messages['regularPrice.required'] = 'السعر العادي مطلوب للمنتج البسيط';
        }

        $this->validate($rules, $messages);
    }

    #[On('latestVariationsSent')]
    public function handleLatestVariations($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];

        // التحقق من صحة المتغيرات
        if ($this->productType === 'variable' && empty($this->variations)) {
            $this->isSaving = false;
            Toaster::error('يجب إنشاء متغيرات للمنتج المتعدد');
            return;
        }

        $this->saveProduct();
    }

    public function saveProduct()
    {
        try {
            Log::info('بدء حفظ المنتج', [
                'product_type' => $this->productType,
                'variations_count' => count($this->variations)
            ]);

            // إعداد بيانات المنتج الأساسية
            $productData = $this->prepareProductData();
            // إنشاء المنتج في ووردبريس
            $wooProduct = $this->wooService->post('products', $productData);

            $this->productId = $wooProduct['id'];

            Log::info('تم إنشاء المنتج في ووردبريس بنجاح', ['product_id' => $this->productId]);

            // حفظ المنتج في قاعدة البيانات المحلية
            $localProduct = $this->saveProductLocally($wooProduct);

            // إنشاء المتغيرات للمنتجات المتعددة
            if ($this->productType === 'variable' && !empty($this->variations)) {
                $this->createVariations($this->productId, $localProduct->id);
            }

            $this->isSaving = false;
            Toaster::success('✅ تم حفظ المنتج بنجاح في ووردبريس وقاعدة البيانات المحلية');
            $this->redirectRoute('product.index');

        } catch (\Exception $e) {
            $this->isSaving = false;
            Log::error('فشل في حفظ المنتج: ' . $e->getMessage());
            Toaster::error('❌ حدث خطأ في حفظ المنتج: ' . $e->getMessage());
        }
    }

    private function prepareProductData(): array
    {
        $data = [
            'name' => $this->productName,
            'type' => $this->productType,
            'description' => $this->productDescription ?? '',
            'sku' => $this->sku ?: null,
            'status' => 'publish',
            'manage_stock' => $this->isStockManagementEnabled,
            'stock_quantity' => $this->stockQuantity ?? null,
            'backorders' => $this->allowBackorders,
            'stock_status' => $this->stockStatus,
            'sold_individually' => $this->soldIndividually,
            'categories' => array_map(fn($id) => ['id' => $id], $this->selectedCategories),
        ];

        // إضافة الأسعار للمنتجات البسيطة
        if (in_array($this->productType, ['simple', 'external'])) {
            $data['regular_price'] = $this->regularPrice;
            $data['sale_price'] = $this->salePrice ?: '';
        }

        // إعداد خصائص المنتجات المتعددة
        if ($this->productType === 'variable' && !empty($this->attributeMap)) {
            $data['attributes'] = $this->prepareProductAttributes();
        }

        // إضافة الصور
        $images = $this->prepareProductImages();
        if (!empty($images)) {
            $data['images'] = $images;
        }

        return $data;
    }

    private function prepareProductAttributes(): array
    {
        Log::info('=== بدء تحضير خصائص المنتج للحفظ ===', [
            'attributeMap_count' => count($this->attributeMap),
            'variations_count' => count($this->variations),
            'attributeMap' => $this->attributeMap
        ]);

        $productAttributes = [];

        foreach ($this->attributeMap as $index => $attribute) {
            Log::info("معالجة الخاصية {$index}:", [
                'attribute_id' => $attribute['id'],
                'attribute_name' => $attribute['name']
            ]);

            // جمع جميع القيم الفريدة لهذه الخاصية من المتغيرات
            $options = collect($this->variations)
                ->pluck("options.{$index}")
                ->unique()
                ->values()
                ->filter()
                ->toArray();

            Log::info("خيارات الخاصية {$index}:", [
                'options' => $options,
                'options_count' => count($options),
                'all_variation_options' => collect($this->variations)->map(function($variation, $varIndex) use ($index) {
                    return [
                        'variation_index' => $varIndex,
                        'option_at_index_' . $index => $variation['options'][$index] ?? 'MISSING'
                    ];
                })->toArray()
            ]);

            if (!empty($options)) {
                $attributeData = [
                    'id' => $attribute['id'],
                    'variation' => true,
                    'visible' => true,
                    'options' => $options,
                ];

                $productAttributes[] = $attributeData;

                Log::info("✅ تم تحضير خاصية للحفظ:", [
                    'attribute_data' => $attributeData
                ]);
            } else {
                Log::warning("❌ تجاهل خاصية بدون خيارات:", [
                    'attribute_id' => $attribute['id'],
                    'attribute_name' => $attribute['name']
                ]);
            }
        }

        Log::info('=== انتهاء تحضير خصائص المنتج ===', [
            'prepared_attributes_count' => count($productAttributes),
            'prepared_attributes' => $productAttributes
        ]);

        return $productAttributes;
    }

    private function prepareProductImages(): array
    {
        $images = [];
        $position = 0;

        // الصورة الرئيسية
        if ($this->featuredImage) {
            $images[] = [
                'src' => $this->featuredImage,
                'position' => $position++
            ];
        } elseif ($this->file) {
            $uploadedImage = $this->uploadSingleImageSync();
            if ($uploadedImage) {
                $images[] = [
                    'id' => $uploadedImage['id'],
                    'src' => $uploadedImage['src'],
                    'position' => $position++
                ];
            }
        }

        // صور المعرض
        foreach ($this->galleryImages as $imageSrc) {
            $images[] = [
                'src' => $imageSrc,
                'position' => $position++
            ];
        }

        // رفع صور إضافية
        if (!empty($this->files)) {
            foreach ($this->files as $file) {
                $uploadedImage = $this->wooService->uploadImage($file);
                if (isset($uploadedImage['id'])) {
                    $images[] = [
                        'id' => $uploadedImage['id'],
                        'src' => $uploadedImage['src'],
                        'position' => $position++
                    ];
                }
            }
        }

        return $images;
    }

    /**
     * حفظ المنتج في قاعدة البيانات المحلية
     */
    private function saveProductLocally($wooProduct)
    {
        try {
            Log::info('بدء حفظ المنتج في قاعدة البيانات المحلية', [
                'woo_product_id' => $wooProduct['id'],
                'product_name' => $wooProduct['name']
            ]);

            // إعداد البيانات للحفظ المحلي
            $localProductData = [
                'name' => $wooProduct['name'],
                'slug' => $wooProduct['slug'] ?? Str::slug($wooProduct['name']),
                'sku' => $wooProduct['sku'],
                'type' => $wooProduct['type'],
                'status' => $wooProduct['status'] === 'publish' ? 'active' : $wooProduct['status'],
                'regular_price' => $this->parsePrice($wooProduct['regular_price']),
                'sale_price' => $this->parsePrice($wooProduct['sale_price']),
                'price' => $this->parsePrice($wooProduct['price']) ?: $this->parsePrice($wooProduct['regular_price']),
                'stock_status' => $wooProduct['stock_status'],
                'stock_quantity' => $this->parseQuantity($wooProduct['stock_quantity']),
                'manage_stock' => $wooProduct['manage_stock'],
                'weight' => $wooProduct['weight'] ?: null,
                'dimensions' => !empty($wooProduct['dimensions']) ? $wooProduct['dimensions'] : null,
                'description' => $wooProduct['description'] ?? '',
                'short_description' => $wooProduct['short_description'] ?? '',
                'featured_image' => $this->extractFeaturedImage($wooProduct),
                'gallery' => $this->extractGalleryImages($wooProduct),
                'categories' => $this->extractCategories($wooProduct),
                'tags' => $this->extractTags($wooProduct),
                'attributes' => $this->extractAttributes($wooProduct),
                'external_url' => $wooProduct['external_url'] ?? null,
                'button_text' => $wooProduct['button_text'] ?? null,
                'remote_wp_id' => $wooProduct['id'],
                'synced_at' => now(),
            ];

            // إنشاء المنتج في قاعدة البيانات المحلية
            $localProduct = Product::create($localProductData);

            Log::info('تم حفظ المنتج في قاعدة البيانات المحلية بنجاح', [
                'local_product_id' => $localProduct->id,
                'remote_wp_id' => $wooProduct['id']
            ]);

            return $localProduct;

        } catch (\Exception $e) {
            Log::error('فشل في حفظ المنتج محلياً: ' . $e->getMessage(), [
                'woo_product_id' => $wooProduct['id'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * استخراج الصورة الرئيسية من بيانات ووردبريس
     */
    private function extractFeaturedImage($wooProduct)
    {
        if (!empty($wooProduct['images']) && is_array($wooProduct['images'])) {
            $featuredImage = collect($wooProduct['images'])->firstWhere('position', 0);
            return $featuredImage['src'] ?? null;
        }
        return null;
    }

    /**
     * استخراج صور المعرض من بيانات ووردبريس
     */
    private function extractGalleryImages($wooProduct)
    {
        if (!empty($wooProduct['images']) && is_array($wooProduct['images'])) {
            return collect($wooProduct['images'])
                ->where('position', '>', 0)
                ->pluck('src')
                ->toArray();
        }
        return [];
    }

    /**
     * استخراج التصنيفات من بيانات ووردبريس
     */
    private function extractCategories($wooProduct)
    {
        if (!empty($wooProduct['categories']) && is_array($wooProduct['categories'])) {
            return collect($wooProduct['categories'])->map(function($category) {
                return [
                    'id' => $category['id'],
                    'name' => $category['name'],
                    'slug' => $category['slug'] ?? ''
                ];
            })->toArray();
        }
        return [];
    }

    /**
     * استخراج العلامات من بيانات ووردبريس
     */
    private function extractTags($wooProduct)
    {
        if (!empty($wooProduct['tags']) && is_array($wooProduct['tags'])) {
            return collect($wooProduct['tags'])->map(function($tag) {
                return [
                    'id' => $tag['id'],
                    'name' => $tag['name'],
                    'slug' => $tag['slug'] ?? ''
                ];
            })->toArray();
        }
        return [];
    }

    /**
     * استخراج الخصائص من بيانات ووردبريس
     */
    private function extractAttributes($wooProduct)
    {
        if (!empty($wooProduct['attributes']) && is_array($wooProduct['attributes'])) {
            return collect($wooProduct['attributes'])->map(function($attribute) {
                return [
                    'id' => $attribute['id'],
                    'name' => $attribute['name'],
                    'options' => $attribute['options'] ?? [],
                    'variation' => $attribute['variation'] ?? false,
                    'visible' => $attribute['visible'] ?? true
                ];
            })->toArray();
        }
        return [];
    }

    /**
     * تحليل وتنظيف قيم الأسعار
     */
    private function parsePrice($price)
    {
        if (empty($price) || $price === '' || $price === '0' || $price === 0) {
            return null;
        }

        // إزالة أي رموز عملة أو مسافات
        $cleanPrice = preg_replace('/[^0-9.]/', '', $price);

        // التحقق من أن القيمة رقمية صحيحة
        if (is_numeric($cleanPrice) && $cleanPrice > 0) {
            return (float) $cleanPrice;
        }

        return null;
    }

    /**
     * تحليل وتنظيف قيم الكميات
     */
    private function parseQuantity($quantity)
    {
        if (empty($quantity) || $quantity === '' || !is_numeric($quantity)) {
            return 0;
        }

        return (int) $quantity;
    }

    /**
     * حفظ المتغير في قاعدة البيانات المحلية
     */
    private function saveVariationLocally($wooVariation, $localProductId, $originalVariation, $index)
    {
        try {
            Log::info('بدء حفظ المتغير في قاعدة البيانات المحلية', [
                'woo_variation_id' => $wooVariation['id'],
                'local_parent_id' => $localProductId
            ]);

            // إعداد بيانات المتغير للحفظ المحلي
            $localVariationData = [
                'parent_id' => $localProductId,
                'name' => $this->generateVariationName($originalVariation, $index),
                'slug' => Str::slug($this->generateVariationName($originalVariation, $index)),
                'sku' => $wooVariation['sku'] ?? '',
                'type' => 'variation',
                'status' => $wooVariation['status'] === 'publish' ? 'active' : $wooVariation['status'],
                'regular_price' => $this->parsePrice($wooVariation['regular_price']),
                'sale_price' => $this->parsePrice($wooVariation['sale_price']),
                'price' => $this->parsePrice($wooVariation['price']) ?: $this->parsePrice($wooVariation['regular_price']),
                'stock_status' => $wooVariation['stock_status'] ?? 'instock',
                'stock_quantity' => $this->parseQuantity($wooVariation['stock_quantity']),
                'manage_stock' => $wooVariation['manage_stock'] ?? true,
                'weight' => $wooVariation['weight'] ?: null,
                'dimensions' => !empty($wooVariation['dimensions']) ? $wooVariation['dimensions'] : null,
                'description' => $wooVariation['description'] ?? '',
                'featured_image' => $this->extractFeaturedImage($wooVariation),
                'attributes' => $this->extractVariationAttributes($wooVariation),
                'remote_wp_id' => $wooVariation['id'],
                'synced_at' => now(),
            ];

            // إنشاء المتغير في قاعدة البيانات المحلية
            $localVariation = Product::create($localVariationData);

            Log::info('تم حفظ المتغير في قاعدة البيانات المحلية بنجاح', [
                'local_variation_id' => $localVariation->id,
                'remote_wp_id' => $wooVariation['id']
            ]);

            return $localVariation;

        } catch (\Exception $e) {
            Log::error('فشل في حفظ المتغير محلياً: ' . $e->getMessage(), [
                'woo_variation_id' => $wooVariation['id'] ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * توليد اسم المتغير بناءً على خصائصه
     */
    private function generateVariationName($variation, $index)
    {
        $baseName = $this->productName;
        $options = $variation['options'] ?? [];

        if (!empty($options)) {
            $optionsText = implode(' - ', array_filter($options));
            return $baseName . ' (' . $optionsText . ')';
        }

        return $baseName . ' - متغير ' . ($index + 1);
    }

    /**
     * استخراج خصائص المتغير من بيانات ووردبريس
     */
    private function extractVariationAttributes($wooVariation)
    {
        if (!empty($wooVariation['attributes']) && is_array($wooVariation['attributes'])) {
            return collect($wooVariation['attributes'])->map(function($attribute) {
                return [
                    'id' => $attribute['id'],
                    'name' => $attribute['name'] ?? '',
                    'option' => $attribute['option'] ?? ''
                ];
            })->toArray();
        }
        return [];
    }

    private function createVariations($wooProductId, $localProductId)
    {
        $successCount = 0;
        $errorCount = 0;

        foreach ($this->variations as $index => $variation) {
            try {
                $variationData = $this->prepareVariationData($variation, $index);

                if (empty($variationData['attributes'])) {
                    Log::warning('تجاهل المتغير بدون خصائص', ['index' => $index]);
                    continue;
                }

                // إنشاء المتغير في ووردبريس
                $wooVariation = $this->wooService->post("products/{$wooProductId}/variations", $variationData);
                Log::info('تم إنشاء المتغير في ووردبريس', [
                    'variation_id' => $wooVariation['id'],
                    'index' => $index
                ]);

                // حفظ المتغير في قاعدة البيانات المحلية
                $this->saveVariationLocally($wooVariation, $localProductId, $variation, $index);

                $successCount++;

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('فشل إنشاء المتغير', [
                    'index' => $index,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('انتهاء إنشاء المتغيرات', [
            'success' => $successCount,
            'errors' => $errorCount
        ]);

        if ($errorCount > 0) {
            Toaster::warning("تم إنشاء {$successCount} متغير بنجاح و {$errorCount} فشل");
        }
    }

    private function prepareVariationData($variation, $index): array
    {
        $attributes = [];

        foreach ($variation['options'] as $optIndex => $value) {
            if ($optIndex >= count($this->attributeMap)) continue;

            $attribute = $this->attributeMap[$optIndex];
            if (!empty($value) && isset($attribute['id'])) {
                $attributes[] = [
                    'id' => $attribute['id'],
                    'option' => $value,
                ];
            }
        }

        $variationData = [
            'regular_price' => !empty($variation['regular_price']) ? (string)$variation['regular_price'] : '0',
            'sale_price' => !empty($variation['sale_price']) ? (string)$variation['sale_price'] : '',
            'stock_quantity' => isset($variation['stock_quantity']) ? (int)$variation['stock_quantity'] : 0,
            'manage_stock' => $variation['manage_stock'] ?? true,
            'status' => 'publish',
            'attributes' => $attributes,
        ];

        if (!empty($variation['sku'])) {
            $variationData['sku'] = $variation['sku'];
        }

        if (!empty($variation['description'])) {
            $variationData['description'] = $variation['description'];
        }

        return $variationData;
    }

    protected function cartesian($arrays)
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

    // دوال رفع الصور
    public function updatedFile()
    {
        if ($this->file) {
            $this->uploadSingleImage();
        }
    }

    public function updatedFiles()
    {
        if (!empty($this->files)) {
            $this->uploadGalleryImages();
        }
    }

    public function uploadSingleImage()
    {
        try {
            if (!$this->file->isValid()) {
                throw new \Exception('الملف غير صالح');
            }

            $uploadedImage = $this->wooService->uploadImage($this->file);

            if (isset($uploadedImage['src'])) {
                $this->featuredImage = $uploadedImage['src'];
                $this->file = null;
                Toaster::success('تم رفع صورة الغلاف بنجاح');
            }
        } catch (\Exception $e) {
            Log::error('خطأ في رفع الصورة: ' . $e->getMessage());
            Toaster::error('حدث خطأ في رفع الصورة');
        }
    }

    private function uploadSingleImageSync()
    {
        try {
            if ($this->file && $this->file->isValid()) {
                return $this->wooService->uploadImage($this->file);
            }
        } catch (\Exception $e) {
            Log::error('خطأ في رفع الصورة المتزامن: ' . $e->getMessage());
        }
        return null;
    }

    public function uploadGalleryImages()
    {
        try {
            $uploadedCount = 0;
            foreach ($this->files as $file) {
                if (!$file->isValid()) continue;

                $uploadedImage = $this->wooService->uploadImage($file);
                if (isset($uploadedImage['src'])) {
                    $this->galleryImages[] = $uploadedImage['src'];
                    $uploadedCount++;
                }
            }

            $this->files = [];
            if ($uploadedCount > 0) {
                Toaster::success("تم رفع {$uploadedCount} صورة للمعرض بنجاح");
            }
        } catch (\Exception $e) {
            Log::error('خطأ في رفع صور المعرض: ' . $e->getMessage());
            Toaster::error('حدث خطأ في رفع صور المعرض');
        }
    }

    public function removeFeaturedImage()
    {
        $this->file = null;
        $this->featuredImage = null;
    }

    public function removeGalleryImage($index)
    {
        if (isset($this->galleryImages[$index])) {
            unset($this->galleryImages[$index]);
            $this->galleryImages = array_values($this->galleryImages);
        }
    }

    public function render()
    {
        return view('livewire.pages.product.add');
    }
}

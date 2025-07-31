<?php

namespace App\Livewire\Pages\Product;

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

    public function fetchProductAttributes()
    {
        $this->productAttributes = $this->wooService->getAttributes();

        foreach ($this->productAttributes as $attr) {
            $attributeId = $attr['id'];
            $this->attributeTerms[$attributeId] = $this->wooService->getTermsForAttribute($attributeId);
        }
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

                        // تحديد القيم المحددة
                        $this->selectedAttributes[$attributeId] = [];
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

                Log::info('Processing variation', [
                    'variation_id' => $variation['id'] ?? 'new',
                    'variation_attributes' => $variation['attributes'] ?? [],
                    'current_attributeMap' => $this->attributeMap
                ]);

                // ✅ ترتيب القيم حسب attributeMap مع تسجيل مفصل
                foreach ($this->attributeMap as $attr) {
                    $value = null;
                    $attributeId = $attr['id'];

                    // البحث في attributes المتغير
                    foreach ($variation['attributes'] as $vAttr) {
                        Log::info('Checking variation attribute', [
                            'variation_attr' => $vAttr,
                            'looking_for_id' => $attributeId,
                            'looking_for_name' => $attr['name']
                        ]);

                        // مقارنة بالـ ID أو الاسم
                        if ((isset($vAttr['id']) && $vAttr['id'] == $attributeId) ||
                            (isset($vAttr['name']) && $vAttr['name'] === $attr['name']) ||
                            (isset($vAttr['name']) && strtolower($vAttr['name']) === strtolower($attr['name']))) {
                            $value = $vAttr['option'] ?? null;
                            Log::info('Found matching attribute', [
                                'attribute_id' => $attributeId,
                                'found_value' => $value
                            ]);
                            break;
                        }
                    }

                    $options[] = $value ?? '';
                    Log::info('Added option', [
                        'attribute_name' => $attr['name'],
                        'option_value' => $value ?? 'empty'
                    ]);
                }

                // ✅ تحسين معالجة stock_quantity
                $stockQuantity = '';
                if (isset($variation['stock_quantity'])) {
                    $stockQuantity = $variation['stock_quantity'];
                    if (is_null($stockQuantity)) {
                        $stockQuantity = '';
                    }
                }

                $variationData = [
                    'id' => $variation['id'] ?? null,
                    'options' => $options,
                    'regular_price' => $variation['regular_price'] ?? '',
                    'sale_price' => $variation['sale_price'] ?? '',
                    'stock_quantity' => $stockQuantity,
                    'description' => $variation['description'] ?? '',
                    'sku' => $variation['sku'] ?? '',
                ];

                Log::info('Final variation data', [
                    'variation_id' => $variation['id'] ?? 'new',
                    'options' => $options,
                    'variation_data' => $variationData
                ]);

                $this->variations[] = $variationData;
            }

            Log::info('All variations processed', [
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

    #[On('updateMrbpPrice')]
    public function handleMrbpUpdate($data)
    {
        $this->mrbpData = $data['data'];
    }

    // ✅ استقبال تحديثات المتغيرات من VariationManager
    #[On('variationsUpdated')]
    public function handleVariationsUpdated($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
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
        if (isset($data['selectedAttributes'])) {
            $this->selectedAttributes = $data['selectedAttributes'];
        }
    }

    public function syncBeforeSave()
    {
        if ($this->productType === 'variable') {
            $this->dispatch('requestLatestVariations', ['page' => 'edit'])->to('variation-manager');
        } else {
            $this->save();
        }
    }

    public function prepareAttributes()
    {
        $attributes = [];

        if (empty($this->selectedAttributes) || empty($this->attributeMap)) {
            return $attributes;
        }

        foreach ($this->attributeMap as $index => $attribute) {
            $attributeId = $attribute['id'];
            $options = [];

            if (isset($this->selectedAttributes[$attributeId])) {
                $selectedTermIds = array_keys(array_filter($this->selectedAttributes[$attributeId]));

                if (!empty($selectedTermIds)) {
                    $terms = $this->attributeTerms[$attributeId];
                    foreach ($selectedTermIds as $termId) {
                        $term = collect($terms)->firstWhere('id', $termId);
                        if ($term) {
                            $options[] = $term['name'];
                        }
                    }
                }
            }

            if (!empty($options)) {
                $attributes[] = [
                    'id' => $attributeId,
                    'name' => $attribute['name'],
                    'position' => $index,
                    'visible' => true,
                    'variation' => true,
                    'options' => $options
                ];
            }
        }

        return $attributes;
    }

    public function save()
    {
        try {
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

            // معالجة المنتج المتغير
            if ($this->productType === 'variable') {
                $attributes = $this->prepareAttributes();
                if (!empty($attributes)) {
                    $productData['attributes'] = $attributes;
                }
            }

            // تحديث المنتج
            $updatedProduct = $this->wooService->updateProduct($this->productId, $productData);

            if (!$updatedProduct) {
                throw new \Exception('فشل تحديث المنتج الأساسي');
            }

            // تحديث MRBP
            if (!empty($this->mrbpData)) {
                $this->wooService->updateMrbpData($this->productId, $this->mrbpData);
            }

            // تحديث المتغيرات
            if ($this->productType === 'variable' && !empty($this->variations)) {
                foreach ($this->variations as $key => $variation) {
                    if (!empty($variation['regular_price'])) {
                        $this->variations[$key]['regular_price'] = (string) number_format(
                            (float) str_replace(',', '.', $variation['regular_price']),
                            2, '.', ''
                        );
                    }
                    if (!empty($variation['sale_price'])) {
                        $this->variations[$key]['sale_price'] = (string) number_format(
                            (float) str_replace(',', '.', $variation['sale_price']),
                            2, '.', ''
                        );
                    }
                    if (isset($this->variations[$key]['stock_quantity']) && $this->variations[$key]['stock_quantity'] !== '') {
                        $this->variations[$key]['stock_quantity'] = (int) $this->variations[$key]['stock_quantity'];
                    }
                }

                $this->wooService->syncVariations($this->productId, $this->variations);
            }

            Toaster::success('تم تحديث المنتج بنجاح');
            return redirect()->route('product.index');

        } catch (\Exception $e) {
            Log::error('خطأ في تحديث المنتج', [
                'product_id' => $this->productId,
                'error' => $e->getMessage()
            ]);
            session()->flash('error', 'فشل تعديل المنتج: ' . $e->getMessage());
        }
    }

    // باقي الدوال (الصور، التصنيفات، إلخ) بدون تغيير
    public function getCategories(): array
    {
        $categories = $this->wooService->getCategories(['per_page' => 100]);
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

    public function render()
    {
        return view('livewire.pages.product.edit');
    }
}

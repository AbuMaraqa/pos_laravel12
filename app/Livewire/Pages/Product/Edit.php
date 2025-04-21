<?php

namespace App\Livewire\Pages\Product;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Services\WooCommerceService;
use Illuminate\Support\Facades\Log;

class Edit extends Component
{
    use WithFileUploads;

    public $productId;
    public $productName;
    public $productDescription;
    public $productType = 'simple';
    public $regularPrice;
    public $salePrice;
    public $sku;
    public $stockQuantity;
    public $stockStatus;
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
            $this->attributeTerms[$attr['id']] = $this->wooService->getTermsForAttribute($attr['id']);
        }
    }

    public function generateVariations()
    {
        $filtered = [];

        foreach ($this->selectedAttributes as $attributeId => $termIds) {
            if (is_array($termIds) && count($termIds)) {
                $filtered[$attributeId] = $termIds;
            }
        }

        $this->selectedAttributes = $filtered;

        $attributeOptions = [];
        $this->attributeMap = []; // تأكد من تصفيرها أولاً

        foreach ($this->selectedAttributes as $attributeId => $termIds) {
            $terms = $this->attributeTerms[$attributeId] ?? [];
            $termNames = [];

            foreach ($termIds as $id) {
                $term = collect($terms)->firstWhere('id', $id);
                if ($term) {
                    $termNames[] = $term['name'];
                }
            }

            if (!empty($termNames)) {
                $attributeOptions[$attributeId] = $termNames;
                $this->attributeMap[] = [
                    'id' => $attributeId,
                    'name' => collect($this->productAttributes)->firstWhere('id', $attributeId)['name'] ?? 'خاصية',
                ];
            }
        }

        $combinations = $this->cartesian(array_values($attributeOptions));
        $this->variations = array_map(fn($combo) => [
            'options' => $combo,
            'sku' => '',
            'regular_price' => '',
            'sale_price' => '',
            'stock_quantity' => '',
            'active' => true,
            'length' => '',
            'width' => '',
            'height' => '',
            'description' => '',
        ], $combinations);
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

    protected function loadProduct()
    {
        $product = $this->wooService->getProduct($this->productId);

        if (!$product) {
            session()->flash('error', 'Product not found');
            return redirect()->route('products.index');
        }

        // تسجيل بيانات المنتج للتشخيص
        \Illuminate\Support\Facades\Log::info('Product data loaded', [
            'product_id' => $this->productId,
            'product_type' => $product['type'],
            'has_attributes' => !empty($product['attributes']),
            'attributes_count' => count($product['attributes'] ?? []),
            'attributes' => $product['attributes'] ?? []
        ]);

        // Load basic product data
        $this->productName = $product['name'];
        $this->productDescription = $product['description'];
        $this->productType = $product['type'];
        $this->regularPrice = $product['regular_price'];
        $this->salePrice = $product['sale_price'];
        $this->sku = $product['sku'];
        $this->stockQuantity = $product['stock_quantity'];
        $this->stockStatus = $product['stock_status'];

        // Load categories
        $this->selectedCategories = collect($product['categories'])->pluck('id')->toArray();

        // Load images
        if (!empty($product['images'])) {
            $this->featuredImage = $product['images'][0]['src'] ?? null;
            $this->galleryImages = collect($product['images'])
                ->slice(1)
                ->pluck('src')
                ->toArray();
        }

        // Load variations if it's a variable product
        if ($this->productType === 'variable') {
            if (!empty($product['attributes'])) {
                // تجهيز الخصائص للتعديل
                foreach ($product['attributes'] as $attribute) {
                    if (isset($attribute['id']) && !empty($attribute['options'])) {
                        $attributeId = $attribute['id'];
                        // تحويل خيارات الخاصية إلى مصفوفة ترابطية (id => true)
                        $this->selectedAttributes[$attributeId] = $attribute['options'];

                        // تأكد من تحميل شروط الخاصية إذا لم تكن محملة بعد
                        if (empty($this->attributeTerms[$attributeId])) {
                            $this->attributeTerms[$attributeId] = $this->wooService->getTermsForAttribute($attributeId);
                        }

                        // إضافة الخاصية إلى خريطة الخصائص
                        $this->attributeMap[] = [
                            'id' => $attributeId,
                            'name' => $attribute['name']
                        ];
                    }
                }

                \Illuminate\Support\Facades\Log::info('Processed attributes', [
                    'selected_attributes' => $this->selectedAttributes,
                    'attribute_map' => $this->attributeMap
                ]);
            }
        }

        // Load MRBP data if exists
        $this->loadMrbpData();
    }

    protected function loadMrbpData()
    {
        // Load MRBP data from your custom storage
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
    }

    #[On('updateMrbpPrice')]
    public function handleMrbpUpdate($data)
    {
        $this->mrbpData = $data['data'];
    }

    #[On('latestVariationsSent')]
    public function handleVariationsUpdate($data)
    {
        try {
            // تسجيل الاستجابة التي تم استلامها
            \Illuminate\Support\Facades\Log::info('Received variations', ['data' => $data]);

            $this->variations = $data['variations'] ?? [];
            $this->attributeMap = $data['attributeMap'] ?? [];
            $this->selectedAttributes = $data['selectedAttributes'] ?? [];

            // بعد تحديث البيانات، نقوم بحفظ المنتج
            $this->save();
        } catch (\Exception $e) {
            session()->flash('error', 'خطأ في handleVariationsUpdate: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Error in handleVariationsUpdate', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    #[On('attributesSelected')]
    public function handleAttributesSelected($data)
    {
        if (isset($data['selectedAttributes']) && is_array($data['selectedAttributes'])) {
            $this->selectedAttributes = $data['selectedAttributes'];

            // تأكد من أن attributeTerms موجودة للخصائص المحددة
            foreach (array_keys($this->selectedAttributes) as $attributeId) {
                if (!isset($this->attributeTerms[$attributeId])) {
                    $this->attributeTerms[$attributeId] = $this->wooService->getTermsForAttribute($attributeId);
                }
            }
        }

        if (isset($data['attributeMap']) && is_array($data['attributeMap'])) {
            $this->attributeMap = $data['attributeMap'];
        }
    }

    public function syncBeforeSave()
    {
        try {
            if ($this->productType === 'variable') {
                $this->dispatch('requestLatestVariations')->to('variation-manager');
                session()->flash('info', 'طلب تحديث المتغيرات...');
                // لا نقوم بإستدعاء save() هنا، لأن handleVariationsUpdate ستقوم بذلك
            } else {
                $this->save();
            }
        } catch (\Exception $e) {
            session()->flash('error', 'خطأ في syncBeforeSave: ' . $e->getMessage());
            // حفظ التفاصيل الكاملة للخطأ في سجل الأخطاء للتحقق لاحقاً
            \Illuminate\Support\Facades\Log::error('Error in syncBeforeSave', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function prepareAttributes()
    {
        if (empty($this->attributeMap) || empty($this->selectedAttributes)) {
            return [];
        }

        $attributes = [];

        foreach ($this->attributeMap as $index => $attribute) {
            if (isset($attribute['id'])) {
                $attributeId = $attribute['id'];

                // تحويل المفاتيح التي تم تحديدها إلى قائمة من القيم
                $options = [];
                if (isset($this->selectedAttributes[$attributeId])) {
                    // في حالة selectedAttributes[$attributeId] هي مصفوفة ترابطية (checkbox style: id => true)
                    if (is_array($this->selectedAttributes[$attributeId])) {
                        foreach ($this->selectedAttributes[$attributeId] as $termId => $isChecked) {
                            if ($isChecked) {
                                // ابحث عن اسم الخاصية من معرفها
                                foreach ($this->attributeTerms[$attributeId] ?? [] as $term) {
                                    if ($term['id'] == $termId) {
                                        $options[] = $term['name'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    // في حالة selectedAttributes[$attributeId] هي قائمة من القيم المحددة مباشرة
                    else {
                        $options = $this->selectedAttributes[$attributeId];
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
                'categories' => array_map(fn($id) => ['id' => $id], $this->selectedCategories),
            ];

            // أضف السعر إن وجد
            if (!empty($this->regularPrice)) {
                $productData['regular_price'] = $this->regularPrice;
            }

            if (!empty($this->salePrice)) {
                $productData['sale_price'] = $this->salePrice;
            }

            // أضف الكمية إذا محددة
            if (!is_null($this->stockQuantity)) {
                $productData['stock_quantity'] = (int) $this->stockQuantity;
            }

            // أضف SKU إذا موجود
            if (!empty($this->sku)) {
                $productData['sku'] = $this->sku;
            }

            // أضف الخصائص إذا كان المنتج متغير
            if ($this->productType === 'variable') {
                $attributes = $this->prepareAttributes();
                \Illuminate\Support\Facades\Log::info('Prepared attributes', ['attributes' => $attributes]);
                $productData['attributes'] = $attributes;
            }

            $log = "بيانات المنتج قبل التحديث: " . json_encode($productData);
            \Illuminate\Support\Facades\Log::info($log);

            // يمكنك استخدام dd للتوقف مؤقتاً والتحقق من البيانات قبل الحفظ
            // dd($productData);

            $updatedProduct = $this->wooService->updateProduct($this->productId, $productData);

            // تحديث تسعيرة MRBP إن وجدت
            if (!empty($this->mrbpData)) {
                $this->wooService->updateMrbpData($this->productId, $this->mrbpData);
            }

            // تحديث المتغيرات في حال كان المنتج متغير
            if ($this->productType === 'variable' && !empty($this->variations)) {
                $this->wooService->syncVariations($this->productId, $this->variations);
            }

            dd($productData);

            \Illuminate\Support\Facades\Log::info('Product updated successfully', ['product_id' => $this->productId]);
            session()->flash('success', 'تم تعديل المنتج بنجاح');
            return redirect()->route('products.index');

        } catch (\Exception $e) {
            // تسجيل الخطأ بشكل مفصل
            \Illuminate\Support\Facades\Log::error('Error updating product', [
                'product_id' => $this->productId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // عرض رسالة خطأ مفصلة للمستخدم
            $error = "فشل تعديل المنتج: " . $e->getMessage() .
                     " في الملف " . $e->getFile() .
                     " السطر " . $e->getLine();

            session()->flash('error', $error);

            // يمكنك أيضًا عرض النتيجة مباشرة (ليس مستحسنًا في الإنتاج)
            // dd("خطأ: " . $e->getMessage(), $e->getTraceAsString());
        }
    }

    public function removeFeaturedImage()
    {
        $this->featuredImage = null;
        $this->file = null;
    }

    public function removeGalleryImage($index)
    {
        unset($this->galleryImages[$index]);
        $this->galleryImages = array_values($this->galleryImages);
    }

    public function updatedProductType($value)
    {
        $this->dispatch('productTypeChanged', $value)->to('tabs-component');
    }

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

    public function render()
    {
        return view('livewire.pages.product.edit');
    }
}

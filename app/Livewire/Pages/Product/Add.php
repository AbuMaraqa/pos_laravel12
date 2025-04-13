<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Livewire\Livewire;
use Masmerise\Toaster\Toaster;
use Spatie\LivewireFilepond\WithFilePond;

class Add extends Component
{
    use WithFilePond;

    public $productId;                         // ID المنتج الجديد بعد الحفظ

// بيانات المنتج الأساسية
    public $productName;                       // اسم المنتج
    public $productDescription;
    public $productType = 'simple';

    public $regularPrice;
    public $salePrice;                        // سعر الخصم
    public $sku;                              // SKU الخاص بالمنتج

// إدارة المخزون
    public $isStockManagementEnabled = false; // هل يتم إدارة المخزون؟
    public $stockQuantity = null;             // كمية المخزون
    public $allowBackorders = false;          // السماح بالطلبات الخلفية
    public $stockStatus = 'instock';          // حالة التوفر في المخزون
    public $soldIndividually = false;         // هل يباع بشكل فردي فقط؟

// تحميل الصور
    public $file;                             // صورة الغلاف
    public $files = [];                       // صور المعرض

// الخصائص والمتغيرات
    public $productAttributes = [];           // قائمة جميع الخصائص (attributes)
    public $attributeTerms = [];              // قائمة جميع القيم (terms) الخاصة بكل خاصية
    public $selectedAttributes = [];          // الخصائص والقيم التي تم اختيارها من قبل المستخدم
    #[Locked]
    public $attributeMap = [];                // خريطة الخصائص (id + name) حسب الترتيب
    #[Locked]
    public $variations = [];                  // جميع المتغيرات المولدة بناءً على الخصائص المحددة

// حالة الحفظ
    public $isSaving = false;                 // تستخدم لمنع التكرار عند الحفظ

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
//        $this->fetchProductAttributes();
    }

    #[On('updateMultipleFieldsFromTabs')]
    public function updateFieldsFromTabs($data)
    {
        $this->regularPrice = $data['regularPrice'] ?? null;
        $this->salePrice = $data['salePrice'] ?? null;
        $this->sku = $data['sku'] ?? null;
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
        $this->validate([
            'named' => 'required|string|max:255',
        ]);

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

    #[Computed]
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

    #[On('variationsGenerated')]
    public function setVariations($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
    }

    #[On('continueProductSave')]
    public function saveProduct()
    {
        $this->validate([
            'categoryId' => 'required|integer',
        ]);

        $woo = $this->wooService;

        try {
            $productAttributes = [];
            $defaultAttributes = [];
            $attributeMap = array_values($this->attributeMap);

            foreach ($attributeMap as $index => $attribute) {
                $options = collect($this->variations)->pluck("options.$index")->unique()->values()->toArray();

                if (!empty($options)) {
                    $productAttributes[] = [
                        'id' => $attribute['id'],
                        'variation' => true,
                        'visible' => true,
                        'options' => $options,
                    ];

                    $defaultAttributes[] = [
                        'id' => $attribute['id'],
                        'option' => $options[0],
                    ];
                }
            }

            $data = [
                'name' => $this->productName ?? 'منتج بدون اسم',
                'type' => $this->productType ?? 'simple',
                'description' => $this->productDescription ?? '',
                'short_description' => $this->productShortDescription ?? '',
                'sku' => $this->sku ?: null,
                'status' => 'publish',
                'manage_stock' => $this->isStockManagementEnabled,
                'stock_quantity' => $this->stockQuantity ?? null,
                'backorders' => $this->allowBackorders ? 'yes' : 'no',
                'stock_status' => $this->stockStatus,
                'sold_individually' => $this->soldIndividually,
            ];

            // ✅ أسعار خاصة للأنواع التي تدعم السعر
            if (in_array($this->productType, ['simple', 'external'])) {
                $data['price'] = $this->regularPrice ?: '0';
                $data['regular_price'] = $this->regularPrice ?: '0';
                $data['sale_price'] = $this->salePrice ?: '0';
            }

            // ✅ إعدادات خاصة بمنتج خارجي
            if ($this->productType === 'external') {
                $data['external_url'] = $this->externalUrl ?? '';
                $data['button_text'] = $this->buttonText ?? 'Buy Now';
            }

            // ✅ إعدادات المتغيرات
            if ($this->productType === 'variable') {
                $data['attributes'] = $productAttributes;
                $data['default_attributes'] = $defaultAttributes;
            }

            // ✅ التصنيفات والصور إذا حابب تضيفهم لاحقاً
            // $data['categories'] = [['id' => 9], ['id' => 14]];
            // $data['images'] = [['src' => 'https://example.com/image.jpg']];

            $product = $woo->post('products', $data);
            $this->productId = $product['id'];

            // 🧩 إنشاء المتغيرات في حالة variable
            if ($this->productType === 'variable') {
                foreach ($this->variations as $variation) {
                    $attributes = [];

                    foreach ($variation['options'] as $index => $value) {
                        $attribute = $attributeMap[$index] ?? null;

                        if ($attribute) {
                            $attributes[] = [
                                'id' => $attribute['id'],
                                'option' => $value,
                            ];
                        }
                    }

                    $woo->post("products/{$product['id']}/variations", [
                        'sku' => $variation['sku'],
                        'regular_price' => $variation['regular_price'] ?: '0',
                        'sale_price' => $variation['sale_price'] ?: '',
                        'stock_quantity' => $variation['stock_quantity'] ?: 0,
                        'manage_stock' => true,
                        'status' => $variation['active'] ? 'publish' : 'private',
                        'attributes' => $attributes,
                        'dimensions' => [
                            'length' => $variation['length'] ?: '',
                            'width' => $variation['width'] ?: '',
                            'height' => $variation['height'] ?: '',
                        ],
                        'description' => $variation['description'] ?: '',
                    ]);
                }
            }

            Toaster::success('✅ تم حفظ المنتج بنجاح');
            $this->redirectRoute('product.index');

        } catch (\Exception $e) {
            $this->isSaving = false;

            Toaster::error('❌ حدث خطاء في حفظ المنتج: ' . $e->getMessage());
        }
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

    #[On('latestVariationsSent')]
    public function handleLatestVariations($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];

        // الآن نكمل حفظ المنتج
        $this->saveProduct();
    }

    public function syncBeforeSave()
    {
        if ($this->isSaving) return; // 🚫 لو جاري الحفظ لا تعمل شي

        $this->isSaving = true;
        $this->dispatch('requestLatestVariations')->to('variation-manager');
    }

    public function render()
    {
        return view('livewire.pages.product.add');
    }
}

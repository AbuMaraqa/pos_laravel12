<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Attributes\On;
use Livewire\Component;
use Spatie\LivewireFilepond\WithFilePond;

class Add extends Component
{
    use WithFilePond;

    public $file;

    // بيانات المنتج
    public $productId;
    public $productName;
    public $productDescription;
    public $regularPrice;
    public $salePrice;
    public $sku;

    // المخزون
    public $isStockManagementEnabled = false;
    public $stockQuantity = null;
    public $allowBackorders = false;
    public $stockStatus = 'instock';
    public $soldIndividually = false;

    // الخصائص والمتغيرات
    public $productAttributes = [];
    public $attributeTerms = [];
    public $selectedAttributes = [];
    public $attributeMap = [];
    public $variations = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
        $this->fetchProductAttributes();
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

    #[On('variationsGenerated')]
    public function setVariations($data)
    {
        $this->variations = $data['variations'] ?? [];
        $this->attributeMap = $data['attributeMap'] ?? [];
    }

    public function saveProduct()
    {
        $this->requestLatestVariations();
        $woo = $this->wooService;

        try {
            // 🧠 تحضير الخصائص والقيم الافتراضية
            $productAttributes = [];
            $defaultAttributes = [];

            // التأكد من ترتيب attributeMap الصحيح
            $attributeMap = array_values($this->attributeMap);

            foreach ($attributeMap as $index => $attribute) {
                // الحصول على كل القيم لهذا الـ attribute من كل المتغيرات
                $options = collect($this->variations)
                    ->pluck("options.$index")
                    ->unique()
                    ->values()
                    ->toArray();

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

            // 🛠 إنشاء المنتج
            $data = [
                'name' => $this->productName ?? 'منتج بدون اسم',
                'type' => 'variable',
                'description' => $this->productDescription,
                'sku' => $this->sku ?: null,
                'status' => 'publish',
                'manage_stock' => $this->isStockManagementEnabled,
                'stock_quantity' => $this->stockQuantity ?? null,
                'backorders' => $this->allowBackorders ? 'yes' : 'no',
                'stock_status' => $this->stockStatus,
                'sold_individually' => $this->soldIndividually,
                'attributes' => $productAttributes,
                'default_attributes' => $defaultAttributes,
            ];

            $product = $woo->post('products', $data);
            $this->productId = $product['id'];

            // 🧩 إنشاء المتغيرات وربطها
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

            $this->dispatch('toast', ['type' => 'success', 'message' => '✅ تم حفظ المنتج والمتغيرات والخصائص بنجاح!']);
        } catch (\Exception $e) {
            logger()->error('❌ WooCommerce Product Save Error: ' . $e->getMessage());
            $this->dispatch('toast', ['type' => 'error', 'message' => '❌ فشل في حفظ المنتج']);
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

    public function requestLatestVariations()
    {
        $this->dispatch('requestLatestVariations')->to('variation-manager');
    }


    public function render()
    {
        return view('livewire.pages.product.add');
    }
}

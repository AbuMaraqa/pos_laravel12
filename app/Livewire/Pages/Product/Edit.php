<?php

namespace App\Livewire\Pages\Product;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Services\WooCommerceService;
use Masmerise\Toaster\Toaster;

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

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    public function mount($id)
    {
        $this->productId = $id;
        $this->loadProduct();
    }

    protected function loadProduct()
    {
        $product = $this->wooService->getProduct($this->productId);

        if (!$product) {
            session()->flash('error', 'Product not found');
            return redirect()->route('products.index');
        }

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
        if ($this->productType === 'variable' && !empty($product['variations'])) {
            $this->variations = $product['variations'];
            $this->loadVariationAttributes($product);
        }

        // Load MRBP data if exists
        $this->loadMrbpData();
    }

    protected function loadVariationAttributes($product)
    {
        if (!empty($product['attributes'])) {
            foreach ($product['attributes'] as $attribute) {
                if (!empty($attribute['id']) && !empty($attribute['options'])) {
                    $this->selectedAttributes[$attribute['id']] = array_combine(
                        $attribute['options'],
                        array_fill(0, count($attribute['options']), true)
                    );
                }
            }
            $this->attributeMap = collect($product['attributes'])
                ->map(fn($attr) => ['id' => $attr['id'], 'name' => $attr['name']])
                ->toArray();
        }
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
        $this->variations = $data['variations'];
        $this->attributeMap = $data['attributeMap'];
        $this->selectedAttributes = $data['selectedAttributes'];
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

    public function syncBeforeSave()
    {
        try {
            if ($this->productType === 'variable') {
                $this->dispatch('requestLatestVariations')->to('variation-manager');
            }
            $this->save();
        } catch (\Exception $e) {
            logger()->error('Error in syncBeforeSave', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            Toaster::error('حدث خطأ: ' . $e->getMessage());
        }
    }

    public function save()
    {
        try {
            if (empty($this->productId)) {
                throw new \Exception('معرف المنتج غير موجود');
            }

            logger()->info('Product data before save', [
                'productId' => $this->productId,
                'name' => $this->productName,
                'type' => $this->productType,
                'variations' => $this->variations,
                'attributes' => $this->selectedAttributes
            ]);

            $productData = [];

            // Basic product data
            if (!empty($this->productName)) {
                $productData['name'] = $this->productName;
            }
            if (!empty($this->productDescription)) {
                $productData['description'] = $this->productDescription;
            }
            if (!empty($this->productType)) {
                $productData['type'] = $this->productType;
            }
            if (!empty($this->regularPrice)) {
                $productData['regular_price'] = (string)$this->regularPrice;
            }
            if (!empty($this->salePrice)) {
                $productData['sale_price'] = (string)$this->salePrice;
            }
            if (!empty($this->sku)) {
                $productData['sku'] = $this->sku;
            }
            if (isset($this->stockQuantity)) {
                $productData['stock_quantity'] = (int)$this->stockQuantity;
            }
            if (!empty($this->stockStatus)) {
                $productData['stock_status'] = $this->stockStatus;
            }
            if (!empty($this->selectedCategories)) {
                $productData['categories'] = array_map(function($id) {
                    return ['id' => (int)$id];
                }, $this->selectedCategories);
            }

            // Handle variable product
            if ($this->productType === 'variable') {
                logger()->info('Processing variable product', [
                    'variations_count' => count($this->variations)
                ]);

                if (!empty($this->variations)) {
                    $processedVariations = [];
                    foreach ($this->variations as $variation) {
                        if (!is_array($variation)) {
                            logger()->warning('Invalid variation data', ['variation' => $variation]);
                            continue;
                        }

                        $processedVariation = [];

                        if (isset($variation['id'])) {
                            $processedVariation['id'] = (int)$variation['id'];
                        }
                        if (isset($variation['regular_price'])) {
                            $processedVariation['regular_price'] = (string)$variation['regular_price'];
                        }
                        if (isset($variation['sale_price'])) {
                            $processedVariation['sale_price'] = (string)$variation['sale_price'];
                        }
                        if (isset($variation['stock_quantity'])) {
                            $processedVariation['stock_quantity'] = (int)$variation['stock_quantity'];
                        }
                        if (!empty($variation['attributes'])) {
                            $processedVariation['attributes'] = array_map(function($attr) {
                                return [
                                    'id' => isset($attr['id']) ? (int)$attr['id'] : null,
                                    'name' => $attr['name'] ?? '',
                                    'option' => $attr['option'] ?? ''
                                ];
                            }, $variation['attributes']);
                        }

                        $processedVariations[] = $processedVariation;
                    }

                    $productData['variations'] = $processedVariations;
                }

                // Process attributes
                if (!empty($this->selectedAttributes)) {
                    $attributes = [];
                    foreach ($this->selectedAttributes as $attrId => $terms) {
                        if (empty($terms)) continue;

                        $selectedTerms = array_keys(array_filter($terms));
                        if (!empty($selectedTerms)) {
                            $attributes[] = [
                                'id' => (int)$attrId,
                                'variation' => true,
                                'visible' => true,
                                'options' => array_values($selectedTerms)
                            ];
                        }
                    }
                    if (!empty($attributes)) {
                        $productData['attributes'] = $attributes;
                    }
                }
            }

            // Handle images
            $images = [];
            if ($this->file) {
                $featuredImageId = $this->wooService->uploadImage($this->file);
                if ($featuredImageId) {
                    $images[] = ['id' => (int)$featuredImageId];
                }
            }
            if (!empty($this->files)) {
                foreach ($this->files as $file) {
                    $imageId = $this->wooService->uploadImage($file);
                    if ($imageId) {
                        $images[] = ['id' => (int)$imageId];
                    }
                }
            }
            if (!empty($images)) {
                $productData['images'] = $images;
            }

            logger()->info('Final product data', ['data' => $productData]);

            // Update product
            $response = $this->wooService->updateProduct($this->productId, $productData);

            if (empty($response)) {
                throw new \Exception('لم يتم استلام رد من الخادم');
            }

            if (!empty($this->mrbpData)) {
                $this->wooService->updateMrbpData($this->productId, $this->mrbpData);
            }

            Toaster::success('تم تحديث المنتج بنجاح');
            return redirect()->route('products.index');

        } catch (\Exception $e) {
            logger()->error('Error saving product', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'productData' => $productData ?? null
            ]);

            Toaster::error('فشل في حفظ المنتج: ' . $e->getMessage());
        }
    }

    public function updatedProductType($value)
    {
        $this->dispatch('productTypeChanged', $value)->to('tabs-component');
    }

    public function render()
    {
        return view('livewire.pages.product.edit');
    }
}

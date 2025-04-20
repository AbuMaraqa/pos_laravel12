<?php

namespace App\Livewire\Pages\Product;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\On;
use App\Services\WooCommerceService;

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

    protected $wooService;

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
        if ($this->productType === 'variable') {
            $this->dispatch('requestLatestVariations')->to('variation-manager');
        }

        $this->save();
    }

    public function save()
    {
        $productData = [
            'name' => $this->productName,
            'description' => $this->productDescription,
            'type' => $this->productType,
            'regular_price' => $this->regularPrice,
            'sale_price' => $this->salePrice,
            'sku' => $this->sku,
            'stock_quantity' => $this->stockQuantity,
            'stock_status' => $this->stockStatus,
            'categories' => array_map(fn($id) => ['id' => $id], $this->selectedCategories),
        ];

        if ($this->productType === 'variable') {
            $productData['variations'] = $this->variations;
            $productData['attributes'] = $this->prepareAttributes();
        }

        // Handle image uploads if new images were added
        if ($this->file) {
            // Upload featured image
            $featuredImageId = $this->wooService->uploadImage($this->file);
            if ($featuredImageId) {
                $productData['images'][] = ['id' => $featuredImageId];
            }
        }

        if ($this->files) {
            // Upload gallery images
            foreach ($this->files as $file) {
                $imageId = $this->wooService->uploadImage($file);
                if ($imageId) {
                    $productData['images'][] = ['id' => $imageId];
                }
            }
        }

        try {
            // Update the product
            $updatedProduct = $this->wooService->updateProduct($this->productId, $productData);

            // Update MRBP data if needed
            if (!empty($this->mrbpData)) {
                $this->wooService->updateMrbpData($this->productId, $this->mrbpData);
            }

            session()->flash('success', 'Product updated successfully');
            return redirect()->route('products.index');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update product: ' . $e->getMessage());
        }
    }

    protected function prepareAttributes()
    {
        $attributes = [];
        foreach ($this->selectedAttributes as $attributeId => $terms) {
            $selectedTerms = array_keys(array_filter($terms));
            if (!empty($selectedTerms)) {
                $attributes[] = [
                    'id' => $attributeId,
                    'options' => $selectedTerms,
                    'variation' => true,
                ];
            }
        }
        return $attributes;
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

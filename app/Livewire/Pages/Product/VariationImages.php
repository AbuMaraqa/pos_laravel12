<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;
use Spatie\LivewireFilepond\WithFilePond;

class VariationImages extends Component
{
    use WithFilePond;

    public $productId;
    public $file;
    public $variation;
    public array $variationsImage = [];
    public $mainImage;
    public $galleryImages = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount($id)
    {
        $this->productId = $id;
        $product = $this->wooService->getProductsById($id);

        // الصورة الرئيسية
        $this->mainImage = $product['images'][0]['src'] ?? null;

        // صور الجاليري (كل الصور ما عدا الرئيسية)
        $this->galleryImages = [];
        if (!empty($product['images'])) {
            foreach ($product['images'] as $index => $img) {
                if ($index > 0) {
                    $this->galleryImages[] = $img['src'];
                }
            }
        }
    }

    #[Computed()]
    public function getVariationProduct()
    {
        return $this->wooService->getProductVariations($this->productId);
    }

    public function uploadImage($file) {}

    public function updatedVariationsImage($value, $key)
    {
        if ($value) {
            try {
                // Upload the image to WordPress
                $uploadedImage = $this->wooService->uploadImage($value);

                // Update the variation with the new image
                $this->wooService->updateProductVariation($this->productId, $key, [
                    'image' => [
                        'id' => $uploadedImage['id'],
                        'src' => $uploadedImage['src']
                    ]
                ]);
                Toaster::success('تم رفع الصورة بنجاح');
                $this->variationsImage[$key] = null;
            } catch (\Exception $e) {
                session()->flash('error', 'حدث خطأ أثناء رفع الصورة: ' . $e->getMessage());
            }
        }
    }

    public function updatedgalleryImages($value)
    {
        dd($value);
        $uploadedImages = [];

        foreach ($value as $image) {
            $uploadedImage = $this->wooService->uploadImage($image);

            // جهز مصفوفة الصور لرفعها مرة وحدة
            $uploadedImages[] = [
                'id' => $uploadedImage['id'],
                'src' => $uploadedImage['src']
            ];

            // خزن الرابط داخلياً لعرضه
            $this->galleryImages[] = $uploadedImage['src'];
        }

        // بعد رفع كل الصور، حدّث المنتج مرة وحدة بالصور كلها
        if (!empty($uploadedImages)) {
            $this->wooService->updateProduct($this->productId, [
                'images' => $uploadedImages
            ]);
        }
    }

    public function render()
    {
        return view('livewire.pages.product.variation-images');
    }
}

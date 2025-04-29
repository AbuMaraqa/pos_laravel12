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
    public $mainImageUpload;
    public $galleryUploads = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount($id)
    {
        $this->productId = $id;
        $product = $this->wooService->getProductsById($id);
        dd($product);

        // الصورة الرئيسية
        $this->mainImage = $product['images'][0]['src'] ?? null;

        // صور الجاليري (كل الصور ما عدا الرئيسية)
        $this->galleryImages = [];
        if (!empty($product['images'])) {
            // dd($product['images']);
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
                Toaster::success('تم رفع صورة المتغير بنجاح');
                $this->variationsImage[$key] = null;
            } catch (\Exception $e) {
                Toaster::error('حدث خطأ أثناء رفع صورة المتغير: ' . $e->getMessage());
            }
        }
    }

    public function updatedMainImageUpload($value)
    {
        if ($value) {
            try {
                // رفع الصورة إلى ووردبريس
                $uploadedImage = $this->wooService->uploadImage($value);

                // الحصول على الصور الحالية للمنتج
                $product = $this->wooService->getProductsById($this->productId);
                $currentImages = $product['images'] ?? [];

                // إنشاء مصفوفة الصور الجديدة مع وضع الصورة الرئيسية في المقدمة
                $images = [
                    [
                        'id' => $uploadedImage['id'],
                        'src' => $uploadedImage['src']
                    ]
                ];

                // إضافة صور المعرض الحالية (إن وجدت)
                if (count($currentImages) > 1) {
                    for ($i = 1; $i < count($currentImages); $i++) {
                        $images[] = [
                            'id' => $currentImages[$i]['id'],
                            'src' => $currentImages[$i]['src']
                        ];
                    }
                }

                // تحديث المنتج بالصور الجديدة
                $this->wooService->updateProduct($this->productId, [
                    'images' => $images
                ]);

                // تحديث الصورة للعرض
                $this->mainImage = $uploadedImage['src'];
                $this->mainImageUpload = null;

                Toaster::success('تم تحديث الصورة الرئيسية بنجاح');
            } catch (\Exception $e) {
                Toaster::error('حدث خطأ أثناء رفع الصورة الرئيسية: ' . $e->getMessage());
            }
        }
    }

    public function updatedGalleryUploads($value)
    {
        if (empty($value)) {
            return;
        }

        try {
            // الحصول على صور المنتج الحالية
            $product = $this->wooService->getProductsById($this->productId);
            $currentImages = $product['images'] ?? [];

            // تحضير مصفوفة الصور للتحديث
            $updatedImages = [];

            // الاحتفاظ بالصورة الرئيسية إذا كانت موجودة
            if (!empty($currentImages[0])) {
                $updatedImages[] = [
                    'id' => $currentImages[0]['id'],
                    'src' => $currentImages[0]['src']
                ];
            }

            // الاحتفاظ بصور المعرض الحالية
            if (count($currentImages) > 1) {
                for ($i = 1; $i < count($currentImages); $i++) {
                    $updatedImages[] = [
                        'id' => $currentImages[$i]['id'],
                        'src' => $currentImages[$i]['src']
                    ];
                }
            }

            // إضافة صور المعرض الجديدة
            $newGalleryImages = [];
            foreach ($value as $image) {
                $uploadedImage = $this->wooService->uploadImage($image);

                $updatedImages[] = [
                    'id' => $uploadedImage['id'],
                    'src' => $uploadedImage['src']
                ];

                $newGalleryImages[] = $uploadedImage['src'];
            }

            // تحديث المنتج بكل الصور (الرئيسية + الحالية + الجديدة)
            $this->wooService->updateProduct($this->productId, [
                'images' => $updatedImages
            ]);

            // تحديث قائمة الصور المعروضة
            $this->galleryImages = array_merge($this->galleryImages, $newGalleryImages);

            // تفريغ حقل الرفع بعد الانتهاء
            $this->reset('galleryUploads');

            Toaster::success('تم إضافة صور المعرض بنجاح');
        } catch (\Exception $e) {
            Toaster::error('حدث خطأ أثناء رفع صور المعرض: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pages.product.variation-images');
    }
}

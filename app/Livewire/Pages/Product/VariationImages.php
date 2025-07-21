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
    public $variations = [];
    public array $variationsImage = [];
    public $mainImage;
    public $galleryImages = [];
    public $mainImageUpload;
    public $galleryUploads = [];
    public $selectedVariationIds = []; // تغيير اسم المتغير

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

        // تهيئة المصفوفات
        $this->selectedVariationIds = [];
        $this->variationsImage = [];
    }

    #[Computed()]
    public function getVariationProduct()
    {
        return $this->wooService->getProductVariations($this->productId, [
            'per_page' => 100,
            'page' => 1,
        ]);
    }

    public function uploadImage($file) {}

    public function updatedVariationsImage($value, $key)
    {
        if ($value) {
            try {
                $uploadedImage = $this->wooService->uploadImage($value);

                // تحديث لكل المتغيرات المحددة
                foreach ($this->selectedVariationIds as $variationId) {
                    $this->wooService->updateProductVariation($this->productId, $variationId, [
                        'image' => [
                            'id' => $uploadedImage['id'],
                            'src' => $uploadedImage['src']
                        ]
                    ]);
                }

                Toaster::success('تم تحديث صورة ' . count($this->selectedVariationIds) . ' متغير بنجاح');

                // تفريغ الصورة بعد الرفع
                $this->variationsImage[$key] = null;
            } catch (\Exception $e) {
                Toaster::error('حدث خطأ أثناء تحديث صور المتغيرات: ' . $e->getMessage());
            }
        }
    }


    // دالة للتعامل مع العناصر المحددة
    public function processSelectedVariations()
    {
        if (!empty($this->selectedVariationIds)) {
            try {
                foreach ($this->selectedVariationIds as $variationId) {
                    // هنا يمكنك إضافة العمليات التي تريد تنفيذها على العناصر المحددة
                    // مثال: حذف صور المتغيرات المحددة
                    $this->wooService->updateProductVariation($this->productId, $variationId, [
                        'image' => null
                    ]);
                }

                Toaster::success('تم تنفيذ العملية على ' . count($this->selectedVariationIds) . ' عنصر بنجاح');
                $this->selectedVariationIds = []; // إعادة تعيين التحديد
            } catch (\Exception $e) {
                Toaster::error('حدث خطأ أثناء تنفيذ العملية: ' . $e->getMessage());
            }
        } else {
            Toaster::info('لم يتم تحديد أي عنصر');
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

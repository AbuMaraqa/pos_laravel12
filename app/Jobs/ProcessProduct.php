<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use App\Services\WooCommerceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessProduct implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param array $productData بيانات المنتج من WooCommerce
     * @param int $userId معرف المستخدم
     */
    public function __construct(public array $productData, public int $userId) {}

    public function handle(): void
    {
        try {
            $subId = User::whereKey($this->userId)->value('subscription_id');
            if (!$subId) {
                Log::warning("User {$this->userId} has no subscription_id for product {$this->productData['id']}");
                return;
            }

            $woo = new WooCommerceService((int) $subId);

            // Upsert المنتج الأساسي
            $parent = Product::updateOrCreate(
                ['remote_wp_id' => (int) $this->productData['id']],
                [
                    'parent_id'          => null,
                    'type'               => $this->productData['type'] ?? 'simple',
                    'name'               => $this->productData['name'] ?? null,
                    'slug'               => $this->productData['slug'] ?? null,
                    'sku'                => $this->cleanString($this->productData['sku'] ?? null),
                    'price'              => $this->cleanDecimal($this->productData['price'] ?? null),
                    'regular_price'      => $this->cleanDecimal($this->productData['regular_price'] ?? null),
                    'sale_price'         => $this->cleanDecimal($this->productData['sale_price'] ?? null),
                    'stock_status'       => $this->productData['stock_status'] ?? null,
                    'manage_stock'       => (bool)($this->productData['manage_stock'] ?? false),
                    'stock_quantity'     => $this->cleanStockQuantity($this->productData['stock_quantity'] ?? null),
                    'status'             => $this->productData['status'] ?? null,
                    'featured_image'     => $this->productData['images'][0]['src'] ?? null,
                    'short_description'  => $this->productData['short_description'] ?? null,
                    'description'        => $this->productData['description'] ?? null,
                    // الحقول الإضافية
                    'gallery'            => $this->extractGallery($this->productData['images'] ?? []),
                    'categories'         => $this->extractCategories($this->productData['categories'] ?? []),
                    'tags'               => $this->extractTags($this->productData['tags'] ?? []),
                    'attributes'         => $this->extractAttributes($this->productData['attributes'] ?? []),
                    'variations'         => $this->extractVariationsIds($this->productData['variations'] ?? []),
                    'external_url'       => $this->productData['external_url'] ?? null,
                    'synced_at'          => now(),
                ]
            );

            Log::info("Product synced: {$this->productData['name']}", ['id' => $this->productData['id']]);

            // لو المنتج variable → جيب المتغيرات
            if (($this->productData['type'] ?? '') === 'variable') {
                $this->syncVariations($parent, (int) $this->productData['id'], $woo, $this->productData);
            } else {
                // منتج simple → احذف أي children قديمة
                Product::where('parent_id', $parent->id)->delete();
            }

        } catch (\Throwable $th) {
            Log::error("Failed to process product {$this->productData['id']}: " . $th->getMessage());
            // يمكنك هنا إعادة دفع المهمة إلى الصف إذا كانت هناك مشكلة مؤقتة
            $this->release(10); // إعادة المحاولة بعد 10 ثواني
        }
    }

    private function syncVariations(Product $parent, int $productId, WooCommerceService $woo, array $w): void
    {
        $vpage = 1;
        $keptIds = [];

        do {
            $vars = $woo->getProductVariations($productId, [
                'per_page' => 100,
                'page'     => $vpage,
            ]);

            foreach ($vars as $v) {
                $variation = Product::updateOrCreate(
                    ['remote_wp_id' => (int) $v['id']],
                    [
                        'parent_id'       => $parent->id,
                        'type'            => 'variation',
                        'name'            => $parent->name . ' - ' . $this->buildVariationName($v['attributes'] ?? []),
                        'slug'            => $parent->slug . '-' . $this->createSlug($this->buildVariationName($v['attributes'] ?? [])),
                        'sku'             => $this->cleanString($v['sku'] ?? null),
                        'price'           => $this->cleanDecimal($v['price'] ?? null),
                        'regular_price'   => $this->cleanDecimal($v['regular_price'] ?? null),
                        'sale_price'      => $this->cleanDecimal($v['sale_price'] ?? null),
                        'stock_status'    => $v['stock_status'] ?? null,
                        'manage_stock'    => (bool)($v['manage_stock'] ?? false),
                        'stock_quantity'  => $this->cleanStockQuantity($v['stock_quantity'] ?? null),
                        'status'          => $v['status'] ?? null,
                        'featured_image'  => $v['image']['src'] ?? ($w['images'][0]['src'] ?? null),
                        'synced_at'       => now(),
                    ]
                );
                $keptIds[] = $variation->id;
            }

            $vpage++;
        } while (!empty($vars) && count($vars) === 100);

        // نظّف متغيرات قديمة
        Product::where('parent_id', $parent->id)
            ->whereNotIn('id', $keptIds)
            ->delete();

        Log::info("Variations synced for: {$parent->name}", ['count' => count($keptIds)]);
    }

    // الدوال المساعدة (Helper methods)
    private function buildVariationName(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }

        $attributeStrings = [];
        foreach ($attributes as $attr) {
            if (!empty($attr['option'])) {
                $attributeStrings[] = $attr['option'];
            }
        }

        return implode(', ', $attributeStrings);
    }

    private function createSlug(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug ?: 'product-' . time();
    }

    private function cleanDecimal($value)
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function cleanStockQuantity($value)
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }

        if (is_numeric($value)) {
            $intValue = (int) $value;
            return $intValue < 0 ? 0 : $intValue;
        }

        return null;
    }

    private function cleanInteger($value)
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }

        if (is_numeric($value)) {
            $intValue = (int) $value;
            return $intValue;
        }

        return null;
    }

    private function cleanString($value)
    {
        if ($value === null || $value === '' || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private function extractGallery(array $images): ?string
    {
        if (empty($images)) {
            return null;
        }

        $gallery = [];
        foreach ($images as $image) {
            if (!empty($image['src'])) {
                $gallery[] = $image['src'];
            }
        }

        return !empty($gallery) ? json_encode($gallery) : null;
    }

    private function extractCategories(array $categories): ?string
    {
        if (empty($categories)) {
            return null;
        }

        $categoryData = [];
        foreach ($categories as $category) {
            $categoryData[] = [
                'id' => $category['id'] ?? null,
                'name' => $category['name'] ?? null,
                'slug' => $category['slug'] ?? null,
            ];
        }

        return !empty($categoryData) ? json_encode($categoryData) : null;
    }

    private function extractTags(array $tags): ?string
    {
        if (empty($tags)) {
            return null;
        }

        $tagData = [];
        foreach ($tags as $tag) {
            $tagData[] = [
                'id' => $tag['id'] ?? null,
                'name' => $tag['name'] ?? null,
                'slug' => $tag['slug'] ?? null,
            ];
        }

        return !empty($tagData) ? json_encode($tagData) : null;
    }

    private function extractAttributes(array $attributes): ?string
    {
        if (empty($attributes)) {
            return null;
        }

        $attributeData = [];
        foreach ($attributes as $attribute) {
            $attributeData[] = [
                'id' => $attribute['id'] ?? null,
                'name' => $attribute['name'] ?? null,
                'position' => $attribute['position'] ?? null,
                'visible' => $attribute['visible'] ?? false,
                'variation' => $attribute['variation'] ?? false,
                'options' => $attribute['options'] ?? [],
            ];
        }

        return !empty($attributeData) ? json_encode($attributeData) : null;
    }

    private function extractVariationsIds(array $variations): ?string
    {
        if (empty($variations)) {
            return null;
        }

        return json_encode($variations);
    }
}

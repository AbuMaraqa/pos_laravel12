<?php

namespace App\Livewire\Pages\Inventory;

use App\Services\WooCommerceService;
use Livewire\Component;
use Exception;
use Masmerise\Toaster\Toaster;

class Index extends Component
{
    public $productId = '';
    public $scannedProducts = [];
    public $error = '';
    public $success = '';
    protected $woocommerce;

    public function boot()
    {
        try {
            $this->woocommerce = app(WooCommerceService::class);
        } catch (Exception $e) {
            $this->error = 'خطأ في الاتصال بالمتجر';
            logger()->error('WooCommerce Service Error: ' . $e->getMessage());
        }
    }

    public function saveQuantities()
    {
        try {
            if (empty($this->scannedProducts)) {
                $this->error = 'لا توجد منتجات لتحديث كمياتها';
                return;
            }

            $successCount = 0;
            $failCount = 0;

            foreach ($this->scannedProducts as $productId => $product) {
                try {
                    // تحديد المسار الصحيح للمتغير
                    $endpoint = $product['is_variation']
                        ? "products/{$product['parent_id']}/variations/{$productId}"
                        : "products/{$productId}";

                    // الحصول على بيانات المنتج الحالية
                    $currentProduct = $this->woocommerce->get($endpoint);

                    logger()->info('Current product data:', [
                        'product_id' => $productId,
                        'is_variation' => $product['is_variation'],
                        'parent_id' => $product['parent_id'] ?? null,
                        'endpoint' => $endpoint,
                        'response' => $currentProduct
                    ]);

                    if (!$currentProduct || isset($currentProduct['error'])) {
                        $errorMessage = isset($currentProduct['error']) ? $currentProduct['error'] : 'المنتج غير موجود';
                        throw new Exception($errorMessage);
                    }

                    $currentStock = (int) ($currentProduct['stock_quantity'] ?? 0);
                    $requestedQuantity = (int) $product['quantity'];
                    $newStock = $currentStock + $requestedQuantity;

                    logger()->info('Stock calculation:', [
                        'current_stock' => $currentStock,
                        'requested_quantity' => $requestedQuantity,
                        'new_stock' => $newStock
                    ]);

                    if ($newStock < 0) {
                        throw new Exception("الكمية المطلوبة ({$requestedQuantity}) أكبر من المخزون المتوفر ({$currentStock})");
                    }

                    // تحديث المخزون
                    $updateData = [
                        'stock_quantity' => $newStock,
                        'manage_stock' => true
                    ];

                    logger()->info('Sending update request:', [
                        'endpoint' => $endpoint,
                        'update_data' => $updateData
                    ]);

                    $response = $this->woocommerce->put($endpoint, $updateData);

                    logger()->info('Update response:', [
                        'product_id' => $productId,
                        'response' => $response
                    ]);

                    if (!$response || isset($response['error'])) {
                        $errorMessage = isset($response['error']) ? $response['error'] : 'فشل تحديث المخزون';
                        throw new Exception($errorMessage);
                    }

                    $successCount++;
                } catch (Exception $e) {
                    $failCount++;
                    $this->error = 'خطأ في تحديث المنتج: ' . $e->getMessage();
                    logger()->error("Failed to update product {$productId}", [
                        'error' => $e->getMessage(),
                        'product' => $product,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if ($failCount > 0) {
                if ($successCount > 0) {
                    $this->error = "تم تحديث {$successCount} منتج، وفشل تحديث {$failCount} منتج";
                } else {
                    $this->error = "فشل تحديث جميع المنتجات. يرجى التحقق من سجلات النظام للمزيد من التفاصيل.";
                }
            } else {
                $this->success = "تم إضافة الكميات بنجاح";
                $this->scannedProducts = []; // مسح القائمة بعد الحفظ الناجح
            }

        } catch (Exception $e) {
            $this->error = 'حدث خطأ أثناء حفظ الكميات: ' . $e->getMessage();
            logger()->error('Save Quantities Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    // حساب إجمالي الكمية
    public function getTotalQuantityProperty()
    {
        return array_sum(array_column($this->scannedProducts, 'quantity'));
    }

    // حساب إجمالي المبلغ
    public function getTotalAmountProperty()
    {
        $total = 0;
        foreach ($this->scannedProducts as $product) {
            $total += $product['quantity'] * floatval($product['price']);
        }
        return number_format($total, 2);
    }

    public function searchProduct()
    {
        $searchId = trim($this->productId);
        $this->productId = '';

        if (!empty($searchId)) {
            $this->processProduct($searchId);
        }
    }

    public function updatedProductId()
    {
        $this->error = '';
        $this->success = '';
    }

    public function processProduct($id)
    {
        try {
            if (!is_numeric($id)) {
                $this->error = 'الرجاء إدخال رقم صحيح';
                return;
            }

            if (!$this->woocommerce) {
                $this->error = 'خطأ في الاتصال بالخدمة';
                logger()->error('WooCommerce service not initialized');
                return;
            }

            logger()->info('Searching for product', ['id' => $id]);

            // محاولة الحصول على المنتج
            try {
                $product = $this->woocommerce->getProductsById($id);
                logger()->info('Product search result', [
                    'id' => $id,
                    'product' => $product
                ]);
            } catch (Exception $e) {
                logger()->error('Failed to fetch product', [
                    'id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            // التحقق مما إذا كان المنتج متغيراً
            $isVariation = false;
            if (isset($product['type']) && $product['type'] === 'variation' || isset($product['parent_id']) && $product['parent_id'] > 0) {
                $isVariation = true;
                logger()->info('Product is a variation', [
                    'id' => $id,
                    'parent_id' => $product['parent_id'] ?? null
                ]);

                // إعادة تحميل المنتج كمتغير
                try {
                    $parentId = $product['parent_id'];
                    $variation = $this->woocommerce->get("products/{$parentId}/variations/{$id}");
                    logger()->info('Variation data', [
                        'id' => $id,
                        'parent_id' => $parentId,
                        'variation' => $variation
                    ]);

                    if ($variation && !isset($variation['error'])) {
                        $product = $variation;
                    }
                } catch (Exception $e) {
                    logger()->error('Failed to load variation', [
                        'product_id' => $id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if (!$product || (isset($product['code']) && $product['code'] === 'woocommerce_rest_invalid_product_id')) {
                $this->error = 'لم يتم العثور على المنتج';
                logger()->error('Product not found or invalid', ['id' => $id, 'product' => $product]);
                return;
            }

            if ($product['status'] !== 'publish') {
                $this->error = 'هذا المنتج غير متاح حالياً';
                logger()->info('Product not published', ['id' => $id, 'status' => $product['status']]);
                return;
            }

            if (isset($this->scannedProducts[$id])) {
                $this->scannedProducts[$id]['quantity']++;
                logger()->info('Increased product quantity', [
                    'id' => $id,
                    'new_quantity' => $this->scannedProducts[$id]['quantity']
                ]);
            } else {
                $this->scannedProducts[$id] = [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'stock_quantity' => $product['stock_quantity'] ?? 0,
                    'sku' => $product['sku'] ?? '',
                    'is_variation' => $isVariation,
                    'parent_id' => $product['parent_id'] ?? null
                ];
                logger()->info('Added new product to scan list', [
                    'id' => $id,
                    'product_data' => $this->scannedProducts[$id]
                ]);
            }

            $this->error = '';
            $this->success = '';

        } catch (Exception $e) {
            Toaster::error('خطأ في البحث عن المنتج');
        }
    }

    public function removeProduct($productId)
    {
        if (isset($this->scannedProducts[$productId])) {
            unset($this->scannedProducts[$productId]);
        }
    }

    public function updateQuantity($productId, $quantity)
    {
        if (isset($this->scannedProducts[$productId])) {
            if ($quantity > 0) {
                $this->scannedProducts[$productId]['quantity'] = $quantity;
            } else {
                $this->removeProduct($productId);
            }
        }
    }

    public function render()
    {
        return view('livewire.pages.inventory.index');
    }
}

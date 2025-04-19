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
    public $pendingProducts = [];

    public function boot()
    {
        try {
            $this->woocommerce = app(WooCommerceService::class);
        } catch (Exception $e) {
            $this->error = 'خطأ في الاتصال بالمتجر';
            Toaster::error('خطأ في الاتصال بالمتجر');
            logger()->error('WooCommerce Service Error: ' . $e->getMessage());
        }
    }

    public function saveQuantities()
    {
        try {
            if (empty($this->scannedProducts)) {
                $this->error = 'لا توجد منتجات لتحديث كمياتها';
                Toaster::error('لا توجد منتجات لتحديث كمياتها');
                return;
            }

            $successCount = 0;
            $failCount = 0;

            foreach ($this->scannedProducts as $productId => $product) {
                try {
                    // تحديد المسار الصحيح للمتغير
                    if ($product['is_variation'] && isset($product['parent_id']) && $product['parent_id'] > 0) {
                        $endpoint = "products/{$product['parent_id']}/variations/{$productId}";
                    } else {
                        $endpoint = "products/{$productId}";
                    }

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
                        Toaster::error($errorMessage);
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
                        Toaster::error("الكمية المطلوبة ({$requestedQuantity}) أكبر من المخزون المتوفر ({$currentStock})");
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
                        Toaster::error($errorMessage);
                        throw new Exception($errorMessage);
                    }

                    $successCount++;
                } catch (Exception $e) {
                    $failCount++;
                    $this->error = 'خطأ في تحديث المنتج: ' . $e->getMessage();
                    Toaster::error('خطأ في تحديث المنتج: ' . $e->getMessage());
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
                    Toaster::warning('تم تحديث {$successCount} منتج، وفشل تحديث {$failCount} منتج');
                } else {
                    $this->error = "فشل تحديث جميع المنتجات. يرجى التحقق من سجلات النظام للمزيد من التفاصيل.";
                    Toaster::warning('فشل تحديث جميع المنتجات. يرجى التحقق من سجلات النظام للمزيد من التفاصيل.');
                }
            } else {
                $this->success = "تم إضافة الكميات بنجاح";
                Toaster::success('تم إضافة الكميات بنجاح');
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
        try {
            $searchId = trim($this->productId);
            $this->productId = '';

            if (empty($searchId)) {
                return;
            }

            // إذا كان المنتج موجود مسبقاً
            if (isset($this->scannedProducts[$searchId])) {
                $this->scannedProducts[$searchId]['quantity']++;
                $this->success = "تم تحديث كمية المنتج";
                Toaster::success('تم تحديث كمية المنتج');
                return;
            }

            // إذا كان المنتج جديد
            $this->processProduct($searchId);

        } catch (Exception $e) {
            $this->error = 'حدث خطأ أثناء البحث عن المنتج';
            Toaster::error('حدث خطأ أثناء البحث عن المنتج');
            logger()->error('Search Product Error: ' . $e->getMessage());
        }
    }

    public function processProduct($id)
    {
        try {
            if (!is_numeric($id)) {
                $this->error = 'الرجاء إدخال رقم صحيح';
                Toaster::error('الرجاء إدخال رقم صحيح');
                return;
            }

            if (!$this->woocommerce) {
                $this->error = 'خطأ في الاتصال بالخدمة';
                Toaster::error('خطأ في الاتصال بالخدمة');
                return;
            }

            // جلب بيانات المنتج
            $product = $this->woocommerce->getProductsById($id);

            if (!$product || isset($product['error'])) {
                $this->error = 'لم يتم العثور على المنتج';
                Toaster::error('لم يتم العثور على المنتج');
                return;
            }

            if ($product['status'] !== 'publish') {
                $this->error = 'هذا المنتج غير متاح حالياً';
                Toaster::error('هذا المنتج غير متاح حالياً');
                return;
            }

            // إضافة أو تحديث المنتج في القائمة
            $this->scannedProducts[$id] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => isset($this->scannedProducts[$id]) ?
                    $this->scannedProducts[$id]['quantity'] + 1 : 1,
                'stock_quantity' => $product['stock_quantity'] ?? 0,
                'sku' => $product['sku'] ?? '',
                'is_variation' => isset($product['type']) && $product['type'] === 'variation',
                'parent_id' => $product['parent_id'] ?? null
            ];

            $this->success = "تم إضافة/تحديث المنتج بنجاح";
            Toaster::success('تم إضافة/تحديث المنتج بنجاح');

        } catch (Exception $e) {
            $this->error = 'خطأ في معالجة المنتج';
            Toaster::error('خطأ في معالجة المنتج');
            logger()->error('Process Product Error: ' . $e->getMessage());
        }
    }

    public function updateQuantity($productId, $quantity)
    {
        $quantity = (int) $quantity;
        if ($quantity > 0 && isset($this->scannedProducts[$productId])) {
            $this->scannedProducts[$productId]['quantity'] = $quantity;
        } else {
            $this->removeProduct($productId);
        }
    }

    public function removeProduct($productId)
    {
        if (isset($this->scannedProducts[$productId])) {
            unset($this->scannedProducts[$productId]);
        }
    }

    public function incrementQuantity($productId)
    {
        if (isset($this->scannedProducts[$productId])) {
            $this->scannedProducts[$productId]['quantity']++;
        }
    }

    public function decrementQuantity($productId)
    {
        if (isset($this->scannedProducts[$productId])) {
            $newQty = $this->scannedProducts[$productId]['quantity'] - 1;
            if ($newQty > 0) {
                $this->scannedProducts[$productId]['quantity'] = $newQty;
            } else {
                $this->removeProduct($productId);
            }
        }
    }

    public function updatedProductId()
    {
        $this->error = '';
        $this->success = '';
    }

    public function render()
    {
        return view('livewire.pages.inventory.index');
    }
}

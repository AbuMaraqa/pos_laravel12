<?php

namespace App\Livewire\Pages\Inventory;

use App\Enums\InventoryType;
use App\Models\Inventory;
use App\Models\Store;
use App\Models\Product;
use App\Services\WooCommerceService;
use Livewire\Component;
use Exception;
use Masmerise\Toaster\Toaster;
use Illuminate\Support\Facades\DB;

class Decrement extends Component
{
    public $productId = '';
    public $scannedProducts = [];
    public $error = '';
    public $success = '';
    public $pendingProducts = [];
    public $stores;
    public $storeId = null;

    public function mount()
    {
        $this->scannedProducts = [];
        $this->stores = Store::all();
    }

    public function saveQuantities()
    {
        try {
            if (empty($this->scannedProducts)) {
                $this->error = 'لا توجد منتجات لتحديث كمياتها';
                Toaster::error('لا توجد منتجات لتحديث كمياتها');
                return;
            }

            logger()->info('=== بدء عملية خصم المخزون ===');

            $successCount = 0;
            $failCount = 0;
            $wooCommerceErrors = [];

            DB::beginTransaction();

            // إنشاء instance من WooCommerceService
            try {
                $wooService = new WooCommerceService();
                
                // اختبار الاتصال
                $testConnection = $wooService->getProducts(['per_page' => 1]);
                logger()->info('WooCommerce connection test successful for decrement');
            } catch (Exception $e) {
                logger()->error('Failed to initialize WooCommerceService: ' . $e->getMessage());
                $this->error = 'خطأ في الاتصال بـ WooCommerce: ' . $e->getMessage();
                Toaster::error('خطأ في الاتصال بـ WooCommerce');
                DB::rollBack();
                return;
            }

            foreach ($this->scannedProducts as $productId => $productData) {
                try {
                    // العثور على المنتج في قاعدة البيانات
                    $product = Product::find($productId);

                    if (!$product) {
                        throw new Exception('المنتج غير موجود');
                    }

                    $currentStock = (int) $product->stock_quantity;
                    $requestedQuantity = (int) $productData['quantity'];
                    $newStock = $currentStock - $requestedQuantity; // خصم الكمية

                    logger()->info('حساب خصم المخزون:', [
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'remote_wp_id' => $product->remote_wp_id,
                        'current_stock' => $currentStock,
                        'requested_quantity' => $requestedQuantity,
                        'new_stock' => $newStock,
                        'product_type' => $product->type
                    ]);

                    if ($newStock < 0) {
                        $errorMsg = "الكمية المطلوبة ({$requestedQuantity}) أكبر من المخزون المتوفر ({$currentStock})";
                        Toaster::error($errorMsg);
                        throw new Exception($errorMsg);
                    }

                    // تحديث المخزون في WooCommerce أولاً
                    if ($product->remote_wp_id) {
                        try {
                            logger()->info('=== بدء تحديث WooCommerce ===', [
                                'local_product_id' => $productId,
                                'remote_wp_id' => $product->remote_wp_id,
                                'new_stock' => $newStock
                            ]);

                            // التحقق من حالة المنتج في WooCommerce قبل التحديث
                            try {
                                $currentWooProduct = $wooService->getProductsById($product->remote_wp_id);
                                logger()->info('حالة المنتج الحالية في WooCommerce:', [
                                    'woo_stock_quantity' => $currentWooProduct['stock_quantity'] ?? 'غير موجود',
                                    'woo_stock_status' => $currentWooProduct['stock_status'] ?? 'غير موجود'
                                ]);
                            } catch (Exception $e) {
                                logger()->warning('فشل في جلب حالة المنتج الحالية من WooCommerce: ' . $e->getMessage());
                            }

                            $updateData = [
                                'stock_quantity' => $newStock,
                                'manage_stock' => true,
                                'stock_status' => $newStock > 0 ? 'instock' : 'outofstock'
                            ];

                            $wooResult = null;

                            if ($product->type === 'variation' && $product->parent_id) {
                                // للمتغيرات
                                $parentProduct = Product::find($product->parent_id);
                                if ($parentProduct && $parentProduct->remote_wp_id) {
                                    logger()->info('تحديث متغير في WooCommerce:', [
                                        'parent_remote_id' => $parentProduct->remote_wp_id,
                                        'variation_remote_id' => $product->remote_wp_id,
                                        'update_data' => $updateData
                                    ]);

                                    $wooResult = $wooService->updateProductVariation(
                                        $parentProduct->remote_wp_id,
                                        $product->remote_wp_id,
                                        $updateData
                                    );

                                    logger()->info('نتيجة تحديث المتغير:', [
                                        'result' => $wooResult
                                    ]);
                                } else {
                                    throw new Exception('لم يتم العثور على المنتج الأب للمتغير');
                                }
                            } else {
                                // للمنتجات العادية
                                logger()->info('تحديث منتج عادي في WooCommerce:', [
                                    'remote_wp_id' => $product->remote_wp_id,
                                    'update_data' => $updateData
                                ]);

                                $wooResult = $wooService->updateProduct($product->remote_wp_id, $updateData);

                                logger()->info('نتيجة تحديث المنتج العادي:', [
                                    'result' => $wooResult
                                ]);
                            }

                            if (!$wooResult || !isset($wooResult['id'])) {
                                logger()->error('استجابة غير صالحة من WooCommerce:', [
                                    'result' => $wooResult
                                ]);
                                throw new Exception('استجابة غير صالحة من WooCommerce');
                            }

                            // التحقق النهائي من التحديث
                            try {
                                sleep(1); // انتظار قصير
                                $verifyWooProduct = $wooService->getProductsById($product->remote_wp_id);
                                $finalWooStock = (int)($verifyWooProduct['stock_quantity'] ?? -1);
                                
                                logger()->info('=== التحقق النهائي من التحديث ===', [
                                    'expected_stock' => $newStock,
                                    'final_woo_stock' => $finalWooStock,
                                    'update_successful' => $finalWooStock === $newStock
                                ]);

                                if ($finalWooStock !== $newStock) {
                                    logger()->critical('🚨 التحديث لم ينجح - عدم تطابق الكميات', [
                                        'expected' => $newStock,
                                        'actual' => $finalWooStock,
                                        'product_id' => $productId,
                                        'remote_wp_id' => $product->remote_wp_id
                                    ]);
                                    throw new Exception("التحديث لم ينجح في WooCommerce. متوقع: {$newStock}, فعلي: {$finalWooStock}");
                                }
                            } catch (Exception $e) {
                                logger()->error('فشل في التحقق النهائي: ' . $e->getMessage());
                            }

                            logger()->info('✅ تم تحديث WooCommerce بنجاح', [
                                'product_id' => $productId,
                                'new_stock' => $newStock
                            ]);

                        } catch (Exception $e) {
                            logger()->error('❌ فشل تحديث WooCommerce:', [
                                'product_id' => $productId,
                                'remote_wp_id' => $product->remote_wp_id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            $wooCommerceErrors[] = "المنتج {$product->name}: " . $e->getMessage();
                            throw new Exception('فشل في تحديث المخزون في WooCommerce: ' . $e->getMessage());
                        }
                    } else {
                        logger()->warning('المنتج لا يحتوي على remote_wp_id:', [
                            'product_id' => $productId,
                            'product_name' => $product->name
                        ]);
                    }

                    // تحديث المخزون في قاعدة البيانات المحلية
                    $product->update([
                        'stock_quantity' => $newStock,
                        'manage_stock' => true
                    ]);

                    // إنشاء سجل في جدول المخزون
                    Inventory::create([
                        'product_id' => $productId,
                        'quantity' => -$requestedQuantity, // سالب للخصم
                        'store_id' => $this->storeId,
                        'user_id' => auth()->user()->id,
                        'type' => InventoryType::OUTPUT
                    ]);

                    logger()->info('✅ تم تحديث المنتج في النظامين:', [
                        'product_id' => $productId,
                        'new_stock' => $newStock
                    ]);

                    $successCount++;

                } catch (Exception $e) {
                    $failCount++;
                    $this->error = 'خطأ في تحديث المنتج: ' . $e->getMessage();
                    logger()->error("فشل في تحديث المنتج {$productId}", [
                        'error' => $e->getMessage(),
                        'product' => $productData,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            if ($failCount > 0) {
                DB::rollBack();
                
                $errorMessage = "فشل خصم {$failCount} منتج";
                if ($successCount > 0) {
                    $errorMessage = "تم خصم {$successCount} منتج، وفشل خصم {$failCount} منتج";
                }
                
                if (!empty($wooCommerceErrors)) {
                    $this->error = $errorMessage . ". أخطاء WooCommerce: " . implode(', ', array_slice($wooCommerceErrors, 0, 2));
                } else {
                    $this->error = $errorMessage;
                }
                
                Toaster::error($errorMessage);
            } else {
                DB::commit();
                $this->success = "تم خصم الكميات بنجاح من قاعدة البيانات و WooCommerce";
                Toaster::success('تم خصم الكميات بنجاح من قاعدة البيانات و WooCommerce');
                $this->scannedProducts = [];
            }

        } catch (Exception $e) {
            DB::rollBack();
            $this->error = 'حدث خطأ أثناء حفظ الكميات: ' . $e->getMessage();
            logger()->error('Save Quantities Decrement Error: ' . $e->getMessage(), [
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
                // التحقق من عدم تجاوز الكمية المتوفرة
                $currentQuantity = $this->scannedProducts[$searchId]['quantity'];
                $availableStock = $this->scannedProducts[$searchId]['stock_quantity'];

                if ($currentQuantity < $availableStock) {
                    $this->scannedProducts[$searchId]['quantity']++;
                    $this->success = "تم تحديث كمية المنتج";
                    Toaster::success('تم تحديث كمية المنتج');
                } else {
                    $this->error = "لا يمكن إضافة المزيد - الكمية المطلوبة تتجاوز المخزون المتوفر";
                    Toaster::error('لا يمكن إضافة المزيد - الكمية المطلوبة تتجاوز المخزون المتوفر');
                }
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

            // البحث عن المنتج بـ remote_wp_id
            $product = Product::where('remote_wp_id', $id)->first();

            // إذا لم يتم العثور على المنتج بالـ remote_wp_id، نبحث بالـ SKU
            if (!$product) {
                $product = Product::where('sku', $id)->first();
            }

            if (!$product) {
                $this->error = 'لم يتم العثور على المنتج';
                Toaster::error('لم يتم العثور على المنتج');
                return;
            }

            // التحقق من حالة المنتج
            if ($product->status !== 'active' && $product->status !== 'publish') {
                $this->error = 'هذا المنتج غير متاح حالياً';
                Toaster::error('هذا المنتج غير متاح حالياً');
                return;
            }

            $stockQuantity = $product->stock_quantity ?? 0;

            // التحقق من وجود مخزون
            if ($stockQuantity <= 0) {
                $this->error = 'هذا المنتج غير متوفر في المخزون';
                Toaster::error('هذا المنتج غير متوفر في المخزون');
                return;
            }

            // إضافة أو تحديث المنتج في القائمة
            $this->scannedProducts[$product->id] = [
                'name' => $product->name,
                'price' => $product->price ?? 0,
                'quantity' => isset($this->scannedProducts[$product->id]) ?
                    min($this->scannedProducts[$product->id]['quantity'] + 1, $stockQuantity) : 1,
                'stock_quantity' => $stockQuantity,
                'sku' => $product->sku ?? '',
                'remote_wp_id' => $product->remote_wp_id,
                'is_variation' => $product->type === 'variation',
                'parent_id' => $product->parent_id ?? null
            ];

            $this->success = "تم إضافة/تحديث المنتج بنجاح";
            Toaster::success('تم إضافة/تحديث المنتج بنجاح');

        } catch (Exception $e) {
            $this->error = 'خطأ في معالجة المنتج';
            Toaster::error('خطأ في معالجة المنتج');
            logger()->error('Process Product Error: ' . $e->getMessage());
        }
    }

    /**
     * تحديث المخزون في WooCommerce
     */
    private function updateWooCommerceStock($wooService, Product $product, int $newStock): bool
    {
        try {
            logger()->info('=== بدء تحديث WooCommerce للخصم ===', [
                'product_id' => $product->id,
                'remote_wp_id' => $product->remote_wp_id,
                'new_stock' => $newStock,
                'product_type' => $product->type
            ]);

            $updateData = [
                'stock_quantity' => $newStock,
                'manage_stock' => true,
                'stock_status' => $newStock > 0 ? 'instock' : 'outofstock'
            ];

            $wooResult = null;

            if ($product->type === 'variation' && $product->parent_id) {
                // للمتغيرات
                $parentProduct = Product::find($product->parent_id);
                if (!$parentProduct || !$parentProduct->remote_wp_id) {
                    throw new Exception('المنتج الأب غير موجود أو لا يحتوي على remote_wp_id');
                }

                logger()->info('تحديث متغير:', [
                    'parent_remote_id' => $parentProduct->remote_wp_id,
                    'variation_remote_id' => $product->remote_wp_id
                ]);

                $wooResult = $wooService->updateProductVariation(
                    $parentProduct->remote_wp_id,
                    $product->remote_wp_id,
                    $updateData
                );
            } else {
                // للمنتجات العادية
                logger()->info('تحديث منتج عادي:', [
                    'remote_wp_id' => $product->remote_wp_id
                ]);

                $wooResult = $wooService->updateProduct($product->remote_wp_id, $updateData);
            }

            // التحقق من النتيجة
            if (!$wooResult || !isset($wooResult['id'])) {
                logger()->error('نتيجة غير صالحة من WooCommerce:', [
                    'result' => $wooResult
                ]);
                return false;
            }

            // التحقق من تحديث الكمية
            $actualStock = (int)($wooResult['stock_quantity'] ?? -1);
            if ($actualStock !== $newStock) {
                logger()->warning('عدم تطابق كمية المخزون بعد التحديث:', [
                    'expected' => $newStock,
                    'actual' => $actualStock
                ]);
            }

            logger()->info('✅ تم تحديث WooCommerce بنجاح للخصم');
            return true;

        } catch (Exception $e) {
            logger()->error('❌ فشل تحديث WooCommerce للخصم:', [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function updateQuantity($productId, $quantity)
    {
        $quantity = (int) $quantity;
        if ($quantity > 0 && isset($this->scannedProducts[$productId])) {
            $availableStock = $this->scannedProducts[$productId]['stock_quantity'];

            if ($quantity <= $availableStock) {
                $this->scannedProducts[$productId]['quantity'] = $quantity;
            } else {
                $this->scannedProducts[$productId]['quantity'] = $availableStock;
                Toaster::warning("تم تعديل الكمية إلى الحد الأقصى المتوفر: {$availableStock}");
            }
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
            $currentQuantity = $this->scannedProducts[$productId]['quantity'];
            $availableStock = $this->scannedProducts[$productId]['stock_quantity'];

            if ($currentQuantity < $availableStock) {
                $this->scannedProducts[$productId]['quantity']++;
            } else {
                Toaster::warning('لا يمكن إضافة المزيد - تم الوصول للحد الأقصى المتوفر');
            }
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

    public function updatedScannedProducts($value, $key)
    {
        // $key مثال: "123.quantity"
        [$id, $field] = explode('.', $key);

        if ($field === 'quantity') {
            $qty = (int) $value;
            if ($qty < 1) {
                $qty = 1; // لا نسمح بأقل من 1
            }
            if (isset($this->scannedProducts[$id])) {
                $availableStock = $this->scannedProducts[$id]['stock_quantity'];
                if ($qty > $availableStock) {
                    $qty = $availableStock;
                    Toaster::warning("تم تعديل الكمية إلى الحد الأقصى المتوفر: {$availableStock}");
                }
                $this->scannedProducts[$id]['quantity'] = $qty;
            }
        }
    }

    public function render()
    {
        return view('livewire.pages.inventory.decrement');
    }
}
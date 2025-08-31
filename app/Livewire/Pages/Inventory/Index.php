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

class Index extends Component
{
    public $productId = '';
    public $scannedProducts = [];
    public $error = '';
    public $success = '';
    public $pendingProducts = [];
    public $stores;
    public $storeId = null;
    
    protected $wooService;

    public function mount()
    {
        $this->scannedProducts = [];
        $this->stores = Store::all();
        
        // إنشاء instance من WooCommerceService
        try {
            $this->wooService = new WooCommerceService();
        } catch (Exception $e) {
            logger()->error('Failed to initialize WooCommerceService in Inventory: ' . $e->getMessage());
            Toaster::error('خطأ في الاتصال بـ WooCommerce');
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

        DB::beginTransaction();

        // إنشاء instance من WooCommerceService
        try {
            $wooService = new WooCommerceService();
        } catch (Exception $e) {
            logger()->error('Failed to initialize WooCommerceService: ' . $e->getMessage());
            $this->error = 'خطأ في الاتصال بـ WooCommerce';
            Toaster::error('خطأ في الاتصال بـ WooCommerce');
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
                $newStock = $currentStock + $requestedQuantity;

                logger()->info('Stock calculation:', [
                    'product_id' => $productId,
                    'current_stock' => $currentStock,
                    'requested_quantity' => $requestedQuantity,
                    'new_stock' => $newStock
                ]);

                if ($newStock < 0) {
                    Toaster::error("الكمية المطلوبة ({$requestedQuantity}) أكبر من المخزون المتوفر ({$currentStock})");
                    throw new Exception("الكمية المطلوبة ({$requestedQuantity}) أكبر من المخزون المتوفر ({$currentStock})");
                }

                // تحديث المخزون في WooCommerce أولاً
                if ($product->remote_wp_id) {
                    try {
                        $updateData = [
                            'stock_quantity' => $newStock,
                            'manage_stock' => true,
                            'stock_status' => $newStock > 0 ? 'instock' : 'outofstock'
                        ];

                        // تحديد نوع المنتج وتحديثه في WooCommerce
                        if ($product->type === 'variation' && $product->parent_id) {
                            // للمتغيرات نحتاج parent product
                            $parentProduct = Product::find($product->parent_id);
                            if ($parentProduct && $parentProduct->remote_wp_id) {
                                $wooResult = $wooService->updateProductVariation(
                                    $parentProduct->remote_wp_id,
                                    $product->remote_wp_id,
                                    $updateData
                                );
                            } else {
                                throw new Exception('Parent product not found for variation');
                            }
                        } else {
                            // للمنتجات العادية
                            $wooResult = $wooService->updateProduct($product->remote_wp_id, $updateData);
                        }

                        if (!$wooResult || !isset($wooResult['id'])) {
                            throw new Exception('فشل في تحديث WooCommerce');
                        }

                        logger()->info('WooCommerce stock updated successfully:', [
                            'product_id' => $productId,
                            'remote_wp_id' => $product->remote_wp_id,
                            'new_stock' => $newStock
                        ]);

                    } catch (Exception $e) {
                        logger()->error('WooCommerce update failed:', [
                            'product_id' => $productId,
                            'remote_wp_id' => $product->remote_wp_id,
                            'error' => $e->getMessage()
                        ]);
                        throw new Exception('فشل في تحديث المخزون في WooCommerce: ' . $e->getMessage());
                    }
                } else {
                    logger()->warning('Product has no remote_wp_id, skipping WooCommerce update:', [
                        'product_id' => $productId
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
                    'quantity' => $requestedQuantity,
                    'store_id' => $this->storeId,
                    'user_id' => auth()->user()->id,
                    'type' => InventoryType::INPUT
                ]);

                logger()->info('Product updated successfully:', [
                    'product_id' => $productId,
                    'new_stock' => $newStock
                ]);

                $successCount++;
            } catch (Exception $e) {
                $failCount++;
                $this->error = 'خطأ في تحديث المنتج: ' . $e->getMessage();
                Toaster::error('خطأ في تحديث المنتج: ' . $e->getMessage());
                logger()->error("Failed to update product {$productId}", [
                    'error' => $e->getMessage(),
                    'product' => $productData,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        if ($failCount > 0) {
            DB::rollBack();
            if ($successCount > 0) {
                $this->error = "تم تحديث {$successCount} منتج، وفشل تحديث {$failCount} منتج";
                Toaster::warning("تم تحديث {$successCount} منتج، وفشل تحديث {$failCount} منتج");
            } else {
                $this->error = "فشل تحديث جميع المنتجات. يرجى التحقق من سجلات النظام للمزيد من التفاصيل.";
                Toaster::error('فشل تحديث جميع المنتجات. يرجى التحقق من سجلات النظام للمزيد من التفاصيل.');
            }
        } else {
            DB::commit();
            $this->success = "تم إضافة الكميات بنجاح في قاعدة البيانات و WooCommerce";
            Toaster::success('تم إضافة الكميات بنجاح في قاعدة البيانات و WooCommerce');
            $this->scannedProducts = []; // مسح القائمة بعد الحفظ الناجح
        }

    } catch (Exception $e) {
        DB::rollBack();
        $this->error = 'حدث خطأ أثناء حفظ الكميات: ' . $e->getMessage();
        logger()->error('Save Quantities Error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
    }
}

    /**
     * تحديث المخزون في WooCommerce
     */
    private function updateWooCommerceStock(Product $product, int $newStock): bool
    {
        try {
            if (!$product->remote_wp_id) {
                logger()->warning('Product has no remote_wp_id, skipping WooCommerce update', [
                    'product_id' => $product->id,
                    'product_name' => $product->name
                ]);
                return true; // نعتبرها نجاح إذا لم يكن هناك remote_wp_id
            }

            // تحديد نوع المنتج
            if ($product->type === 'variation') {
                return $this->updateVariationStock($product, $newStock);
            } else {
                return $this->updateSimpleProductStock($product, $newStock);
            }
            
        } catch (Exception $e) {
            logger()->error('Error updating WooCommerce stock', [
                'product_id' => $product->id,
                'remote_wp_id' => $product->remote_wp_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * تحديث مخزون المنتج البسيط
     */
    private function updateSimpleProductStock(Product $product, int $newStock): bool
    {
        try {
            $updateData = [
                'stock_quantity' => $newStock,
                'manage_stock' => true,
                'stock_status' => $newStock > 0 ? 'instock' : 'outofstock'
            ];

            $result = $this->wooService->updateProduct($product->remote_wp_id, $updateData);
            
            if (!$result || !isset($result['id'])) {
                logger()->error('Invalid WooCommerce response for simple product update', [
                    'product_id' => $product->id,
                    'remote_wp_id' => $product->remote_wp_id,
                    'result' => $result
                ]);
                return false;
            }

            logger()->info('Simple product stock updated in WooCommerce', [
                'product_id' => $product->id,
                'remote_wp_id' => $product->remote_wp_id,
                'new_stock' => $newStock
            ]);

            return true;
            
        } catch (Exception $e) {
            logger()->error('Failed to update simple product stock in WooCommerce', [
                'product_id' => $product->id,
                'remote_wp_id' => $product->remote_wp_id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * تحديث مخزون المتغير
     */
    private function updateVariationStock(Product $product, int $newStock): bool
    {
        try {
            if (!$product->parent_id) {
                logger()->error('Variation product missing parent_id', [
                    'product_id' => $product->id,
                    'remote_wp_id' => $product->remote_wp_id
                ]);
                return false;
            }

            // الحصول على المنتج الأب
            $parentProduct = Product::find($product->parent_id);
            if (!$parentProduct || !$parentProduct->remote_wp_id) {
                logger()->error('Parent product not found or missing remote_wp_id', [
                    'variation_id' => $product->id,
                    'parent_id' => $product->parent_id
                ]);
                return false;
            }

            $updateData = [
                'stock_quantity' => $newStock,
                'manage_stock' => true,
                'stock_status' => $newStock > 0 ? 'instock' : 'outofstock'
            ];

            $result = $this->wooService->updateProductVariation(
                $parentProduct->remote_wp_id,
                $product->remote_wp_id,
                $updateData
            );
            
            if (!$result || !isset($result['id'])) {
                logger()->error('Invalid WooCommerce response for variation update', [
                    'variation_id' => $product->id,
                    'parent_remote_id' => $parentProduct->remote_wp_id,
                    'variation_remote_id' => $product->remote_wp_id,
                    'result' => $result
                ]);
                return false;
            }

            logger()->info('Variation stock updated in WooCommerce', [
                'variation_id' => $product->id,
                'parent_remote_id' => $parentProduct->remote_wp_id,
                'variation_remote_id' => $product->remote_wp_id,
                'new_stock' => $newStock
            ]);

            return true;
            
        } catch (Exception $e) {
            logger()->error('Failed to update variation stock in WooCommerce', [
                'variation_id' => $product->id,
                'error' => $e->getMessage()
            ]);
            return false;
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

            // إضافة أو تحديث المنتج في القائمة
            $this->scannedProducts[$product->id] = [
                'name' => $product->name,
                'price' => $product->price ?? 0,
                'quantity' => isset($this->scannedProducts[$product->id]) ?
                    $this->scannedProducts[$product->id]['quantity'] + 1 : 1,
                'stock_quantity' => $product->stock_quantity ?? 0,
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
                $this->scannedProducts[$id]['quantity'] = $qty;
            }
        }
    }

    public function render()
    {
        return view('livewire.pages.inventory.index');
    }
}
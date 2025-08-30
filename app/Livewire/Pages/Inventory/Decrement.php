<?php

namespace App\Livewire\Pages\Inventory;

use App\Enums\InventoryType;
use App\Models\Inventory;
use App\Models\Store;
use App\Models\Product; // إضافة موديل المنتج
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

            $successCount = 0;
            $failCount = 0;

            DB::beginTransaction();

            foreach ($this->scannedProducts as $productId => $productData) {
                try {
                    // العثور على المنتج في قاعدة البيانات
                    $product = Product::find($productId);

                    if (!$product) {
                        throw new Exception('المنتج غير موجود');
                    }

                    $currentStock = (int) $product->stock_quantity;
                    $requestedQuantity = (int) $productData['quantity'];
                    $newStock = $currentStock - $requestedQuantity; // تغيير: نطرح بدلاً من الجمع

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

                    // تحديث المخزون في قاعدة البيانات
                    $product->update([
                        'stock_quantity' => $newStock,
                        'manage_stock' => true
                    ]);

                    // إنشاء سجل في جدول المخزون
                    Inventory::create([
                        'product_id' => $productId,
                        'quantity' => -$requestedQuantity, // تغيير: نحفظ بالسالب للدلالة على الخروج
                        'store_id' => $this->storeId,
                        'user_id' => auth()->user()->id,
                        'type' => InventoryType::OUTPUT // تغيير: نوع العملية إخراج
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
                $this->success = "تم خصم الكميات بنجاح";
                Toaster::success('تم خصم الكميات بنجاح');
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

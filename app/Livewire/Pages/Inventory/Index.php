<?php

namespace App\Livewire\Pages\Inventory;

use App\Services\WooCommerceService;
use Livewire\Component;
use Exception;

class Index extends Component
{
    public $productId = '';
    public $scannedProducts = [];
    public $error = '';
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

    public function searchProduct()
    {
        $searchId = trim($this->productId); // حفظ القيمة قبل تفريغها
        $this->productId = ''; // تفريغ الحقل مباشرة

        if (!empty($searchId)) {
            $this->processProduct($searchId);
        }
    }

    public function updatedProductId()
    {
        $this->error = '';
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
                return;
            }

            // جلب المنتج مباشرة بواسطة ID
            $product = $this->woocommerce->getProductsById($id);

            // التحقق من وجود خطأ في الاستجابة
            if (isset($product['code']) && $product['code'] === 'woocommerce_rest_product_invalid_id') {
                $this->error = 'لم يتم العثور على المنتج';
                return;
            }

            // التحقق من أن المنتج متاح للبيع
            if ($product['status'] !== 'publish') {
                $this->error = 'هذا المنتج غير متاح حالياً';
                return;
            }

            // تحديث المصفوفة بشكل صحيح
            if (isset($this->scannedProducts[$id])) {
                $this->scannedProducts[$id]['quantity']++;
            } else {
                $this->scannedProducts[$id] = [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => 1,
                    'stock_quantity' => $product['stock_quantity'] ?? 0,
                    'sku' => $product['sku'] ?? ''
                ];
            }

            $this->error = '';

        } catch (Exception $e) {
            $this->error = 'حدث خطأ في البحث عن المنتج';
            logger()->error('Product Search Error: ' . $e->getMessage(), [
                'product_id' => $id ?? null,
                'error' => $e->getMessage()
            ]);
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

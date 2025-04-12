<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Component;
use PDF;

class Index extends Component
{
    public $search;
    public $categoryId = null;
    public $products = [];
    public $categories = [];

    public $product = [];
    public $variations = [];
    public $quantities = [];


    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount(): void
    {
        $this->loadProducts();
        $this->loadCategories();
    }

    public function loadProducts(array $query = []): void
    {
        if (!empty($this->search)) {
            $query['search'] = $this->search;
        }

        if ($this->categoryId) {
            $query['category'] = $this->categoryId;
        }

        $this->products = $this->wooService->getProducts($query);
    }

    public function updatedSearch(): void
    {
        $this->loadProducts();
    }

    public function loadCategories(array $query = []): void
    {
        $query['parent'] = 0;
        $this->categories = $this->wooService->getCategories($query);
    }

    public function resetCategory(): void
    {
        $this->categoryId = null;
        $this->loadProducts(); // تحميل كل المنتجات بدون فلتر
    }

    public function setCategory($categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->loadProducts(['category' => $categoryId]);
    }
    public function openPrintBarcodeModal($productId)
    {
        $product = $this->wooService->getProductsById($productId);

        $this->product = $product;
        $this->variations = $product['variations'] ?? [];

        // إعداد الكميات
        $this->quantities = [];

        // الكمية الافتراضية للمنتج الرئيسي
        $this->quantities['main'] = 1;

        // الكمية الافتراضية لكل متغير
        foreach ($this->variations as $variation) {
            $this->quantities[$variation] = 1;
        }

        $this->modal('barcode-product-modal')->show();
    }

    public function printBarcodes()
    {
        // طباعة المنتج الرئيسي
//        \Log::info("Print barcode for main product ID {$this->product['id']} with quantity {$this->quantities['main']}");

//        return view('livewire.pages.product.pdf.index');


        // طباعة المتغيرات إن وجدت
        foreach ($this->variations as $variation) {
            $variationId = $variation;
            $qty = $this->quantities[$variationId] ?? 0;
        }

        $pdf = PDF::loadView('livewire.pages.product.pdf.index' , [
            'product' => $this->product,
            'variations' => $this->variations,
            'quantities' => $this->quantities,
        ]);

        return response()->streamDownload(function () use ($pdf) {
            $pdf->stream();
        }, 'documentname.pdf');
    }

    public function render()
    {
        return view('livewire.pages.product.index');
    }
}

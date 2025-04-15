<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\Attributes\Url;

class Index extends Component
{
    #[Url(as: 'page')]
    public int $page = 1;

    #[Url]
    public string $search = '';

    public $categoryId = null;
    public $categories = [];

    public int $perPage = 10;
    public int $total = 0;

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
        $this->categories = $this->wooService->getCategories(['parent' => 0]);
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
    }

    public function resetCategory(): void
    {
        $this->categoryId = null;
        $this->page = 1;
    }

    public function setCategory($categoryId): void
    {
        $this->categoryId = $categoryId;
        $this->page = 1;
    }

    public function openPrintBarcodeModal($productId)
    {
        $product = $this->wooService->getProductsById($productId);
        $this->product = $product;
        $this->quantities = ['main' => 1];
        foreach ($product['variations'] as $variation) {
            $this->variations[$variation] = $this->wooService->getProductsById($variation);
        }
        $this->modal('barcode-product-modal')->show();
//        $this->dispatch('open-modal', name: 'barcode-product-modal');
    }

    public function printBarcodes()
    {
        $pdf = \PDF::loadView('livewire.pages.product.pdf.index', [
            'product' => $this->product,
            'variations' => $this->variations,
            'quantities' => $this->quantities,
        ], [], [
            'format' => [80, 30]
        ]);

        return response()->streamDownload(function () use ($pdf) {
            $pdf->stream();
        }, 'barcode.pdf');
    }

    // #[Computed]
    // public function getMrbpRole($productId){
    //     $result = $this->wooService->getMrbpRoleById($productId);

    //     // التحقق من نوع البيانات المرجعة وتحويلها إلى نص إذا كانت مصفوفة
    //     if (is_array($result)) {
    //         return isset($result['error']) ? 'خطأ: ' . $result['error'] : 'مصفوفة غير محددة';
    //     }

    //     // إذا كانت القيمة فارغة
    //     if (is_null($result)) {
    //         return 'غير محدد';
    //     }

    //     return (string) $result;
    // }


    public function deleteProduct($productId)
    {
        $this->wooService->deleteProductById($productId);
    }



    public function render()
    {
        $query = [
            'search' => $this->search,
            'per_page' => $this->perPage,
            'page' => $this->page,
        ];

        if ($this->categoryId) {
            $query['category'] = $this->categoryId;
        }

        $response = $this->wooService->getProducts($query);
        $collection = collect($response['data'] ?? $response);
        $total = $response['total'] ?? 1000;

        $products = new LengthAwarePaginator(
            $collection,
            $total,
            $this->perPage,
            $this->page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.pages.product.index', [
            'products' => $products,
            'categories' => $this->categories,
        ]);
    }

}

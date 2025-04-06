<?php

namespace App\Livewire\Pages\Product;

use App\Services\WooCommerceService;
use Livewire\Component;

class Index extends Component
{
    public $search;
    public $categoryId = null;
    public $products = [];
    public $categories = [];

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

    public function render()
    {
        return view('livewire.pages.product.index');
    }
}

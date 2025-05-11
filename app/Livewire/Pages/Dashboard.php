<?php

namespace App\Livewire\Pages;

use App\Services\WooCommerceService;
use Livewire\Component;

class Dashboard extends Component
{
    public $ordersThisMonth;
    public $customersCount;
    public $productsCount;
    public $lowStockProducts;
    public $orderStatuses;

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
        $this->getOrdersThisMonth();
        $this->getCustomersCount();
        $this->getProductsCount();
    }

    public function getOrdersThisMonth()
    {
        $orders = $this->wooService->getOrders([
            'after' => now()->startOfMonth()->toIso8601String(),
            'before' => now()->endOfMonth()->toIso8601String(),
        ]);

        $this->ordersThisMonth = is_array($orders) && isset($orders['total']) ? $orders['total'] : count($orders);
    }

    public function getCustomersCount()
    {
        $this->customersCount = $this->wooService->getCustomersCount();
    }

    public function getProductsCount()
    {
        $this->productsCount = $this->wooService->getProductsCount();
    }

    public function getLowStockProducts()
{
    $response = $this->wooService->get('products', ['per_page' => 50]);

    $products = $response['data'] ?? $response;

    $this->lowStockProducts = collect($products)
        ->filter(fn($p) => isset($p['stock_quantity']) && $p['stock_quantity'] <= 5)
        ->values()
        ->all();
}

public function getOrderStatuses()
{
    $response = $this->wooService->get('orders', ['per_page' => 100]);

    $orders = $response['data'] ?? $response;

    $this->orderStatuses = collect($orders)
        ->groupBy('status')
        ->map(fn($group) => $group->count())
        ->toArray();
}

    public function render()
    {
        return view('livewire.pages.dashboard');
    }
}

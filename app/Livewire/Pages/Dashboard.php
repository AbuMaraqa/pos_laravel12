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
    public $latestOrders;

    protected WooCommerceService $wooService;

    public $dashboardButtons = [];

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
//        $this->getOrdersThisMonth();
//        $this->getCustomersCount();
//        $this->getProductsCount();
//        $this->getLowStockProducts();
//        $this->getLatestOrders();
//        $this->getOrderStatuses();

        $dashboardButtons = [
            [
                'name' => __('Home'),
                'icon' => 'home',
                'url' => route('dashboard'),
                'current' => request()->routeIs('dashboard'),
            ],
            [
                'name' => __('List Products'),
                'icon' => 'list-bullet',
                'url' => route('product.index'),
                'current' => request()->routeIs('product.index'),
            ],
            [
                'name' => __('Add Product'),
                'icon' => 'plus',
                'url' => route('product.add'),
                'current' => request()->routeIs('product.add'),
            ],
            [
                'name' => __('Categories'),
                'icon' => 'rectangle-group',
                'url' => route('category.index'),
                'current' => request()->routeIs('category.index'),
            ],
            [
                'name' => __('Attributes'),
                'icon' => 'tag',
                'url' => route('product.attributes.add'),
                'current' => request()->routeIs('product.attributes.add'),
            ],
            [
                'name' => __('Point of Sale'),
                'icon' => 'shopping-bag',
                'url' => route('pos.index'),
                'current' => request()->routeIs('pos.index'),
            ],
            [
                'name' => __('Orders'),
                'icon' => 'shopping-cart',
                'url' => route('order.index'),
                'current' => request()->routeIs('order.index'),
            ],
            [
                'name' => __('Inventory'),
                'icon' => 'clipboard-document-list',
                'url' => route('inventory.index'),
                'current' => request()->routeIs('inventory.index'),
            ],
            [
                'name' => __('Clients'),
                'icon' => 'users',
                'url' => route('client.index'),
                'current' => request()->routeIs('client.index'),
            ],
            [
                'name' => __('Reports'),
                'icon' => 'document',
                'url' => route('report.index'),
                'current' => request()->routeIs('report.index'),
            ],
            [
                'name' => __('Stores'),
                'icon' => 'building-storefront',
                'url' => route('store.index'),
                'current' => request()->routeIs('store.index'),
            ],
            [
                'name' => __('General Setting'),
                'icon' => 'cog-6-tooth',
                'url' => route('settings.index'),
                'current' => request()->routeIs('settings.index'),
            ],
        ];
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
        $response = $this->wooService->get('orders', ['per_page' => 10]);

        $orders = $response['data'] ?? $response;

        $this->orderStatuses = collect($orders)
            ->groupBy('status')
            ->map(fn($group) => $group->count())
            ->toArray();
    }

    public function getLatestOrders()
    {
        $response = $this->wooService->getOrders([
            'per_page' => 5,
            'order' => 'desc', // فقط هذا يكفي
        ]);

        $this->latestOrders = $response['data'] ?? $response;
    }

    public function render()
    {
        return view('livewire.pages.dashboard');
    }
}

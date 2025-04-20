<?php

namespace App\Livewire\Pages\Order;

use App\Services\WooCommerceService;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

class Index extends Component
{
    public $search;
    public $orders = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount(): void
    {
        $this->loadOrders();
    }

    public function loadOrders(array $query = []): void
    {
        $this->orders = $this->wooService->getOrders($query);
    }

    public function updateOrderStatus(int $orderId, string $status): void
    {
        $this->wooService->updateOrderStatus($orderId, $status);
        Toaster::success('Order status updated successfully');
    }

    public function render()
    {
        return view('livewire.pages.order.index');
    }
}

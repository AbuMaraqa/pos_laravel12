<?php

namespace App\Livewire\Pages\Order;

use App\Services\WooCommerceService;
use Livewire\Component;

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

    public function render()
    {
        return view('livewire.pages.order.index');
    }
}

<?php

namespace App\Livewire\Pages\User;

use App\Services\WooCommerceService;
use Livewire\Component;

class Profile extends Component
{
    public ?array $customer = [];
    public ?array $orders = [];
    public $customerId;

    protected $wooService;
    public function boot(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    /**
     * Mount the component.
     *
     * @param int $id The ID of the customer
     *
     * @return void
     */
    public function mount($id)
    {
        $this->customerId = $id;
        $this->customer = $this->wooService->getCustomerById($id);
        $this->getOrders();
    }

    public function getOrders()
    {
        $this->orders = $this->wooService->getCustomerOrders($this->customerId);
    }

    public function render()
    {
        return view('livewire.pages.user.profile');
    }
}

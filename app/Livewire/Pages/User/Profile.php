<?php

namespace App\Livewire\Pages\User;

use App\Services\WooCommerceService;
use Livewire\Component;

class Profile extends Component
{
    public array $customer = [];
    protected $wooService;
    public function boot(WooCommerceService $wooService)
    {
        $this->wooService = $wooService;
    }

    public function mount($id)
    {
        $this->customer = $this->wooService->getCustomerById($id);
    }

    public function render()
    {
        return view('livewire.pages.user.profile');
    }
}

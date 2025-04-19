<?php

namespace App\Livewire\Pages\User;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    #[Computed()]
    public function customers(){
        return $this->wooService->getCustomers();
    }

    public function render()
    {
        return view('livewire.pages.user.index');
    }
}

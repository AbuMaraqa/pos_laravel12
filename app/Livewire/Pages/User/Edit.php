<?php

namespace App\Livewire\Pages\User;

use App\Services\WooCommerceService;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

class Edit extends Component
{

    public ?array $customer = [];
    public ?array $data = [];
    protected WooCommerceService $wooService;


    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }
    public function mount($id)
    {
        $this->customer = $this->wooService->getCustomerById($id);
        $this->data['first_name'] = $this->customer['first_name'];
        $this->data['last_name'] = $this->customer['last_name'];
        $this->data['email'] = $this->customer['email'];
    }

    public function update(){
        // $this->validate([
        //     'customer.first_name' => 'required',
        //     'customer.last_name' => 'required',
        //     'customer.email' => 'required|email',
        // ]);

        $response = $this->wooService->updateCustomer($this->customer['id'], $this->data);

        if ($response) {
            Toaster::success(__('Customer updated successfully'));
        } else {
            session()->flash('error', __('Failed to update customer.'));
        }
    }

    public function render()
    {
        return view('livewire.pages.user.edit');
    }
}

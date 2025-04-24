<?php

namespace App\Livewire\Pages\User;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

class Index extends Component
{
    public array $roles = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    #[Computed()]
    public function customers()
    {
        return $this->wooService->getUsers();
    }

    #[Computed()]
    public function getRoles()
    {
        return $this->wooService->getRoles();
    }

    public function booted()
    {
        foreach ($this->customers as $index => $customer) {
            if (!is_array($customer)) {
                logger("Customer at index $index is not an array: " . print_r($customer, true));
                continue;
            }

            if (!isset($customer['id'])) {
                logger("Customer at index $index has no ID: " . print_r($customer, true));
                continue;
            }

            $this->roles[$customer['id']] = $customer['roles'] ?? [];
        }
    }

    // public function updateCustomerRole($customerId)
    // {
    //     $roles = $this->roles[$customerId] ?? [];

    //     $response = $this->wooService->updateUser($customerId, [
    //         'roles' => $roles,
    //     ]);

    //     Toaster::success(__('تم تحديث الأدوار بنجاح'));
    // }

    public function updateCustomerRole($customerId, $role)
    {
        $response = $this->wooService->updateUser($customerId, [
            'roles' => [$role],
        ]);

        Toaster::success(__('تم تحديث الأدوار بنجاح'));
    }

    public function render()
    {
        return view('livewire.pages.user.index');
    }
}
<?php

namespace App\Livewire\Pages\User;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

class Index extends Component
{
    public array $roles = [];
    public string $name;
    public string $last_name;
    public string $email;

    public array $customers = [];

    public array $filters = [];

    protected WooCommerceService $wooService;

    public function boot(WooCommerceService $wooService): void
    {
        $this->wooService = $wooService;
    }

    public function mount()
    {
        $this->name = '';
        $this->last_name = '';
        $this->email = '';
        $this->customers = $this->customers();

        $this->filters = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
        ];
    }

    #[Computed()]
public function customers(): array
{
    $email = $this->filters['email'] ?? '';
    $firstName = $this->filters['first_name'] ?? '';
    $lastName = $this->filters['last_name'] ?? '';

    $search = '';

    if (!empty($email)) {
        $search = $email;
    } elseif (!empty($firstName) || !empty($lastName)) {
        $search = trim($firstName . ' ' . $lastName);
    }

    return $this->wooService->getUsers([
        'per_page' => 100,
        'search' => $search,
    ]) ?? [];
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

    public function createCustomer()
    {
        $data = [
            'email' => $this->email,
            'first_name' => $this->name,
            'last_name' => $this->last_name,
        ];

        $response = $this->wooService->createUser($data);

        $this->reset(['name', 'last_name', 'email']);

        $this->customers = $this->customers();

        Toaster::success(__('تم إنشاء العميل بنجاح'));
    }

    public function updated($key): void
    {
        if (str_starts_with($key, 'filters.')) {
            $this->customers = $this->customers();
        }
    }

    public function render()
    {
        return view('livewire.pages.user.index', [
            'customers' => $this->customers,
        ]);
    }
}

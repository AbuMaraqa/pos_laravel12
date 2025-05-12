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

    public array $filters = [
        'name' => '',
        'email' => '',
        'role' => '',
        'status' => '',
    ];

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
    }

    #[Computed()]
    public function customers(): array
    {
        $query = [
            'per_page' => 100,
        ];

        // إضافة فلتر الاسم
        if (!empty($this->filters['name'])) {
            $query['search'] = $this->filters['name'];
        }

        // إضافة فلتر البريد الإلكتروني
        if (!empty($this->filters['email'])) {
            // استخدام search بدلاً من email لأن WooCommerce API لا يدعم فلتر email مباشرة
            $query['search'] = $this->filters['email'];
        }

        // إضافة فلتر الدور
        if (!empty($this->filters['role'])) {
            $query['role'] = $this->filters['role'];
        }

        // إضافة فلتر الحالة
        if (!empty($this->filters['status'])) {
            $query['status'] = $this->filters['status'];
        }

        try {
            $response = $this->wooService->getCustomers($query);

            $customers = is_array($response) && isset($response['data']) ? $response['data'] : $response;

            // إذا كان هناك فلتر بريد إلكتروني، نقوم بتصفية النتائج يدوياً
            if (!empty($this->filters['email'])) {
                $customers = array_filter($customers, function($customer) {
                    return stripos($customer['email'] ?? '', $this->filters['email']) !== false;
                });
            }

            // معالجة حالة العميل إذا لم تكن موجودة
            foreach ($customers as &$customer) {
                if (!isset($customer['status'])) {
                    $customer['status'] = 'inactive';
                }
                // التأكد من أن roles مصفوفة
                if (!isset($customer['roles']) || !is_array($customer['roles'])) {
                    $customer['roles'] = [];
                }
            }

            return $customers;
        } catch (\Exception $e) {
            logger()->error('Error fetching customers: ' . $e->getMessage());
            return [];
        }
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
        try {
            $response = $this->wooService->updateUser($customerId, [
                'roles' => [$role],
            ]);

            // تحديث البيانات المحلية
            $this->customers = $this->customers();

            Toaster::success(__('تم تحديث الأدوار بنجاح'));
        } catch (\Exception $e) {
            logger()->error('Error updating customer role: ' . $e->getMessage());
            Toaster::error(__('حدث خطأ أثناء تحديث الدور'));
        }
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

    public function resetFilters()
    {
        $this->filters = [
            'name' => '',
            'email' => '',
            'role' => '',
            'status' => '',
        ];
        $this->customers = $this->customers();
    }

    public function render()
    {
        return view('livewire.pages.user.index');
    }
}

<?php

namespace App\Livewire\Pages\User;

use App\Services\WooCommerceService;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

class Index extends Component
{
    public array $roles = [];
    public string $name = '';
    public string $last_name = '';
    public string $username = '';
    public string $password = '';

    public array $customers = [];

    public array $filters = [
        'name' => '',
        'username' => '',
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
        $this->username = '';
        $this->password = '';
        $this->customers = $this->customers();
    }

    #[Computed()]
    public function customers(): array
    {
        $query = [
            'per_page' => 100,
            'role' => 'all', // جلب جميع المستخدمين بغض النظر عن الدور
        ];

        // إضافة فلتر الاسم
        if (!empty($this->filters['name'])) {
            $query['search'] = $this->filters['name'];
        }

        // إضافة فلتر البريد الإلكتروني
        if (!empty($this->filters['username'])) {
            $query['search'] = $this->filters['username'];
        }

        // إضافة فلتر الدور - إذا كان محدد، استبدل 'all'
        if (!empty($this->filters['role'])) {
            $query['role'] = $this->filters['role'];
        }

        try {
            $response = $this->wooService->getCustomers($query);

            $customers = is_array($response) && isset($response['data']) ? $response['data'] : $response;

            // إذا كان هناك فلتر بريد إلكتروني، نقوم بتصفية النتائج يدوياً
            if (!empty($this->filters['username'])) {
                $customers = array_filter($customers, function($customer) {
                    return stripos($customer['username'] ?? '', $this->filters['username']) !== false;
                });
            }

            // معالجة حالة العميل إذا لم تكن موجودة
            foreach ($customers as &$customer) {
                if (!isset($customer['status'])) {
                    $customer['status'] = 'inactive';
                }
                if (!isset($customer['roles']) || !is_array($customer['roles'])) {
                    $customer['roles'] = [];
                }
            }

            // فلترة يدوية للحالة
            if (!empty($this->filters['status'])) {
                $customers = array_filter($customers, function($customer) {
                    return ($customer['status'] ?? 'inactive') === $this->filters['status'];
                });
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
        // التحقق من صحة البيانات قبل إرسالها (اختياري ولكنه موصى به)
        $this->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username', // تأكد من أن اسم المستخدم فريد
            'password' => 'required|string|min:8',
        ]);

        $data = [
            'email' => $this->username . '@veronastores.com', // بريد إلكتروني إلزامي
            'username' => $this->username, // <-- هذا هو السطر الجديد والمهم
            'first_name' => $this->name,
            'last_name' => $this->last_name,
            'password' => $this->password,
            // 'roles' => ['customer'], // عادةً ما يكون الدور الافتراضي هو 'customer'
        ];

        try {
            $response = $this->wooService->createUser($data);

            // إعادة تعيين الحقول بعد الإنشاء بنجاح
            $this->reset(['name', 'last_name', 'username', 'password']);

            // تحديث قائمة العملاء
            $this->customers = $this->customers();

            // إخفاء الـ modal (إذا كنت تستخدم modal)
            // $this->dispatch('close-modal', 'edit-profile');

            Toaster::success(__('تم إنشاء العميل بنجاح'));
        } catch (\Exception $e) {
            // يمكنك إظهار رسالة خطأ أكثر تحديدًا للمستخدم
            Toaster::error('حدث خطأ: ' . $e->getMessage());
            logger()->error('Error creating customer: ' . $e->getMessage());
        }
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
            'username' => '',
            'role' => '',
            'status' => '',
        ];
        $this->customers = $this->customers();
    }

    public function removeCustomer(int $id): void
    {
        try {
            $this->wooService->removeCustomer($id, true);
            dd('ereere');
        } catch (\Throwable $e) {
            dd($e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.pages.user.index');
    }
}

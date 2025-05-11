<div class="p-6 bg-white rounded shadow-sm">
    {{-- فلتر البحث --}}
    <div class="grid grid-cols-5 gap-4 mb-4">
        <flux:input wire:model.live.debounce.500ms="filters.customer_name" placeholder="اسم الزبون" />
        <flux:input wire:model.live.debounce.500ms="filters.order_number" placeholder="رقم الطلبية" />
        <flux:input type="date" wire:model="filters.date_from" />
        <flux:input type="date" wire:model="filters.date_to" />
        <flux:select wire:model="filters.status">
            <option value="">كل الحالات</option>
            <option value="pending">قيد الانتظار</option>
            <option value="processing">جاري المعالجة</option>
            <option value="completed">مكتمل</option>
            <option value="cancelled">ملغي</option>
        </flux:select>
    </div>

    {{-- جدول الطلبات --}}
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-2 text-start">رقم الطلب</th>
                <th class="px-4 py-2 text-start">الزبون</th>
                <th class="px-4 py-2 text-start">الحالة</th>
                <th class="px-4 py-2 text-start">المبلغ</th>
                <th class="px-4 py-2 text-start">التاريخ</th>
                <th class="px-4 py-2 text-start">العمليات</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse ($orders as $order)
                <tr>
                    <td class="px-4 py-2">#{{ $order['id'] }}</td>
                    <td class="px-4 py-2">{{ $order['billing']['first_name'] ?? '' }} {{ $order['billing']['last_name'] ?? '' }}</td>
                    <td class="px-4 py-2">{{ $order['status'] }}</td>
                    <td class="px-4 py-2">{{ $order['total'] }} ₪</td>
                    <td class="px-4 py-2">{{ \Carbon\Carbon::parse($order['date_created'])->translatedFormat('Y-m-d') }}</td>
                    <td class="px-4 py-2">
                        <flux:button href="{{ route('order.details', $order['id']) }}" variant="outline" size="sm">
                            <flux:icon name="eye" />
                        </flux:button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center py-6 text-gray-500">لا توجد طلبيات حالياً.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-6">
        <div class="flex justify-center">
            {{ $orders->links() }}
        </div>
    </div>

</div>

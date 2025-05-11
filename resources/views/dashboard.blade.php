<x-layouts.app :title="__('Dashboard')">
    <div class="flex flex-col gap-6">
        {{-- بطاقات الإحصائيات --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow">
                <div class="text-gray-500 dark:text-gray-400">طلبات هذا الشهر</div>
                <div class="text-2xl font-bold text-black dark:text-white">{{ $ordersThisMonth }}</div>
            </div>
            <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow">
                <div class="text-gray-500 dark:text-gray-400">عدد الزبائن</div>
                <div class="text-2xl font-bold text-black dark:text-white">{{ $customersCount }}</div>
            </div>
            <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow">
                <div class="text-gray-500 dark:text-gray-400">عدد الأصناف</div>
                <div class="text-2xl font-bold text-black dark:text-white">{{ $productsCount }}</div>
            </div>
        </div>

        {{-- حالات الطلبات --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach ($orderStatuses as $status => $count)
                <div class="bg-blue-100 dark:bg-blue-900 p-4 rounded-xl text-center shadow border border-blue-300 dark:border-blue-700">
                    <div class="text-sm text-blue-700 dark:text-blue-100">{{ ucfirst($status) }}</div>
                    <div class="text-xl font-bold text-blue-800 dark:text-blue-200">{{ $count }}</div>
                </div>
            @endforeach
        </div>

        {{-- آخر الطلبات --}}
        <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow">
            <h2 class="text-lg font-bold mb-4 text-gray-800 dark:text-white">آخر الطلبات</h2>
            <div class="overflow-auto">
                <table class="min-w-full text-sm text-left">
                    <thead class="border-b border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300">
                        <tr>
                            <th class="p-2">رقم الطلب</th>
                            <th class="p-2">الزبون</th>
                            <th class="p-2">الحالة</th>
                            <th class="p-2">التاريخ</th>
                            <th class="p-2">المجموع</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($latestOrders as $order)
                            <tr class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                <td class="p-2">#{{ $order->id }}</td>
                                <td class="p-2">{{ $order->customer_name }}</td>
                                <td class="p-2">
                                    <span class="inline-block px-2 py-1 text-xs rounded bg-gray-200 dark:bg-gray-700">
                                        {{ ucfirst($order->status) }}
                                    </span>
                                </td>
                                <td class="p-2">{{ $order->created_at->format('Y-m-d') }}</td>
                                <td class="p-2">{{ number_format($order->total, 2) }} ₪</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- المنتجات منخفضة المخزون --}}
        <div class="bg-white dark:bg-neutral-900 p-4 rounded-xl border border-red-300 dark:border-red-700 shadow">
            <h2 class="text-lg font-bold text-red-600 dark:text-red-400 mb-4">أصناف شارفت على الانتهاء</h2>
            <ul class="space-y-2">
                @foreach ($lowStockProducts as $product)
                    <li class="flex justify-between items-center">
                        <span class="text-gray-700 dark:text-gray-200">{{ $product->name }}</span>
                        <span class="text-red-600 font-semibold">{{ $product->stock }} متبقي</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</x-layouts.app>

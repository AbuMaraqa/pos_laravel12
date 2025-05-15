<div class="flex flex-col gap-8">

    {{-- بطاقات الإحصائيات --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

        {{-- 🛒 طلبات هذا الشهر --}}
        <div class="flex items-center gap-4 p-5 bg-white dark:bg-neutral-900 rounded-2xl border border-blue-200 dark:border-blue-800 shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="bg-blue-100 dark:bg-blue-800/50 p-3 rounded-full">
                <flux:icon name="shopping-cart" class="w-6 h-6 text-blue-600 dark:text-blue-300" />
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-300">طلبات هذا الشهر</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $ordersThisMonth }}</div>
            </div>
        </div>

        {{-- 👥 عدد الزبائن --}}
        <div class="flex items-center gap-4 p-5 bg-white dark:bg-neutral-900 rounded-2xl border border-green-200 dark:border-green-800 shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="bg-green-100 dark:bg-green-800/50 p-3 rounded-full">
                <flux:icon name="users" class="w-6 h-6 text-green-600 dark:text-green-300" />
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-300">عدد الزبائن</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $customersCount }}</div>
            </div>
        </div>

        {{-- 📦 عدد الأصناف --}}
        <div class="flex items-center gap-4 p-5 bg-white dark:bg-neutral-900 rounded-2xl border border-yellow-200 dark:border-yellow-800 shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">
            <div class="bg-yellow-100 dark:bg-yellow-800/50 p-3 rounded-full">
                <flux:icon name="archive-box" class="w-6 h-6 text-yellow-600 dark:text-yellow-300" />
            </div>
            <div>
                <div class="text-sm text-gray-500 dark:text-gray-300">عدد الأصناف</div>
                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $productsCount }}</div>
            </div>
        </div>

    </div>


    {{-- حالات الطلبات --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @if (!empty($orderStatuses))
            @foreach ($orderStatuses as $status => $count)
                <div
                    class="rounded-xl p-4 shadow-md border text-center transition-all duration-300 hover:shadow-lg hover:scale-105
                @if ($status === 'pending') bg-yellow-100 dark:bg-yellow-900/40 border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200
                @elseif($status === 'completed') bg-green-100 dark:bg-green-900/40 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200
                @elseif($status === 'processing') bg-blue-100 dark:bg-blue-900/40 border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200
                @else bg-red-100 dark:bg-red-900/40 border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 @endif">
                    <div class="text-sm font-medium">{{ __(ucfirst($status)) }}</div>
                    <div class="text-xl font-bold mt-1">{{ $count }}</div>
                </div>
            @endforeach
        @endif
    </div>

    {{-- آخر الطلبات --}}
    <div class="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-700 shadow-md hover:shadow-lg transition-all duration-300">
        <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white flex items-center gap-2">
            <flux:icon name="clock" class="w-5 h-5 text-gray-600 dark:text-gray-300" />
            <span>آخر الطلبات</span>
        </h2>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm text-right border-separate border-spacing-y-2">
                <thead class="text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="p-2">رقم الطلب</th>
                        <th class="p-2">الزبون</th>
                        <th class="p-2">الحالة</th>
                        <th class="p-2">المجموع</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($latestOrders as $order)
                        <tr class="bg-gray-50 dark:bg-neutral-800 rounded-xl shadow-sm hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors duration-200">
                            <td class="p-3 font-semibold">
                                <a href="{{ route('order.details', $order['id']) }}" class="text-blue-500 hover:text-blue-600 transition-colors">
                                    #{{ $order['id'] }}
                                </a>
                            </td>
                            <td class="p-3">
                                {{ ($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '') }}
                            </td>
                            <td class="p-3">
                                <span
                                    class="inline-flex px-2 py-1 text-xs font-medium rounded-full
                                    @if ($order['status'] === 'pending') bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-200
                                    @elseif($order['status'] === 'completed') bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200
                                    @elseif($order['status'] === 'processing') bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200
                                    @else bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 @endif">
                                    {{ ucfirst($order['status']) }}
                                </span>
                            </td>
                            <td class="p-3 font-bold">{{ number_format($order['total'], 2) }} ₪</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-gray-400 dark:text-gray-600 py-4">لا توجد طلبات</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- المنتجات منخفضة المخزون --}}
    <div class="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-red-300 dark:border-red-700 shadow-md hover:shadow-lg transition-all duration-300">
        <h2 class="text-xl font-semibold text-red-600 dark:text-red-400 mb-4 flex items-center gap-2">
            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />
            <span>أصناف شارفت على الانتهاء</span>
        </h2>
        <ul class="space-y-3">
            @forelse ($lowStockProducts as $product)
                <li
                    class="flex justify-between items-center px-5 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-100 border border-red-100 dark:border-red-800/50 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors duration-200">
                    <span class="font-medium">{{ $product['name'] }}</span>
                    <span class="font-semibold px-3 py-1 bg-red-100 dark:bg-red-800/50 rounded-full text-sm">{{ $product['stock_quantity'] }} متبقي</span>
                </li>
            @empty
                <li class="text-center text-gray-400 dark:text-gray-600 py-2">لا يوجد أصناف منخفضة المخزون</li>
            @endforelse
        </ul>
    </div>
</div>

{{--<div class="flex flex-col gap-8">--}}

{{--    --}}{{-- بطاقات الإحصائيات --}}
{{--    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">--}}

{{--        --}}{{-- 🛒 طلبات هذا الشهر --}}
{{--        <div class="flex items-center gap-4 p-5 bg-white dark:bg-neutral-900 rounded-2xl border border-blue-200 dark:border-blue-800 shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">--}}
{{--            <div class="bg-blue-100 dark:bg-blue-800/50 p-3 rounded-full">--}}
{{--                <flux:icon name="shopping-cart" class="w-6 h-6 text-blue-600 dark:text-blue-300" />--}}
{{--            </div>--}}
{{--            <div>--}}
{{--                <div class="text-sm text-gray-500 dark:text-gray-300">طلبات هذا الشهر</div>--}}
{{--                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $ordersThisMonth }}</div>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--        --}}{{-- 👥 عدد الزبائن --}}
{{--        <div class="flex items-center gap-4 p-5 bg-white dark:bg-neutral-900 rounded-2xl border border-green-200 dark:border-green-800 shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">--}}
{{--            <div class="bg-green-100 dark:bg-green-800/50 p-3 rounded-full">--}}
{{--                <flux:icon name="users" class="w-6 h-6 text-green-600 dark:text-green-300" />--}}
{{--            </div>--}}
{{--            <div>--}}
{{--                <div class="text-sm text-gray-500 dark:text-gray-300">عدد الزبائن</div>--}}
{{--                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $customersCount }}</div>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--        --}}{{-- 📦 عدد الأصناف --}}
{{--        <div class="flex items-center gap-4 p-5 bg-white dark:bg-neutral-900 rounded-2xl border border-yellow-200 dark:border-yellow-800 shadow-md hover:shadow-lg transition-all duration-300 transform hover:-translate-y-1">--}}
{{--            <div class="bg-yellow-100 dark:bg-yellow-800/50 p-3 rounded-full">--}}
{{--                <flux:icon name="archive-box" class="w-6 h-6 text-yellow-600 dark:text-yellow-300" />--}}
{{--            </div>--}}
{{--            <div>--}}
{{--                <div class="text-sm text-gray-500 dark:text-gray-300">عدد الأصناف</div>--}}
{{--                <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ $productsCount }}</div>--}}
{{--            </div>--}}
{{--        </div>--}}

{{--    </div>--}}


{{--    --}}{{-- حالات الطلبات --}}
{{--    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">--}}
{{--        @if (!empty($orderStatuses))--}}
{{--            @foreach ($orderStatuses as $status => $count)--}}
{{--                <div--}}
{{--                    class="rounded-xl p-4 shadow-md border text-center transition-all duration-300 hover:shadow-lg hover:scale-105--}}
{{--                @if ($status === 'pending') bg-yellow-100 dark:bg-yellow-900/40 border-yellow-200 dark:border-yellow-700 text-yellow-800 dark:text-yellow-200--}}
{{--                @elseif($status === 'completed') bg-green-100 dark:bg-green-900/40 border-green-200 dark:border-green-700 text-green-800 dark:text-green-200--}}
{{--                @elseif($status === 'processing') bg-blue-100 dark:bg-blue-900/40 border-blue-200 dark:border-blue-700 text-blue-800 dark:text-blue-200--}}
{{--                @else bg-red-100 dark:bg-red-900/40 border-red-200 dark:border-red-700 text-red-800 dark:text-red-200 @endif">--}}
{{--                    <div class="text-sm font-medium">{{ __(ucfirst($status)) }}</div>--}}
{{--                    <div class="text-xl font-bold mt-1">{{ $count }}</div>--}}
{{--                </div>--}}
{{--            @endforeach--}}
{{--        @endif--}}
{{--    </div>--}}

{{--    --}}{{-- آخر الطلبات --}}
{{--    <div class="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-neutral-200 dark:border-neutral-700 shadow-md hover:shadow-lg transition-all duration-300">--}}
{{--        <h2 class="text-xl font-semibold mb-4 text-gray-800 dark:text-white flex items-center gap-2">--}}
{{--            <flux:icon name="clock" class="w-5 h-5 text-gray-600 dark:text-gray-300" />--}}
{{--            <span>آخر الطلبات</span>--}}
{{--        </h2>--}}
{{--        <div class="overflow-x-auto">--}}
{{--            <table class="min-w-full text-sm text-right border-separate border-spacing-y-2">--}}
{{--                <thead class="text-gray-500 dark:text-gray-400">--}}
{{--                    <tr>--}}
{{--                        <th class="p-2">رقم الطلب</th>--}}
{{--                        <th class="p-2">الزبون</th>--}}
{{--                        <th class="p-2">الحالة</th>--}}
{{--                        <th class="p-2">المجموع</th>--}}
{{--                    </tr>--}}
{{--                </thead>--}}
{{--                <tbody>--}}
{{--                    @forelse ($latestOrders as $order)--}}
{{--                        <tr class="bg-gray-50 dark:bg-neutral-800 rounded-xl shadow-sm hover:bg-gray-100 dark:hover:bg-neutral-700 transition-colors duration-200">--}}
{{--                            <td class="p-3 font-semibold">--}}
{{--                                <a href="{{ route('order.details', $order['id']) }}" class="text-blue-500 hover:text-blue-600 transition-colors">--}}
{{--                                    #{{ $order['id'] }}--}}
{{--                                </a>--}}
{{--                            </td>--}}
{{--                            <td class="p-3">--}}
{{--                                {{ ($order['billing']['first_name'] ?? '') . ' ' . ($order['billing']['last_name'] ?? '') }}--}}
{{--                            </td>--}}
{{--                            <td class="p-3">--}}
{{--                                <span--}}
{{--                                    class="inline-flex px-2 py-1 text-xs font-medium rounded-full--}}
{{--                                    @if ($order['status'] === 'pending') bg-yellow-100 dark:bg-yellow-900/40 text-yellow-800 dark:text-yellow-200--}}
{{--                                    @elseif($order['status'] === 'completed') bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-200--}}
{{--                                    @elseif($order['status'] === 'processing') bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-200--}}
{{--                                    @else bg-red-100 dark:bg-red-900/40 text-red-800 dark:text-red-200 @endif">--}}
{{--                                    {{ ucfirst($order['status']) }}--}}
{{--                                </span>--}}
{{--                            </td>--}}
{{--                            <td class="p-3 font-bold">{{ number_format($order['total'], 2) }} ₪</td>--}}
{{--                        </tr>--}}
{{--                    @empty--}}
{{--                        <tr>--}}
{{--                            <td colspan="4" class="text-center text-gray-400 dark:text-gray-600 py-4">لا توجد طلبات</td>--}}
{{--                        </tr>--}}
{{--                    @endforelse--}}
{{--                </tbody>--}}
{{--            </table>--}}
{{--        </div>--}}
{{--    </div>--}}

{{--    --}}{{-- المنتجات منخفضة المخزون --}}
{{--    <div class="bg-white dark:bg-neutral-900 p-6 rounded-2xl border border-red-300 dark:border-red-700 shadow-md hover:shadow-lg transition-all duration-300">--}}
{{--        <h2 class="text-xl font-semibold text-red-600 dark:text-red-400 mb-4 flex items-center gap-2">--}}
{{--            <flux:icon name="exclamation-triangle" class="w-5 h-5 text-red-600 dark:text-red-400" />--}}
{{--            <span>أصناف شارفت على الانتهاء</span>--}}
{{--        </h2>--}}
{{--        <ul class="space-y-3">--}}
{{--            @forelse ($lowStockProducts as $product)--}}
{{--                <li--}}
{{--                    class="flex justify-between items-center px-5 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 text-red-700 dark:text-red-100 border border-red-100 dark:border-red-800/50 hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors duration-200">--}}
{{--                    <span class="font-medium">{{ $product['name'] }}</span>--}}
{{--                    <span class="font-semibold px-3 py-1 bg-red-100 dark:bg-red-800/50 rounded-full text-sm">{{ $product['stock_quantity'] }} متبقي</span>--}}
{{--                </li>--}}
{{--            @empty--}}
{{--                <li class="text-center text-gray-400 dark:text-gray-600 py-2">لا يوجد أصناف منخفضة المخزون</li>--}}
{{--            @endforelse--}}
{{--        </ul>--}}
{{--    </div>--}}
{{--</div>--}}

<div class="min-h-screen bg-gradient-to-br from-slate-50 via-slate-100 to-slate-50 dark:from-zinc-950 dark:via-zinc-900 dark:to-zinc-950">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
{{--        --}}{{-- Header --}}
{{--        <div class="mb-10">--}}
{{--            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-[1px] shadow-lg">--}}
{{--                <div class="relative rounded-2xl bg-white/95 px-6 py-6 sm:px-8 sm:py-7 dark:bg-zinc-950/95">--}}
{{--                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">--}}
{{--                        <div>--}}
{{--                            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900 dark:text-white">--}}
{{--                                {{ __('Dashboard') }}--}}
{{--                            </h1>--}}
{{--                            <p class="mt-2 text-sm sm:text-base text-slate-600 dark:text-slate-300">--}}
{{--                                {{ __('Welcome to your POS system dashboard') }}--}}
{{--                            </p>--}}
{{--                        </div>--}}
{{--                        <div class="flex items-center gap-3">--}}
{{--                            <span class="inline-flex items-center rounded-full border border-blue-100 bg-blue-50 px-3 py-1 text-xs font-medium text-blue-700 dark:border-blue-500/40 dark:bg-blue-500/10 dark:text-blue-200">--}}
{{--                                {{ __('POS Overview') }}--}}
{{--                            </span>--}}
{{--                            <a href="{{ route('pos.index') }}" wire:navigate--}}
{{--                               class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-400">--}}
{{--                                {{ __('Open POS') }}--}}
{{--                            </a>--}}
{{--                        </div>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}

        {{-- Grid --}}
        <div class="grid grid-cols-1 mt-5 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">

{{--            --}}{{-- Home Card --}}
{{--            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-blue-500/60 hover:shadow-lg dark:border-zinc-800/80 dark:bg-zinc-900/90 dark:hover:border-blue-400/60">--}}
{{--                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-blue-50/70 via-transparent to-blue-100/70 opacity-0 transition-opacity group-hover:opacity-100 dark:from-blue-500/10 dark:via-transparent dark:to-blue-900/30"></div>--}}

{{--                <div class="relative flex items-start gap-4 rtl:space-x-reverse">--}}
{{--                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-100/80 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300">--}}
{{--                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">--}}
{{--                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"--}}
{{--                                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>--}}
{{--                        </svg>--}}
{{--                    </div>--}}

{{--                    <div class="flex-1">--}}
{{--                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">--}}
{{--                            {{ __('Home') }}--}}
{{--                        </h3>--}}
{{--                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">--}}
{{--                            {{ __('Overview of your POS environment and shortcuts.') }}--}}
{{--                        </p>--}}
{{--                        <a href="{{ route('dashboard') }}" wire:navigate--}}
{{--                           class="mt-3 inline-flex text-sm font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">--}}
{{--                            {{ __('Go to Home') }}--}}
{{--                        </a>--}}
{{--                    </div>--}}
{{--                </div>--}}
{{--            </div>--}}

            {{-- Products Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-emerald-200/80 bg-white/90 p-5 shadow-sm backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/60 hover:shadow-lg dark:border-emerald-900/70 dark:bg-zinc-900/90 dark:hover:border-emerald-400/60">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-emerald-50/70 via-transparent to-emerald-100/70 opacity-0 transition-opacity group-hover:opacity-100 dark:from-emerald-500/10 dark:via-transparent dark:to-emerald-900/30"></div>

                <div class="relative flex items-start gap-4 rtl:space-x-reverse">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100/80 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2M4 13h2m8-8V4a1 1 0 00-1-1h-2a1 1 0 00-1 1v1M9 7h6"/>
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ __('Products') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Manage items, categories and catalog structure.') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 rtl:space-x-reverse">
                            <a href="{{ route('product.index') }}" wire:navigate
                               class="inline-flex text-xs font-medium text-emerald-700 hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-200">
                                {{ __('List') }}
                            </a>
                            <span class="text-slate-300 dark:text-zinc-600">|</span>
                            <a href="{{ route('product.add') }}" wire:navigate
                               class="inline-flex text-xs font-medium text-emerald-700 hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-200">
                                {{ __('Add') }}
                            </a>
                            <span class="text-slate-300 dark:text-zinc-600">|</span>
                            <a href="{{ route('category.index') }}" wire:navigate
                               class="inline-flex text-xs font-medium text-emerald-700 hover:text-emerald-900 dark:text-emerald-300 dark:hover:text-emerald-200">
                                {{ __('Categories') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            {{-- POS Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-purple-200/80 bg-white/90 p-5 shadow-sm backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-purple-500/60 hover:shadow-lg dark:border-purple-900/70 dark:bg-zinc-900/90 dark:hover:border-purple-400/60">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-purple-50/70 via-transparent to-purple-100/70 opacity-0 transition-opacity group-hover:opacity-100 dark:from-purple-500/10 dark:via-transparent dark:to-purple-900/30"></div>

                <div class="relative flex items-start gap-4 rtl:space-x-reverse">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-purple-100/80 text-purple-600 dark:bg-purple-500/15 dark:text-purple-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ __('Point of Sale') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Start selling, manage sessions and active carts.') }}
                        </p>
                        <a href="{{ route('pos.index') }}" wire:navigate
                           class="mt-3 inline-flex text-sm font-medium text-purple-600 hover:text-purple-800 dark:text-purple-300 dark:hover:text-purple-200">
                            {{ __('Open POS') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Orders Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-amber-200/80 bg-white/90 p-5 shadow-sm backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-amber-500/60 hover:shadow-lg dark:border-amber-900/70 dark:bg-zinc-900/90 dark:hover:border-amber-400/60">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-amber-50/70 via-transparent to-amber-100/70 opacity-0 transition-opacity group-hover:opacity-100 dark:from-amber-500/10 dark:via-transparent dark:to-amber-900/30"></div>

                <div class="relative flex items-start gap-4 rtl:space-x-reverse">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100/80 text-amber-600 dark:bg-amber-500/15 dark:text-amber-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ __('Orders') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Track sales orders, statuses and payments.') }}
                        </p>
                        <a href="{{ route('order.index') }}" wire:navigate
                           class="mt-3 inline-flex text-sm font-medium text-amber-600 hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200">
                            {{ __('View Orders') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Clients Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-teal-200/80 bg-white/90 p-5 shadow-sm backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-teal-500/60 hover:shadow-lg dark:border-teal-900/70 dark:bg-zinc-900/90 dark:hover:border-teal-400/60">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-teal-50/70 via-transparent to-teal-100/70 opacity-0 transition-opacity group-hover:opacity-100 dark:from-teal-500/10 dark:via-transparent dark:to-teal-900/30"></div>

                <div class="relative flex items-start gap-4 rtl:space-x-reverse">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-teal-100/80 text-teal-600 dark:bg-teal-500/15 dark:text-teal-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ __('Clients') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Manage your customers and their contact details.') }}
                        </p>
                        <a href="{{ route('client.index') }}" wire:navigate
                           class="mt-3 inline-flex text-sm font-medium text-teal-600 hover:text-teal-800 dark:text-teal-300 dark:hover:text-teal-200">
                            {{ __('Manage Clients') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Reports Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-indigo-200/80 bg-white/90 p-5 shadow-sm backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-indigo-500/60 hover:shadow-lg dark:border-indigo-900/70 dark:bg-zinc-900/90 dark:hover:border-indigo-400/60">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-indigo-50/70 via-transparent to-indigo-100/70 opacity-0 transition-opacity group-hover:opacity-100 dark:from-indigo-500/10 dark:via-transparent dark:to-indigo-900/30"></div>

                <div class="relative flex items-start gap-4 rtl:space-x-reverse">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-100/80 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 17v-2m3 2v-4m3 2v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ __('Reports') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Analyze performance, sales and POS activity.') }}
                        </p>
                        <a href="{{ route('report.index') }}" wire:navigate
                           class="mt-3 inline-flex text-sm font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-300 dark:hover:text-indigo-200">
                            {{ __('View Reports') }}
                        </a>
                    </div>
                </div>
            </div>

            {{-- Settings Card --}}
            <div class="group relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white/90 p-5 shadow-sm backdrop-blur-sm transition-all duration-300 hover:-translate-y-1 hover:border-slate-500/60 hover:shadow-lg dark:border-zinc-800/80 dark:bg-zinc-900/90 dark:hover:border-slate-400/60">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-slate-50/70 via-transparent to-slate-100/70 opacity-0 transition-opacity group-hover:opacity-100 dark:from-slate-500/10 dark:via-transparent dark:to-slate-900/30"></div>

                <div class="relative flex items-start gap-4 rtl:space-x-reverse">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100/80 text-slate-700 dark:bg-slate-500/15 dark:text-slate-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>

                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-slate-900 dark:text-white">
                            {{ __('Settings') }}
                        </h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Control stores, branches and general preferences.') }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2 rtl:space-x-reverse">
                            <a href="{{ route('store.index') }}" wire:navigate
                               class="inline-flex text-xs font-medium text-slate-700 hover:text-slate-900 dark:text-slate-200 dark:hover:text-white">
                                {{ __('Stores') }}
                            </a>
                            <span class="text-slate-300 dark:text-zinc-600">|</span>
                            <a href="{{ route('settings.index') }}" wire:navigate
                               class="inline-flex text-xs font-medium text-slate-700 hover:text-slate-900 dark:text-slate-200 dark:hover:text-white">
                                {{ __('General') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

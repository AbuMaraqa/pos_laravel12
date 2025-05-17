<div class="container mx-auto p-4 max-w-5xl">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">{{ __('Client Profile') }}</h1>
        <div class="flex gap-2">
            {{-- <button
                class="inline-flex items-center justify-center rounded-md text-sm font-medium px-3 py-2 border border-gray-200 shadow-sm bg-white hover:bg-gray-50">
                <i class="ri-edit-line mr-2 h-4 w-4"></i>
                Edit Profile
            </button> --}}
            <flux:button variant="primary">
                {{ __('Edit Profile') }}
            </flux:button>
            <div class="relative">
                <button
                    class="inline-flex items-center justify-center rounded-md text-sm font-medium p-2 hover:bg-gray-100">
                    <i class="ri-more-2-line h-5 w-5"></i>
                </button>
                <div class="hidden absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10"
                    id="dropdown-menu">
                    <div class="py-1">
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Export
                            Profile</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Print
                            Details</a>
                        <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Archive
                            Client</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left Column - Profile Summary -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
            <div class="p-6 flex flex-col items-center text-center pb-2">
                <div>
                    <flux:avatar name="{{ $customer['first_name'] . ' ' . $customer['last_name'] }}"
                        class="w-24 h-24 mb-4" />
                </div>
                <h2 class="text-xl font-semibold">{{ $customer['first_name'] . ' ' . $customer['last_name'] }}</h2>
                <div class="flex items-center justify-center mt-1">
                    <span
                        class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">

                    </span>
                </div>
            </div>
            <div class="px-6 py-2 text-center">
                <div class="flex justify-center gap-2 mb-6">
                    <button
                        class="rounded-full h-8 w-8 flex items-center justify-center border border-gray-200 hover:bg-gray-50">
                        <i class="ri-mail-line h-4 w-4"></i>
                    </button>
                    <button
                        class="rounded-full h-8 w-8 flex items-center justify-center border border-gray-200 hover:bg-gray-50">
                        <i class="ri-phone-line h-4 w-4"></i>
                    </button>
                    <button
                        class="rounded-full h-8 w-8 flex items-center justify-center border border-gray-200 hover:bg-gray-50">
                        <i class="ri-map-pin-line h-4 w-4"></i>
                    </button>
                </div>

                <div class="space-y-4">
                    <div class="">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ __('Email') }}</p>
                            <p class="">
                                <i class="ri-mail-line h-4 w-4 text-gray-500"></i>
                                {{ $customer['email'] }}
                            </p>
                        </div>
                    </div>
                    <div class="">
                        <p class="text-sm font-medium text-gray-500">{{ __('Phone') }}</p>
                        <p class="">
                            <i class="ri-phone-line h-4 w-4 text-gray-500"></i>
                            {{ empty($customer['billing']['phone']) ? __('No Phone Number') : $customer['billing']['phone'] }}
                        </p>
                    </div>
                    <div class="">
                        <p class="text-sm font-medium text-gray-500">{{ __('Address') }}</p>
                        <p class="">
                            <i class="ri-map-pin-line h-4 w-4 text-gray-500"></i>
                        <p>{{ empty($customer['billing']['address_1']) ? __('No Address') : $customer['billing']['address_1'] }}
                        </p>
                        <p>{{ empty($customer['billing']['address_2']) ? __('No Address') : $customer['billing']['address_2'] }}
                        </p>
                        </p>
                    </div>

                    <div class="">
                        <p class="text-sm font-medium text-gray-500">{{ __('Company') }}</p>
                        <p class="">
                            <i class="ri-briefcase-line h-4 w-4 text-gray-500"></i>
                            {{ empty($customer['billing']['company']) ? __('No Company') : $customer['billing']['company'] }}
                        </p>
                    </div>
                </div>
            </div>
            <div class="p-6 pt-4">
                {{-- <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
                    Schedule Meeting
                </button> --}}
            </div>
        </div>

        <!-- Right Column - Detailed Information -->
        <div class="md:col-span-2 space-y-6">
            {{-- <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div class="p-6 pb-2">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-semibold">{{ __('Personal Information') }}</h3>
                        <button class="text-gray-500 hover:bg-gray-100 p-1 rounded-md" id="toggle-details">
                            <i class="ri-arrow-up-s-line h-5 w-5" id="chevron-icon"></i>
                        </button>
                    </div>
                </div>
                <div class="px-6 pb-6" id="details-content">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ __('Email') }}</p>
                            <p class="flex items-center gap-2">
                                <i class="ri-mail-line h-4 w-4 text-gray-500"></i>
                                {{ $customer['email'] }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ __('Phone') }}</p>
                            <p class="flex items-center gap-2">
                                <i class="ri-phone-line h-4 w-4 text-gray-500"></i>
                                {{ empty($customer['billing']['phone']) ? __('No Phone Number') : $customer['billing']['phone'] }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ __('Address') }}</p>
                            <p class="flex items-center gap-2">
                                <i class="ri-map-pin-line h-4 w-4 text-gray-500"></i>
                            <p>{{ empty($customer['billing']['address_1']) ? __('No Address') : $customer['billing']['address_1'] }}
                            </p>
                            <p>{{ empty($customer['billing']['address_2']) ? __('No Address') : $customer['billing']['address_2'] }}
                            </p>
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ __('Birthday') }}</p>
                            <p class="flex items-center gap-2">
                                <i class="ri-calendar-line h-4 w-4 text-gray-500"></i>
                                April 15, 1985
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ __('Company') }}</p>
                            <p class="flex items-center gap-2">
                                <i class="ri-briefcase-line h-4 w-4 text-gray-500"></i>
                                {{ empty($customer['billing']['company']) ? __('No Company') : $customer['billing']['company'] }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-sm font-medium text-gray-500">{{ __('Timezone') }}</p>
                            <p class="flex items-center gap-2">
                                <i class="ri-time-line h-4 w-4 text-gray-500"></i>
                                {{ __('Palestine') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div> --}}

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-5 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800">الطلبات الأخيرة</h2>
                    <p class="text-sm text-gray-500">قائمة بأحدث الطلبات والمعاملات</p>
                </div>

                <div class="px-5 py-2">
                    <div class="space-y-3">
                        @forelse ($orders as $order)
                            <a wire:navigate href="{{ route('order.details', ['order' => $order['id']]) }}"
                                class="block group">
                                <div
                                    class="flex items-center justify-between p-3 rounded-lg transition-all hover:bg-blue-50">
                                    <div class="flex items-center space-x-4 space-x-reverse">
                                        {{-- <div
                                            class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                            <i class="ri-shopping-bag-3-line text-blue-600 text-lg"></i>
                                        </div> --}}
                                        <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-4">
                                            <div>
                                                <p class="text-sm font-bold text-gray-800 group-hover:text-blue-600">
                                                    #{{ $order['id'] }}</p>
                                                <p class="text-xs text-gray-500">{{ $order['date_created_gmt'] }}</p>
                                            </div>
                                            <div class="sm:border-r sm:border-gray-200 sm:pr-4 hidden sm:block">
                                                <p class="text-xs text-gray-500">
                                                    {{ \Carbon\Carbon::parse($order['date_created_gmt'])->diffForHumans() }}
                                                </p>
                                            </div>
                                            <div>
                                                <span
                                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if ($order['status'] == 'completed') bg-green-100 text-green-800
                                        @elseif($order['status'] == 'processing') bg-yellow-100 text-yellow-800
                                        @elseif($order['status'] == 'on-hold') bg-orange-100 text-orange-800
                                        @elseif($order['status'] == 'cancelled') bg-red-100 text-red-800
                                        @elseif($order['status'] == 'shipped') bg-purple-100 text-purple-800
                                        @else bg-gray-100 text-gray-800 @endif">
                                                    {{ $order['status'] }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center">
                                        <p class="text-sm font-bold text-gray-800 ml-2">{{ $order['total'] }}</p>
                                        <i
                                            class="ri-arrow-left-s-line text-gray-400 group-hover:text-blue-500 group-hover:transform group-hover:translate-x-1 transition-all"></i>
                                    </div>
                                </div>
                            </a>
                        @empty
                            <div class="py-8 text-center">
                                <div
                                    class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                                    <i class="ri-shopping-bag-line text-gray-400 text-2xl"></i>
                                </div>
                                <p class="text-gray-500 font-medium">{{ __('No recent orders') }}</p>
                                <p class="text-sm text-gray-400 mt-1">ستظهر الطلبات الجديدة هنا عند إنشائها</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 text-center">
                    <a href="#"
                        class="text-sm font-medium text-blue-600 hover:text-blue-700 inline-flex items-center">
                        عرض جميع الطلبات
                        <i class="ri-arrow-left-s-line ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

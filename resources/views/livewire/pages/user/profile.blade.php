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
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium">Client Since</span>
                        <span class="text-sm">Jan 2022</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium">Projects</span>
                        <span class="text-sm">12</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium">Revenue</span>
                        <span class="text-sm">$85,400</span>
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
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
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
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
                <div>
                    <div class="p-6">
                        <h3 class="text-lg font-semibold">{{ __('Orders') }}</h3>
                        <p class="text-sm text-gray-500">{{ __('Last 30 days') }}</p>
                    </div>
                    <div class="px-6">
                        <div class="space-y-4">
                            <div class="flex items-start gap-4 pb-4 border-b">
                                <div class="bg-gray-100 rounded-full p-2 mt-1">
                                    <i class="ri-calendar-line h-4 w-4"></i>
                                </div>
                                <div>
                                    <p class="font-medium">Meeting scheduled</p>
                                    <p class="text-sm text-gray-500">
                                        Quarterly review meeting scheduled for next week
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">2 days ago</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 pb-4 border-b">
                                <div class="bg-gray-100 rounded-full p-2 mt-1">
                                    <i class="ri-file-list-line h-4 w-4"></i>
                                </div>
                                <div>
                                    <p class="font-medium">Contract updated</p>
                                    <p class="text-sm text-gray-500">
                                        New contract terms have been signed and approved
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">1 week ago</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-4 pb-4 border-b">
                                <div class="bg-gray-100 rounded-full p-2 mt-1">
                                    <i class="ri-message-3-line h-4 w-4"></i>
                                </div>
                                <div>
                                    <p class="font-medium">Email conversation</p>
                                    <p class="text-sm text-gray-500">
                                        Discussed project timeline and deliverables
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1">2 weeks ago</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 flex justify-end">
                        <button class="text-sm text-gray-500 hover:text-gray-700">
                            View All Activity
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

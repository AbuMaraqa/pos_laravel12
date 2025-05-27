<div>
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-4 md:p-8">
        <div class="mx-auto max-w-2xl">
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-blue-100">
                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0h4M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-gray-900">إعدادات الموقع</h1>
                <p class="mt-2 text-gray-600">قم بتخصيص اسم موقعك والشعار الخاص بك</p>
            </div>

            <!-- Settings Card -->
            <div class="rounded-xl bg-white/80 backdrop-blur-sm shadow-xl border border-white/20">
                <div class="p-6 border-b border-gray-100">
                    <h2 class="text-xl font-semibold text-gray-900">الإعدادات العامة</h2>
                    <p class="mt-1 text-sm text-gray-600">يمكنك تحديث معلومات موقعك الأساسية من هنا</p>
                </div>

                <div class="p-6 space-y-8">
                    <!-- Site Name Field -->
                    <flux:field class="space-y-3">
                        <flux:label class="block text-sm font-medium text-gray-700">
                            {{ __('Site Name') }}
                        </flux:label>
                        <flux:input
                            wire:model.defer="settings.site_name"
                            class="block w-full h-12 px-4 text-gray-900 bg-white border border-gray-200 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all duration-200 placeholder-gray-400"
                            placeholder="أدخل اسم الموقع"
                        />
                    </flux:field>

                    <!-- Divider -->
                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gray-200"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="bg-white px-4 text-sm text-gray-500">الشعار</span>
                        </div>
                    </div>

                    <!-- Logo Field -->
                    <flux:field class="space-y-4">
                        <flux:label class="block text-sm font-medium text-gray-700">
                            {{ __('Logo') }}
                        </flux:label>

                        <!-- Logo Preview Area -->
                        <div class="relative rounded-xl border-2 border-dashed border-gray-200 bg-gray-50/50 p-8 text-center hover:border-blue-300 hover:bg-blue-50/30 transition-all duration-300">
                            <div class="space-y-3">
                                {{-- @if ($logo)
                                    <img src="{{ $logo->temporaryUrl() }}" class="mx-auto h-24 max-w-xs object-contain rounded-lg shadow-sm" alt="Logo Preview">
                                    <p class="text-sm text-blue-600 font-medium">معاينة الشعار الجديد</p>
                                @else --}}
                                    <img src="{{ app(\App\Settings\GeneralSettings::class)->getLogoUrl() }}" class="mx-auto h-24 max-w-xs object-contain rounded-lg shadow-sm" alt="Current Logo">
                                    <p class="text-sm text-gray-500">الشعار الحالي</p>
                                {{-- @endif --}}
                            </div>
                        </div>

                        <!-- File Upload Button -->
                        <div class="relative">
                            <input
                                type="file"
                                wire:model="logo"
                                accept="image/*"
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                                id="logo-upload"
                            />
                            <label
                                for="logo-upload"
                                class="flex items-center justify-center w-full h-12 px-6 text-sm font-medium text-gray-700 bg-white border border-gray-200 rounded-lg shadow-sm cursor-pointer hover:bg-gray-50 hover:border-gray-300 focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500 transition-all duration-200"
                            >
                                <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                اختر شعار جديد
                            </label>
                        </div>

                        <!-- File Info -->
                        <p class="text-xs text-gray-500 text-center">
                            يُفضل استخدام صور بصيغة PNG أو JPG • الحد الأقصى 2 ميجابايت
                        </p>

                        <!-- Loading State -->
                        <div wire:loading wire:target="logo" class="flex items-center justify-center space-x-2 text-blue-600">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">جاري رفع الملف...</span>
                        </div>
                    </flux:field>

                    <!-- Save Button -->
                    <div class="flex justify-end pt-6 border-t border-gray-100">
                        <flux:button
                            variant="primary"
                            wire:click='save'
                            class="inline-flex items-center px-6 py-3 text-sm font-medium text-white bg-gradient-to-r border border-transparent rounded-lg shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 transform hover:scale-105"
                            wire:loading.attr="disabled"
                            wire:target="save"
                        >
                            <!-- Loading State -->
                            <span wire:loading wire:target="save" class="flex items-center">
                                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                جاري الحفظ...
                            </span>

                            <!-- Normal State -->
                            <span wire:loading.remove wire:target="save" class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                {{ __('Save') }}
                            </span>
                        </flux:button>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500">
                    سيتم تطبيق التغييرات على جميع صفحات الموقع فور الحفظ
                </p>
            </div>
        </div>
    </div>

    <!-- Custom CSS for backdrop blur (if not supported by your Tailwind version) -->
    <style>
        .backdrop-blur-sm {
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        /* RTL Support */
        [dir="rtl"] .space-x-2 > * + * {
            margin-right: 0.5rem;
            margin-left: 0;
        }
    </style>
</div>
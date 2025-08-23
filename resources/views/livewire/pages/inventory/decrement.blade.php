<div class="bg-gray-50 min-h-screen p-6">
    <!-- Search Section -->
    <div class="max-w-7xl mx-auto bg-white rounded-xl shadow-sm p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:space-x-4 md:space-x-reverse">
            <div class="flex-1">
                <flux:field class="mb-0">
                    <flux:label class="text-gray-700">{{ __('أدخل رقم المنتج') }}</flux:label>
                    <div class="relative flex">
                        <flux:input wire:model.live="productId" wire:keydown.enter="searchProduct" type="number"
                                    min="1" class="ltr:pl-10 rtl:pr-10" placeholder="{{ __('أدخل رقم المنتج...') }}"
                                    autofocus />
                        <div class="absolute inset-y-0 ltr:left-3 rtl:right-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                 fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                      clip-rule="evenodd" />
                            </svg>
                        </div>
                        <flux:button wire:click="searchProduct" color="primary" class="mr-2">
                            {{ __('بحث') }}
                        </flux:button>
                    </div>
                </flux:field>
            </div>
        </div>
    </div>

    <!-- Products Table Section -->
    <div class="max-w-7xl mx-auto">
        @if (count($scannedProducts) > 0)
            <!-- Total Quantity Card -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <div class="flex justify-between items-center">
                    <div class="flex items-center space-x-4 space-x-reverse">
                        <div class="p-2 bg-primary-100 rounded-lg">
                            <svg class="h-4 w-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">{{ __('إجمالي الكمية') }}</p>
                            <p class="text-2xl font-bold text-gray-900">{{ $this->totalQuantity }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2 items-center space-x-4 space-x-reverse">
                        <flux:select style="width: 200px" wire:model="storeId" placeholder="{{ __('Select Store') }}">
                            @foreach ($stores as $store)
                                <flux:select.option value="{{ $store->id }}">{{ $store->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:button wire:click="saveQuantities" color="success" class="gap-2">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M5 13l4 4L19 7" />
                            </svg>
                            {{ __('حفظ التعديلات') }}
                        </flux:button>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    @if (count($pendingProducts) > 0)
                        <div class="mt-2 text-sm text-yellow-600">
                            {{ __('جاري معالجة المنتجات التالية:') }}
                            {{ implode(', ', $pendingProducts) }}
                        </div>
                    @endif
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th scope="col"
                                class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('رقم الباركود') }}</th>
                            <th scope="col"
                                class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('المنتج') }}</th>
                            <th scope="col"
                                class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('رقم المنتج') }}</th>
                            <th scope="col"
                                class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('الكمية المتوفرة') }}</th>
                            <th scope="col"
                                class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('الكمية المطلوبة') }}</th>
                            <th scope="col"
                                class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('الإجراءات') }}</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">

                        @foreach ($scannedProducts as $productId => $product)
                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                <td
                                    class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium text-gray-900">
                                    {{ $productId }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                    {{ $product['name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500">
                                    {{ $product['sku'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <span
                                        class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full
                                        {{ $product['stock_quantity'] > 10 ? 'bg-green-100 text-green-800' :
                                           ($product['stock_quantity'] > 0 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                        {{ $product['stock_quantity'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex flex-col gap-2">
                                        <!-- عرض الكمية المتوفرة -->
                                        <div class="text-xs text-gray-500">
                                            متوفر: {{ $product['stock_quantity'] }}
                                        </div>

                                        <!-- أدوات التحكم في الكمية -->
                                        <div class="flex items-center gap-2">
                                            <flux:button
                                                wire:click="decrementQuantity({{ $productId }})"
                                                color="secondary" size="xs" class="!p-1">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2" d="M20 12H4" />
                                                </svg>
                                            </flux:button>

                                            <input
                                                type="number"
                                                min="1"
                                                max="{{ $product['stock_quantity'] }}"
                                                wire:model.live="scannedProducts.{{ $productId }}.quantity"
                                                class="w-24 border border-gray-300 text-center py-1 text-sm"
                                                style="background-color: #52525C; color: white; min-width: 150px; max-width: 150px; min-height: 100px; max-height: 100px; border-radius: 10px; font-size: 25px; font-weight: bold;" />

                                            @php
                                                $isDisabled = ((int) $product['quantity']) >= ((int) $product['stock_quantity']);
                                            @endphp

                                            <flux:button
                                                wire:click="incrementQuantity({{ $productId }})"
                                                color="secondary" size="xs" class="!p-1"
                                                >
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                     stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                          stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                            </flux:button>
                                        </div>

                                        <!-- تحذير إذا كانت الكمية تقترب من الحد الأقصى -->
                                        @php
                                            $stockQuantity = (int) $product['stock_quantity'];
                                            $currentQuantity = (int) $product['quantity'];
                                        @endphp

                                        @if($currentQuantity == $stockQuantity)
                                            <div class="text-xs text-orange-600">
                                                ⚠️ الحد الأقصى
                                            </div>
                                        @elseif($currentQuantity > ($stockQuantity * 0.8))
                                            <div class="text-xs text-yellow-600">
                                                ⚠️ قريب من الحد الأقصى
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                    <flux:button wire:click="removeProduct({{ $productId }})" color="danger"
                                                 size="xs" class="!p-1">
                                        <span class="sr-only">{{ __('حذف') }}</span>
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="bg-white rounded-xl shadow-sm p-4 text-center">
                <svg class="mx-auto w-10 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">{{ __('لم يتم إضافة منتجات بعد') }}</h3>
                <p class="mt-2 text-sm text-gray-500">{{ __('قم بإدخال رقم المنتج للبدء في إضافة المنتجات.') }}</p>
            </div>
        @endif
    </div>
</div>

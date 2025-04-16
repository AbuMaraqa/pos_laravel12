<div>
    <div class="mb-4">
        <flux:field>
            <flux:label>{{ __('أدخل رقم المنتج') }}</flux:label>
            <flux:input
                wire:model.live="productId"
                wire:keydown.enter="searchProduct"
                type="number"
                min="1"
                autofocus
                placeholder="{{ __('أدخل رقم المنتج...') }}"
            />
        </flux:field>

        @if($error)
            <div class="mt-2 text-red-600 text-sm">
                {{ $error }}
            </div>
        @endif

        @if($success)
            <div class="mt-2 text-green-600 text-sm">
                {{ $success }}
            </div>
        @endif
    </div>

    <div class="mt-6">
        @if(count($scannedProducts) > 0)
            <div class="mb-4 bg-white rounded-lg shadow p-4">
                <div class="flex justify-between items-center">
                    <div class="text-right">
                        <span class="text-gray-600">{{ __('إجمالي الكمية:') }}</span>
                        <span class="font-bold text-lg mr-2">{{ $this->totalQuantity }}</span>
                    </div>
                    <div>
                        <button
                            wire:click="saveQuantities"
                            class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg"
                        >
                            {{ __('حفظ التعديلات') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('رقم الباركود') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('المنتج') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('رقم المنتج') }}</th>
                            {{-- <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('السعر') }}</th> --}}
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('الكمية المتوفرة') }}</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('الكمية المطلوبة') }}</th>
                            {{-- <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('المجموع') }}</th> --}}
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('الإجراءات') }}</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($scannedProducts as $productId => $product)
                            <tr wire:key="product-{{ $productId }}">
                                <td class="px-6 py-4 whitespace-nowrap text-right">{{ $productId }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">{{ $product['name'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">{{ $product['sku'] }}</td>
                                {{-- <td class="px-6 py-4 whitespace-nowrap text-right">{{ $product['price'] }}</td> --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right">{{ $product['stock_quantity'] }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <div class="flex items-center justify-end space-x-2 space-x-reverse">
                                        <button
                                            wire:click="updateQuantity({{ $productId }}, {{ $product['quantity'] - 1 }})"
                                            class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300"
                                        >-</button>
                                        <span class="mx-2">{{ $product['quantity'] }}</span>
                                        <button
                                            wire:click="updateQuantity({{ $productId }}, {{ $product['quantity'] + 1 }})"
                                            class="px-2 py-1 bg-gray-200 rounded hover:bg-gray-300"
                                            @if($product['quantity'] >= $product['stock_quantity']) disabled @endif
                                        >+</button>
                                    </div>
                                </td>
                                {{-- <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                    {{ number_format($product['quantity'] * floatval($product['price']), 2) }}
                                </td> --}}
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button
                                        wire:click="removeProduct({{ $productId }})"
                                        class="text-red-600 hover:text-red-900"
                                    >
                                        {{ __('حذف') }}
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center text-gray-500 py-4">
                {{ __('لم يتم إضافة منتجات بعد') }}
            </div>
        @endif
    </div>
</div>

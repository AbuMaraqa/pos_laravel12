<div>
    {{-- ✅ معلومات التشخيص المحسنة --}}
{{--    @if(app()->environment('local'))--}}
{{--        <div class="mb-4 p-4 bg-gray-100 border rounded">--}}
{{--            <h4 class="font-bold text-sm mb-2">معلومات التشخيص المحسنة:</h4>--}}
{{--            <div class="text-xs space-y-1">--}}
{{--                <div><strong>Product ID:</strong> {{ $productId ?? 'null' }}</div>--}}
{{--                <div><strong>Loaded Attributes:</strong> {{ count($loadedAttributes) }}</div>--}}
{{--                <div><strong>Variations:</strong> {{ count($variations) }}</div>--}}
{{--                <div><strong>Attribute Map:</strong> {{ count($attributeMap) }}</div>--}}
{{--                <div><strong>Selected Attributes Keys:</strong> {{ count($selectedAttributes) }}</div>--}}

{{--                --}}{{-- عرض Selected Attributes بالتفصيل --}}
{{--                @if(!empty($selectedAttributes))--}}
{{--                    <div><strong>Selected Attributes Details (Component):</strong></div>--}}
{{--                    <div class="bg-white p-2 rounded mt-1 max-h-48 overflow-y-auto">--}}
{{--                        @foreach($selectedAttributes as $attrId => $terms)--}}
{{--                            <div class="mb-2 border-b pb-2">--}}
{{--                                <strong>Attribute {{ $attrId }}:</strong>--}}
{{--                                @if(is_array($terms))--}}
{{--                                    <div class="ml-4">--}}
{{--                                        @foreach($terms as $termId => $value)--}}
{{--                                            <div class="text-xs">--}}
{{--                                                Term {{ $termId }}:--}}
{{--                                                <span class="@if($value) text-green-600 font-bold @else text-gray-400 @endif">--}}
{{--                                                {{ $value ? 'TRUE ✓' : 'FALSE ✗' }}--}}
{{--                                            </span>--}}
{{--                                            </div>--}}
{{--                                        @endforeach--}}
{{--                                    </div>--}}
{{--                                @else--}}
{{--                                    <span class="text-orange-600">Not an array: {{ json_encode($terms) }}</span>--}}
{{--                                @endif--}}
{{--                            </div>--}}
{{--                        @endforeach--}}
{{--                    </div>--}}
{{--                @else--}}
{{--                    <div class="text-red-600"><strong>⚠️ Selected Attributes is EMPTY in Component!</strong></div>--}}
{{--                @endif--}}

{{--                --}}{{-- عرض حالة التحديد الفعلية في النموذج --}}
{{--                @if(!empty($loadedAttributes))--}}
{{--                    <div><strong>Form Selection Status:</strong></div>--}}
{{--                    <div class="bg-white p-2 rounded mt-1">--}}
{{--                        @foreach($loadedAttributes as $attribute)--}}
{{--                            <div class="mb-1">--}}
{{--                                <strong>{{ $attribute['name'] }} (ID: {{ $attribute['id'] }}):</strong>--}}
{{--                                @if(isset($selectedAttributes[$attribute['id']]) && is_array($selectedAttributes[$attribute['id']]))--}}
{{--                                    @php--}}
{{--                                        $selectedCount = count(array_filter($selectedAttributes[$attribute['id']]));--}}
{{--                                        $totalCount = count($selectedAttributes[$attribute['id']]);--}}
{{--                                    @endphp--}}
{{--                                    <span class="@if($selectedCount > 0) text-green-600 @else text-red-600 @endif">--}}
{{--                                    {{ $selectedCount }}/{{ $totalCount }} selected--}}
{{--                                </span>--}}
{{--                                @else--}}
{{--                                    <span class="text-red-600">No data</span>--}}
{{--                                @endif--}}
{{--                            </div>--}}
{{--                        @endforeach--}}
{{--                    </div>--}}
{{--                @endif--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    @endif--}}
    {{-- عرض رسالة إذا لم تكن هناك بيانات --}}
    @if(empty($variations) && empty($attributeMap) && $productId)
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-yellow-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                <div>
                    <p class="text-yellow-800 font-medium">لا توجد متغيرات محملة</p>
                    <p class="text-yellow-700 text-sm">إما أن المنتج لا يحتوي على متغيرات، أو هناك مشكلة في تحميل البيانات</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-6">
        @forelse ($loadedAttributes as $attribute)
            <div class="p-4 border rounded shadow-md bg-white">
                <h3 class="font-semibold text-xl mb-2">
                    {{ $attribute['name'] }}
                    <span class="text-sm text-gray-500">({{ count($attributeTerms[$attribute['id']] ?? []) }})</span>
                </h3>

                <div class="flex flex-wrap gap-2">
                    @php
                        $hasSelectedTerms = false;
                        if (isset($selectedAttributes[$attribute['id']])) {
                            if (is_array($selectedAttributes[$attribute['id']])) {
                                $hasSelectedTerms = count(array_filter($selectedAttributes[$attribute['id']])) > 0;
                            } else {
                                $hasSelectedTerms = count($selectedAttributes[$attribute['id']]) > 0;
                            }
                        }
                    @endphp

                    @foreach ($attributeTerms[$attribute['id']] ?? [] as $term)
                        <label class="inline-flex items-center p-2 border rounded-md hover:bg-gray-50 cursor-pointer
                            @if(isset($selectedAttributes[$attribute['id']][$term['id']]) && $selectedAttributes[$attribute['id']][$term['id']])
                                bg-blue-50 border-blue-300
                            @endif"
                        >
                            <input
                                type="checkbox"
                                wire:model="selectedAttributes.{{ $attribute['id'] }}.{{ $term['id'] }}" {{-- تم تغيير هذا السطر --}}
                                class="form-checkbox h-5 w-5 text-blue-600"
                            >
                            <span class="mr-2 text-sm">{{ $term['name'] }}</span>
                        </label>
                    @endforeach

                    @if ($hasSelectedTerms && is_array($selectedAttributes[$attribute['id']]))
                        <div class="mb-2 w-full p-2 bg-blue-50 text-blue-700 text-sm rounded">
                            المحدد:
                            @foreach($selectedAttributes[$attribute['id']] as $termId => $isSelected)
                                @if($isSelected)
                                    @php
                                        $termName = '';
                                        foreach ($attributeTerms[$attribute['id']] ?? [] as $term) {
                                            if ($term['id'] == $termId) {
                                                $termName = $term['name'];
                                                break;
                                            }
                                        }
                                    @endphp
                                    <span class="inline-block px-2 py-1 m-1 bg-blue-100 text-blue-800 rounded-full">{{ $termName }}</span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full p-6 bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                    <path d="M8 14v20c0 4.418 7.163 8 16 8 1.381 0 2.721-.087 4-.252M8 14c0 4.418 7.163 8 16 8s16-3.582 16-8M8 14c0-4.418 7.163-8 16-8s16 3.582 16 8m0 0v14m-16-4c0 4.418 7.163 8 16 8 1.381 0 2.721-.087 4-.252" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">لم يتم تحميل الخصائص</h3>
                <p class="mt-1 text-sm text-gray-500">تحقق من الاتصال بقاعدة البيانات أو إعدادات WooCommerce</p>
            </div>
        @endforelse
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('error'))
        <div class="mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    @if (session()->has('success'))
        <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif

    <div class="mt-6 flex items-center space-x-4 rtl:space-x-reverse">
        <button
            type="button"
            wire:click="generateVariations"
            wire:loading.attr="disabled"
            wire:target="generateVariations"
            class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
        >
            <span wire:loading.remove wire:target="generateVariations">
                {{ __('توليد المتغيرات') }}
            </span>
            <span wire:loading wire:target="generateVariations">
                {{ __('جاري التوليد...') }}
                <svg class="animate-spin h-5 w-5 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </span>
        </button>
    </div>

    @if (count($variations))
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4">المتغيرات الناتجة: ({{ count($variations) }})</h3>

            <div class="overflow-x-auto bg-white shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        @foreach($attributeMap as $attr)
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ $attr['name'] }}
                            </th>
                        @endforeach
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <span>{{ __('السعر') }}</span>
                            <div><input type="number" wire:model.live="allRegularPrice" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="السعر للكل"></div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <span>{{ __('سعر الخصم') }}</span>
                            <div><input type="number" wire:model.live="allSalePrice" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" placeholder="سعر الخصم للكل"></div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            <div class="flex flex-col">
                                <span>{{ __('الكمية') }}</span>
                                <input
                                    type="number"
                                    wire:model.live="allStockQuantity"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-xs"
                                    placeholder="الكمية للكل"
                                    step="1"
                                    min="0"
                                >
                            </div>
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('الوصف') }}</th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($variations as $index => $variation)
                        <tr class="@if(empty($variation['regular_price'])) bg-red-50 @else bg-white @endif">
                            @foreach($variation['options'] as $option)
                                <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $option }}</td>
                            @endforeach
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input
                                    type="number"
                                    wire:model.live="variations.{{ $index }}.regular_price"
                                    step="0.01"
                                    min="0"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm
                                            @if(empty($variation['regular_price'])) border-red-300 bg-red-50 @else bg-white border-green-300 @endif"
                                    placeholder="السعر *"
                                    required
                                >
                                @if(empty($variation['regular_price']))
                                    <p class="text-xs text-red-500 mt-1">مطلوب</p>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input
                                    type="number"
                                    wire:model.live="variations.{{ $index }}.sale_price"
                                    step="0.01"
                                    min="0"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 bg-yellow-50 text-sm"
                                    placeholder="سعر الخصم"
                                >
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @php
                                    $stockValue = '';
                                    if (isset($variation['stock_quantity'])) {
                                        $stockValue = $variation['stock_quantity'];
                                        // التأكد من أن القيمة ليست null
                                        if (is_null($stockValue)) {
                                            $stockValue = '';
                                        }
                                    }
                                @endphp
                                <input
                                    type="number"
                                    wire:model.live="variations.{{ $index }}.stock_quantity"
                                    step="1"
                                    min="0"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 bg-blue-50 text-sm"
                                    placeholder="الكمية"
                                >
                                <div class="text-xs text-gray-500 mt-1">
                                    <div>من المصفوفة: "{{ $variation['stock_quantity'] ?? 'غير موجود' }}"</div>
                                    <div>النوع: {{ gettype($variation['stock_quantity'] ?? null) }}</div>
                                    <div>Livewire Value: {{ $variations[$index]['stock_quantity'] ?? 'غير موجود' }}</div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <input
                                    type="text"
                                    wire:model.live="variations.{{ $index }}.description"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 text-sm"
                                    placeholder="الوصف"
                                    value="{{ $variation['description'] ?? '' }}"
                                >
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

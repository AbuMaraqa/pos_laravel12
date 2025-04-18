{{--<div>--}}
{{--    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">--}}
{{--        @foreach ($productAttributes as $attr)--}}
{{--            <div class="p-4 border rounded shadow-md bg-white">--}}
{{--                <h3 class="font-semibold text-xl mb-2">{{ $attr['name'] }}</h3>--}}

{{--                <div class="flex flex-col gap-1">--}}
{{--                    @foreach ($attributeTerms[$attr['id']] ?? [] as $term)--}}
{{--                        <label class="inline-flex items-center space-x-2">--}}
{{--                            <input--}}
{{--                                type="checkbox"--}}
{{--                                wire:model="selectedAttributes.{{ $attr['id'] }}"--}}
{{--                                value="{{ $term['id'] }}"--}}
{{--                                class="form-checkbox"--}}
{{--                            >--}}
{{--                            <span>{{ $term['name'] }}</span>--}}
{{--                        </label>--}}
{{--                    @endforeach--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        @endforeach--}}
{{--    </div>--}}

{{--    <div class="mt-6 flex items-center space-x-4 rtl:space-x-reverse">--}}
{{--        <flux:button--}}
{{--            wire:click="generateVariations"--}}
{{--            wire:loading.attr="disabled"--}}
{{--            wire:target="generateVariations"--}}
{{--            loading--}}
{{--            type="button"--}}
{{--            class="btn btn-success"--}}
{{--        >--}}
{{--            توليد المتغيرات--}}
{{--        </flux:button>--}}
{{--    </div>--}}

{{--    @if (count($variations))--}}
{{--        <div class="mt-8">--}}
{{--            <h3 class="text-lg font-semibold mb-4">المتغيرات الناتجة:</h3>--}}
{{--            <div class="overflow-x-auto bg-white shadow-md rounded-lg">--}}
{{--                <table class="min-w-full divide-y divide-gray-200 text-sm text-right">--}}
{{--                    <thead class="bg-gray-100">--}}
{{--                    <tr>--}}
{{--                        @foreach ($attributeMap as $label)--}}
{{--                            <th class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">--}}
{{--                                {{ $label }}--}}
{{--                            </th>--}}
{{--                        @endforeach--}}
{{--                    </tr>--}}
{{--                    </thead>--}}
{{--                    <tbody class="divide-y divide-gray-100">--}}
{{--                    @foreach ($variations as $variation)--}}
{{--                        <tr>--}}
{{--                            @foreach ($variation['options'] as $option)--}}
{{--                                <td class="px-4 py-2 text-gray-800 whitespace-nowrap">--}}
{{--                                    {{ $option }}--}}
{{--                                </td>--}}
{{--                            @endforeach--}}
{{--                        </tr>--}}
{{--                    @endforeach--}}
{{--                    </tbody>--}}
{{--                </table>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    @endif--}}
{{--</div>--}}
<div>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($loadedAttributes as $attr)
            @if(isset($attr['id']) && isset($attr['name']))
                <div class="p-4 border rounded shadow-md bg-white">
                    <h3 class="font-semibold text-xl mb-2">{{ $attr['name'] }}</h3>

                    <div class="flex flex-col gap-1">
                        @foreach ($attributeTerms[$attr['id']] ?? [] as $term)
                            @if(isset($term['id']) && isset($term['name']))
                                <label class="inline-flex items-center space-x-2">
                                    <input
                                        type="checkbox"
                                        wire:model.live="selectedAttributes.{{ $attr['id'] }}.{{ $term['id'] }}"
                                        class="form-checkbox"
                                    >
                                    <span>{{ $term['name'] }}</span>
                                </label>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    @if ($currentPage * $perPage < $totalAttributes)
        <div class="mt-4 text-center">
            <button
                wire:click="loadMore"
                wire:loading.attr="disabled"
                class="btn btn-primary"
            >
                <span wire:loading.remove>تحميل المزيد</span>
                <span wire:loading>جاري التحميل...</span>
            </button>
        </div>
    @endif

    <div class="mt-6 flex items-center space-x-4 rtl:space-x-reverse">
        <flux:button
            wire:click="generateVariations"
            wire:loading.attr="disabled"
            wire:target="generateVariations"
            loading
            type="button"
            class="btn btn-success"
        >
            توليد المتغيرات
        </flux:button>
    </div>

    @if (count($variations))
        <div class="mt-8">
            <h3 class="text-lg font-semibold mb-4">المتغيرات الناتجة:</h3>
            <div class="overflow-x-auto bg-white shadow-md rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm text-right">
                    <thead class="bg-gray-100">
                    <tr>
                        @foreach ($attributeMap as $label)
                            <th class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">
                                {{ $label['name'] }}
                            </th>
                        @endforeach
                        {{-- <th class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">SKU</th> --}}
                        <th class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap"><div class="flex items-center gap-2">
                            <span><flux:input style="width: 100px;" size="sm" placeholder="{{ __('All Prices')}}" wire:model.live="allRegularPrice" /></span><span>السعر</span>
                        </div></th>
                        <th class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span><flux:input style="width: 100px;" size="sm" placeholder="{{ __('All Sale Prices')}}" wire:model.live="allSalePrice" /></span><span>سعر الخصم</span>
                            </div>
                        </th>
                        <th class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">
                            <div class="flex items-center gap-2">
                                <span><flux:input style="width: 100px;" size="sm" placeholder="{{ __('All Quantities')}}" wire:model.live="allStockQuantity" /></span><span>الكمية</span>
                            </div>
                        </th>
                        <th class="px-4 py-2 font-medium text-gray-700 whitespace-nowrap">الوصف</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach ($variations as $index => $variation)
                        <tr>
                            @foreach ($variation['options'] as $option)
                                <td class="px-4 py-2 text-gray-800 whitespace-nowrap">
                                    {{ is_array($option) ? implode(', ', $option) : $option }}
                                </td>
                            @endforeach
                            {{-- <td class="px-2 py-2">
                                <flux:input size="sm" wire:model="variations.{{ $index }}.sku" style="background-color: #ffcc00;" type="number"/>
                            </td> --}}
                            <td class="px-2 py-2">
                                <flux:input size="sm" style="background-color: #ffcc00;" type="number" wire:model="variations.{{ $index }}.regular_price"/>
                            </td>
                            <td class="px-2 py-2">
                                <flux:input size="sm" style="background-color: #ffcc00;" type="number" wire:model="variations.{{ $index }}.sale_price"/>
                            </td>
                            <td class="px-2 py-2">
                                <flux:input size="sm" style="background-color: #ffcc00;" type="number" wire:model="variations.{{ $index }}.stock_quantity"/>
                            </td>
                            <td class="px-2 py-2">
                                <flux:input size="sm" style="background-color: #ffcc00;" type="number" wire:model="variations.{{ $index }}.description"/>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Debug information --}}
    {{-- <div class="mt-4 p-4 bg-gray-100 rounded">
        <h4 class="font-semibold mb-2">معلومات التصحيح:</h4>
        <pre class="text-xs">{{ json_encode($selectedAttributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </div> --}}

{{--    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الخصائص</label>
        <div class="space-y-2">
            @foreach($loadedAttributes as $attribute)
                <div class="flex items-center">
                    <input type="checkbox"
                           wire:model="selectedAttributes.{{ $attribute['id'] }}"
                           value="1"
                           id="attribute_{{ $attribute['id'] }}"
                           class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-700">
                    <label for="attribute_{{ $attribute['id'] }}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                        {{ $attribute['name'] }}
                    </label>
                </div>
                @if(isset($attributeTerms[$attribute['id']]))
                    <div class="ml-4">
                        @foreach($attributeTerms[$attribute['id']] as $term)
                            <div class="flex items-center">
                                <input type="checkbox"
                                       wire:model="selectedAttributes.{{ $attribute['id'] }}.{{ $term['id'] }}"
                                       value="1"
                                       id="term_{{ $term['id'] }}"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-700">
                                <label for="term_{{ $term['id'] }}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                    {{ $term['name'] }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    </div> --}}

{{--    <pre class="text-xs bg-gray-100 p-2 mt-4 rounded">--}}
{{--    {{ json_encode($selectedAttributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}--}}
{{--</pre>--}}

</div>

{{-- Remove any error messages --}}
@if(!empty($errors))
    {{-- This section is removed to disable validation errors --}}
@endif

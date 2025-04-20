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
        @foreach ($loadedAttributes as $attribute)
            <div class="p-4 border rounded shadow-md bg-white">
                <h3 class="font-semibold text-xl mb-2">{{ $attribute['name'] }}</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach ($attributeTerms[$attribute['id']] ?? [] as $term)
                        <label class="inline-flex items-center">
                            <input
                                type="checkbox"
                                wire:model.live="selectedAttributes.{{ $attribute['id'] }}.{{ $term['id'] }}"
                                class="form-checkbox"
                            >
                            <span class="mr-2">{{ $term['name'] }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
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
            <h3 class="text-lg font-semibold mb-4">المتغيرات الناتجة:</h3>

            {{-- Bulk Update Fields --}}
            {{-- <div class="mb-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">السعر للكل</label>
                    <input type="number" wire:model.live="allRegularPrice" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">سعر الخصم للكل</label>
                    <input type="number" wire:model.live="allSalePrice" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">الكمية للكل</label>
                    <input type="number" wire:model.live="allStockQuantity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                </div>
            </div> --}}

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
                                <span class="sr-only">السعر</span>
                                <span><input type="number" wire:model.live="allRegularPrice" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></span>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <span class="sr-only">السعر الخصم</span>
                                <span><input type="number" wire:model.live="allSalePrice" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></span>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <span class="sr-only">الكمية</span>
                                <span><input type="number" wire:model.live="allStockQuantity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></span>
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الوصف</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($variations as $index => $variation)
                            <tr>
                                @foreach($variation['options'] as $option)
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $option }}</td>
                                @endforeach
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:input wire:model="variations.{{ $index }}.regular_price" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" style="background-color: #FACA15;" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:input wire:model="variations.{{ $index }}.sale_price" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" style="background-color: #FACA15;" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:input wire:model="variations.{{ $index }}.stock_quantity" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" style="background-color: #FACA15;" />
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <flux:input wire:model="variations.{{ $index }}.description" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500" style="background-color: #FACA15;" />
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



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
        @foreach ($productAttributes as $attr)
            @php
                $terms = app(App\Services\WooCommerceService::class)->getTermsForAttribute($attr['id']);
            @endphp

            <div class="p-4 border rounded shadow-md bg-white">
                <h3 class="font-semibold text-xl mb-2">{{ $attr['name'] }}</h3>

                @foreach ($terms as $term)
                    @php
                        $inputId = 'attr_' . $attr['id'] . '_term_' . $term['id'];
                    @endphp

                    <div class="flex items-center mb-1">
                        <input
                            type="checkbox"
                            id="{{ $inputId }}"
                            wire:model="selectedAttributes.{{ $attr['id'] }}.{{ $term['id'] }}"
                            class="form-checkbox"
                        >
                        <label for="{{ $inputId }}" class="ml-2">{{ $term['name'] }}</label>
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>

    <flux:button
        wire:click="generateVariations"
        wire:loading.attr="disabled"
        wire:target="generateVariations"
        type="button"
        class="btn btn-success mt-6"
    >
        توليد المتغيرات
    </flux:button>


    @if ($variations)
        <div class="mt-10">
            <h3 class="text-lg font-bold text-gray-800 mb-4">المتغيرات الناتجة:</h3>

            <div class="overflow-x-auto rounded-lg shadow border border-gray-200 bg-white">
                <table class="min-w-full text-sm text-right text-gray-700">
                    <thead class="bg-gray-100 text-xs text-gray-600 uppercase tracking-wide">
                    <tr>
                        @foreach ($attributeMap as $label)
                            <th class="px-4 py-3 border-b border-gray-300">{{ $label['name'] }}</th>
                        @endforeach
                        <th class="px-4 py-3 border-b">SKU</th>
                        <th class="px-4 py-3 border-b">السعر</th>
                        <th class="px-4 py-3 border-b">سعر الخصم</th>
                        <th class="px-4 py-3 border-b">الكمية</th>
                        <th class="px-4 py-3 border-b">مفعل</th>
                        <th class="px-4 py-3 border-b">الطول</th>
                        <th class="px-4 py-3 border-b">العرض</th>
                        <th class="px-4 py-3 border-b">الارتفاع</th>
                        <th class="px-4 py-3 border-b">الوصف</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach ($variations as $index => $variation)
                        <tr class="hover:bg-gray-50">
                            @foreach ($variation['options'] as $option)
                                <td class="px-4 py-2 text-gray-800 whitespace-nowrap">
                                    {{ is_array($option) ? implode(', ', $option) : $option }}
                                </td>
                            @endforeach


                            <td class="px-2 py-2">
                                <input type="text" wire:model.defer="variations.{{ $index }}.sku" class="input input-sm input-bordered w-full" />
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" wire:model.defer="variations.{{ $index }}.regular_price" class="input input-sm input-bordered w-full" />
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" wire:model.defer="variations.{{ $index }}.sale_price" class="input input-sm input-bordered w-full" />
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" wire:model.defer="variations.{{ $index }}.stock_quantity" class="input input-sm input-bordered w-full" />
                            </td>
                            <td class="px-2 py-2 text-center">
                                <input type="checkbox" wire:model="variations.{{ $index }}.active" class="toggle toggle-success" />
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" wire:model.defer="variations.{{ $index }}.length" class="input input-sm input-bordered w-full" />
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" wire:model.defer="variations.{{ $index }}.width" class="input input-sm input-bordered w-full" />
                            </td>
                            <td class="px-2 py-2">
                                <input type="number" wire:model.defer="variations.{{ $index }}.height" class="input input-sm input-bordered w-full" />
                            </td>
                            <td class="px-2 py-2">
                                <textarea wire:model.defer="variations.{{ $index }}.description" class="textarea textarea-sm textarea-bordered w-full"></textarea>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif


{{--    <pre class="text-xs bg-gray-100 p-2 mt-4 rounded">--}}
{{--    {{ json_encode($selectedAttributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}--}}
{{--</pre>--}}

</div>

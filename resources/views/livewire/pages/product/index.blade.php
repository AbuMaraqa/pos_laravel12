<div>

    <flux:modal name="barcode-product-modal" class="md:w-[600px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">طباعة الباركود</flux:heading>
            </div>
            {{-- المنتج الرئيسي --}}
            <div class="flex items-center justify-between border-b pb-2">
                <div class="text-sm font-bold text-gray-800">
                    المنتج: {{ $product['name'] ?? '' }}
                </div>
                <div>{!! DNS1D::getBarcodeHTML('AS', 'C39') !!}</div>
                <div class="w-24">
                    <input type="number" min="1" wire:model.defer="quantities.main"
                        class="w-full border border-gray-300 rounded px-2 py-1 text-sm" />
                </div>
            </div>

            {{-- المتغيرات إن وجدت --}}
            @if (!empty($variations))
                <div class="mt-4 space-y-4">
                    @foreach ($variations as $variation)
                        <div class="flex items-center justify-between border-b pb-2">
                            <div class="text-sm text-gray-700">{{ $variation['name'] ?? '' }}</div>
                            <div>{!! DNS1D::getBarcodeHTML($variation['id'], 'C39') !!}</div>
                            <div class="w-24">
                                <flux:field>
                                    <flux:input type="number" min="1"
                                        wire:model.defer="quantities.{{ $variation['id'] }}" />
                                </flux:field>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- زر الطباعة --}}
            <div class="flex pt-4">
                <flux:spacer />
                <flux:button wire:click="printBarcodes" variant="primary">طباعة</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="stock-qty-product-modal" class="md:w-[600px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">كمية المنتج</flux:heading>
            </div>
            {{-- المنتج الرئيسي --}}
{{--            <div class="flex items-center justify-between border-b pb-2">--}}
{{--                <div class="text-sm font-bold text-gray-800">--}}
{{--                    المنتج: {{ $product['name'] ?? '' }}--}}
{{--                </div>--}}
{{--                <div>{!! DNS1D::getBarcodeHTML('AS', 'C39') !!}</div>--}}
{{--                <div class="w-24">--}}
{{--                    <input type="number" min="1" wire:model.defer="quantities.main"--}}
{{--                        class="w-full border border-gray-300 rounded px-2 py-1 text-sm" />--}}
{{--                </div>--}}
{{--            </div>--}}

            {{-- المتغيرات إن وجدت --}}
            @if (!empty($variations))
                <div class="mt-4 space-y-4">
                    <div class="flex items-center justify-between border-b pb-2">
                        <div class="text-sm text-gray-700 font-medium">إضافة كمية للكل</div>
                        <div class="w-24">
                            <flux:field>
                                {{--
                                  1. ربطنا الحقل بـ wire:model.defer="qtyToAdd"
                                  2. عند التغيير، نستدعي changeQty مع القيمة الجديدة
                                --}}
                                <flux:input
                                    type="number"
                                    wire:model.defer="qtyToAdd"
                                    wire:change="changeQty($event.target.value)"
                                    placeholder="إضافة كمية"
                                    min="1"
                                />
                            </flux:field>
                        </div>
                    </div>

                    {{-- أضفنا هذا الرأس للتوضيح --}}
                    <div class="flex items-center justify-between border-b pb-2 pt-4">
                        <div class="text-sm text-gray-800 font-bold">الاسم</div>
                        <div class="w-24 text-sm text-gray-800 font-bold">الكمية الإجمالية</div>
                    </div>

                    @foreach ($variations as $variation)
                        <div class="flex items-center justify-between border-b pb-2">
                            <div class="text-sm text-gray-700">{{ $variation['name'] ?? '' }}</div>
                            <div class="w-24">
                                <flux:field>
                                    {{--
                                      تم حذف value="..."
                                      wire:model.defer سيتكفل بعرض القيمة المبدئية وتحديثها
                                    --}}
                                    <flux:input
                                        type="number"
                                        placeholder="الكمية"
                                        min="1"
                                        wire:model.defer="quantities.{{ $variation['id'] }}"
                                    />
                                </flux:field>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="flex pt-4">
                <flux:spacer />
                {{-- ربطنا الزر بالدالة الجديدة للحفظ وأضفنا حالة التحميل --}}
                <flux:button
                    variant="primary"
                    wire:click="saveStockQuantities"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="saveStockQuantities">
                        {{ __('Save') }}
                    </span>
                    <span wire:loading wire:target="saveStockQuantities">
                        {{ __('Saving...') }}
                    </span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal x-data="{}" name="list-variations" class="" style="min-width: 90vw; max-width: 90vw;">
        <div class="space-y-6">
            {{-- ... (الجزء العلوي يبقى كما هو) ... --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-200 p-4">
                <div>
                    <div class="grid grid-cols-4">
                        <div class="col-span-1">
                            <img style="width: 100px" src="{{ $productData['images'][0]['src'] ?? '' }}" alt="">
                        </div>
                        <div class="col-span-3">
                            <h1 class="text-2xl font-bold">{{ $productData['name'] ?? '' }}</h1>
                            <flux:separator class="my-2" />
                            <p>
                                يمكن من خلال هذه الصفحة تحديد السعر الرئيسي للمنتج و الأسعار بناءا على المناطق او الفئات
                                من خلال الجدول ادناه
                            </p>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="grid grid-cols-2 gap-4 max-w-full p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
                        {{-- ✨ تم حذف wire:change --}}
                        <div>
                            <flux:input type="number" wire:model.defer="main_price" label="{{ __('Price') }}" />
                        </div>
                        <div>
                            <flux:input type="number" wire:model.defer="main_sale_price" label="{{ __('Sale Price') }}" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="inline-flex items-center gap-2">
                <flux:field variant="inline">
                    <flux:label>{{ __('Enable Multiple Price') }}</flux:label>
                    <flux:switch wire:model="showVariationTable" wire:change="updateMrbpMetaboxUserRoleEnable" checked />
                </flux:field>
            </div>
            <table x-bind:class="{ 'hidden': !$wire.showVariationTable }" class="w-full text-sm text-left rtl:text-right text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                <th scope="col" class="px-6 py-3">
                    {{ __('Variation Name') }}
                </th>
                <th>{{ __('Price') }}</th>
                @foreach ($this->getRoles as $role)
                    <th scope="col" class="px-6 py-3">
                        <div class="mb-1">{{ $role['name'] }}</div>
                        {{-- ✨ تم تعديل هذا الجزء بالكامل --}}
                        <div class="flex items-center gap-1">
                            <input type="number"
                                   placeholder="Set all for {{ $role['name'] }}"
                                   class="w-full text-xs p-1 border border-gray-300 rounded"
                                   wire:model.defer="columnPrices.{{ $role['role'] }}"
                                   min="0" step="0.01">
                            <button type="button"
                                    wire:click="setAllPricesForRole('{{ $role['role'] }}')"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                {{ __('Apply') }}
                            </button>
                        </div>
                    </th>
                @endforeach
                </thead>
                <tbody class="bg-white">
                {{-- صف المنتج الأساسي --}}
                <tr class="bg-gray-100">
                    <td class="px-6 py-3 font-bold">{{ $productData['name'] ?? 'المنتج الأساسي' }}</td>
                    <td>
                        {{-- ✨ تم حذف wire:change --}}
                        <flux:input type="number" wire:model.defer="main_price" />
                    </td>
                    @foreach ($this->getRoles as $roleIndex => $role)
                        <td class="px-6 py-3">
                            {{-- ✨ تم حذف wire:change --}}
                            <flux:input type="text"
                                        wire:model.defer="parentRoleValues.{{ $role['role'] }}" class="bg-gray-50" />
                        </td>
                    @endforeach
                </tr>

                {{-- صفوف المتغيرات --}}
                @foreach ($productVariations as $variationIndex => $variation)
                    <tr>
                        <td class="px-6 py-3">{{ $variation['name'] }}</td>
                        <td>
                            {{-- ✨ تم حذف wire:change --}}
                            <flux:input type="number" wire:model.defer="price.{{ $variationIndex }}" />
                        </td>
                        @foreach ($this->getRoles as $roleIndex => $role)
                            <td class="px-6 py-3">
                                {{-- ✨ تم حذف wire:change --}}
                                <flux:input type="text"
                                            wire:model.defer="variationValues.{{ $variationIndex }}.{{ $role['role'] }}" />
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{-- ✨ زر الحفظ وإغلاق النافذة --}}
            <div class="flex justify-end items-center pt-4 border-t mt-4 gap-2">
                <flux:button x-on:click="$dispatch('close-modal')">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="primary" wire:click="saveAllChanges" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="saveAllChanges">
                    {{ __('Save All Changes') }}
                </span>
                    <span wire:loading wire:target="saveAllChanges">
                    {{ __('Saving...') }}
                </span>
                </flux:button>
            </div>

            {{-- ✨ تم حذف شيفرة JavaScript بالكامل من هنا --}}

        </div>
    </flux:modal>

    <flux:button href="{{ route('product.add') }}" wire:navigate variant="primary" icon="plus">
        {{ __('Add product') }}</flux:button>

    <flux:button wire:click="syncProduct()" variant="primary" icon="arrow-path">
        {{ __('Sync product') }}
    </flux:button>

    <flux:input class="mt-3" wire:model.live.debounce.500ms="search" placeholder="{{ __('Search') }}" />

    <flux:button wire:click="resetCategory" class="mt-2">
        {{ __('All') }}
    </flux:button>

    @foreach ($categories as $category)
        <flux:button wire:click="setCategory({{ $category['id'] }})" class="mt-2">
            {{ $category['name'] }}
        </flux:button>
    @endforeach

    <div class="relative overflow-x-auto shadow-lg sm:rounded-lg my-6 border border-gray-200">
        <table
            class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 divide-y divide-gray-200">
            <thead
                class="text-xs font-medium uppercase bg-gradient-to-r from-indigo-50 to-blue-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-4 rounded-tl-lg">
                        {{ __('Image') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold">
                        {{ __('Product name') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold">
                        {{ __('Categories') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Regular price') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Sale price') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Featured') }}
                        {{-- <flux:icon.loading variant="mini" wire:loading wire:target="updateProductFeatured" /> --}}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Status') }}
                    </th>
                    <th>
                        {{ __('Type') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold">
                        {{ __('Area price') }}
                        {{-- <flux:icon.loading variant="mini" wire:loading wire:target="openListVariationsModal" /> --}}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Stock Quantity') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Sort') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center rounded-tr-lg">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($products as $product)
                    <tr class="bg-white hover:bg-gray-50 dark:bg-gray-800 transition-colors duration-200 ease-in-out">
                        <td class="p-4 text-center">
                            <img class="w-16 h-16 object-cover rounded-md border border-gray-200 shadow-sm mx-auto"
                                src="{{ $product['images'][0]['src'] ?? asset('images/no-image.png') }}"
                                alt="{{ $product['name'] }}">
                        </td>
                        <td scope="row"
                            class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('products.edit', $product['id']) }}" wire:navigate>
                                    <flux:icon.pencil-square variant="micro" color="blue"/>
                                </a>
                                {{ $product['name'] }}
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @foreach ($product['categories'] as $category)
                                    <flux:badge color="indigo">
                                        {{ $category['name'] }}
                                    </flux:badge>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center font-medium">
                            {{ $product['regular_price'] }}
                        </td>
                        <td class="px-6 py-4 text-center font-medium text-emerald-600">
                            {{ $product['sale_price'] }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if ($product['featured'])
                            <div wire:loading.remove wire:target="updateProductFeatured({{ $product['id'] }}, false)">
                                <flux:icon.star wire:click="updateProductFeatured({{ $product['id'] }}, false)"
                                    variant="solid"
                                    class="cursor-pointer mx-auto transform hover:scale-110 transition-transform"
                                    color="orange" />
                            </div>

                            <!-- أيقونة اللودينغ (تظهر فقط أثناء التحميل) -->
                            <div wire:loading wire:target="updateProductFeatured({{ $product['id'] }}, false)">
                                <flux:icon.loading variant="mini"/>
                            </div>

                            @else
                                <div wire:loading.remove wire:target="updateProductFeatured({{ $product['id'] }}, true)">
                                    <flux:icon.star wire:click="updateProductFeatured({{ $product['id'] }}, true)"
                                        class="cursor-pointer mx-auto transform hover:scale-110 transition-transform" />
                                </div>

                                <!-- أيقونة اللودينغ (تظهر فقط أثناء التحميل) -->
                                <div wire:loading wire:target="updateProductFeatured({{ $product['id'] }}, true)">
                                    <flux:icon.loading variant="mini"/>
                                </div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            {{-- {{ $product['status'] }} --}}
                            <span
                                class="px-2 py-1 rounded-full text-xs font-medium">
                                @if ($product['status'] == 'publish')
                                    <flux:switch  wire:change="updateProductStatus({{ $product['id'] }}, 'draft')" checked/>
                                @else
                                    <flux:switch  wire:change="updateProductStatus({{ $product['id'] }}, 'publish')"/>
                                @endif
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if ($product['type'] == 'variable')
                                <flux:badge color="orange">
                                    {{ __('Variable') }}
                                </flux:badge>
                            @else
                                <flux:badge color="blue">
                                    {{ __('Simple') }}
                                </flux:badge>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <div wire:loading.remove wire:target="openListVariationsModal({{ $product['id'] }})">
                                    <flux:icon.cog-8-tooth
                                        variant="mini"
                                        class="cursor-pointer hover:text-blue-600"
                                        wire:click="openListVariationsModal({{ $product['id'] }})"
                                    />
                                </div>

                                <!-- أيقونة اللودينغ (تظهر فقط أثناء التحميل) -->
                                <div wire:loading wire:target="openListVariationsModal({{ $product['id'] }})">
                                    <flux:icon.loading variant="mini"/>
                                </div>
                                @if (!empty($product['meta_data']) && is_array($product['meta_data']))
                                    @foreach ($product['meta_data'] as $meta)
                                    @if ($meta['key'] == 'mrbp_role')
                                        @foreach ($meta['value'] as $area)
                                            <flux:badge color="lime">
                                                @php
                                                    $roleKey = array_key_first($area);

                                                    // Usamos siempre el formato directo
                                                    $roleName = $area[$roleKey] ?? $roleKey;
                                                    // Asegurarnos de que roleName sea string
                                                    $roleName = is_array($roleName)
                                                        ? json_encode($roleName)
                                                        : $roleName;
                                                    $regularPrice = $area['mrbp_regular_price'] ?? '';
                                                    $regularPrice = is_array($regularPrice)
                                                        ? json_encode($regularPrice)
                                                        : $regularPrice;
                                                    $salePrice = $area['mrbp_sale_price'] ?? '';
                                                    $salePrice = is_array($salePrice)
                                                        ? json_encode($salePrice)
                                                        : $salePrice;
                                                @endphp
                                                <span>{{ $roleName }}: {{ $regularPrice }}</span>
                                            </flux:badge>
                                        @endforeach
                                    @endif
                                @endforeach
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center font-medium">
                            <flux:button wire:click="openStockQtyModal({{ $product['id'] }})">
                                {{ $product['total_quantity'] }}
                            </flux:button>

{{--                            <span--}}
{{--                                class="px-3 py-1 rounded-full {{ $product['stock_quantity'] > 0 ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700' }}">--}}
{{--                                {{ $product['stock_quantity'] }}--}}
{{--                            </span>--}}
                        </td>
                        <td class="px-6 py-4 text-center font-medium">
                            {{-- نستخدم x-data لإنشاء متغير محلي خاص بهذا الحقل فقط --}}
                            <div x-data="{ localOrder: {{ $product['menu_order'] ?? 0 }} }">
{{--                                <flux:input--}}
{{--                                    type="number"--}}
{{--                                    x-model="localOrder"--}}
{{--                                    x-on:blur="$wire.updateMenuOrder({{ $product['id'] }}, localOrder)"--}}
{{--                                    class="text-center"--}}
{{--                                    style="width:70px;background-color:green;color:white"--}}
{{--                                />--}}
                                <select
                                    style="background-color: green;color: white"
                                    {{-- عند تغيير القيمة، يتم إرسال الطلب مباشرة للسيرفر --}}
                                    wire:change="updateMenuOrder({{ $product['id'] }}, $event.target.value)"
                                    class="w-20 text-center border border-gray-300 rounded-lg px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500 cursor-pointer"
                                >
                                    {{-- حلقة التكرار من 0 إلى 50 --}}
                                    @for ($i = 0; $i <= 50; $i++)
                                        <option
                                            value="{{ $i }}"
                                            {{-- تحديد القيمة الحالية تلقائياً --}}
                                            @selected(($product['menu_order'] ?? 0) == $i)
                                        >
                                            {{ $i }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <flux:dropdown>
                                <flux:button icon:trailing="chevron-down" class="bg-indigo-500 hover:bg-indigo-600">
                                    {{ __('Options') }}
                                </flux:button>

                                <flux:menu>
                                    <flux:menu.item wire:click="openPrintBarcodeModal({{ $product['id'] }})"
                                        icon="eye">{{ __('Barcode Product') }}</flux:menu.item>
                                    <flux:menu.item target="_black" href="{{ $product['permalink'] }}"
                                        icon="eye">
                                        {{ __('View in website') }}</flux:menu.item>
                                    <flux:menu.item wire:navigate href="{{ route('products.edit', $product['id']) }}"
                                        icon="pencil-square">{{ __('Edit product') }}</flux:menu.item>
                                    <flux:menu.item wire:navigate href="{{ route('product.variation.image', $product['id']) }}"
                                        icon="eye">{{ __('Variation Image') }}</flux:menu.item>
                                    <flux:menu.item variant="danger"
                                        wire:confirm="Are you sure you want to delete this product?"
                                        wire:click="deleteProduct({{ $product['id'] }})" icon="trash">
                                        {{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $products->links() }}
    </div>

</div>

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
                <div>{!! DNS1D::getBarcodeHTML('AS', 'C39'); !!}</div>
                <div class="w-24">
                    <input type="number" min="1"
                           wire:model.defer="quantities.main"
                           class="w-full border border-gray-300 rounded px-2 py-1 text-sm" />
                </div>
            </div>

            {{-- المتغيرات إن وجدت --}}
            @if(!empty($variations))
                <div class="mt-4 space-y-4">
                    @foreach($variations as $variation)
                        <div class="flex items-center justify-between border-b pb-2">
                            <div class="text-sm text-gray-700">{{ $variation['name'] ?? '' }}</div>
                            <div>{!! DNS1D::getBarcodeHTML($variation['id'], 'C39') !!}</div>
                            <div class="w-24">
                                <flux:field>
                                    <flux:input type="number" min="1" wire:model.defer="quantities.{{ $variation['id'] }}"/>
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



    <flux:button href="{{ route('product.add') }}" wire:navigate variant="primary" icon="plus">{{ __('Add product') }}</flux:button>

    <flux:input class="mt-3" wire:model.live.debounce.500ms="search" placeholder="{{ __('Search') }}"/>

    <flux:button wire:click="resetCategory" class="mt-2">
        {{ __('All') }}
    </flux:button>

    @foreach($categories as $category)
        <flux:button wire:click="setCategory({{ $category['id'] }})" class="mt-2">
            {{ $category['name'] }}
        </flux:button>
    @endforeach

    <div class="relative overflow-x-auto sm:rounded-lg">
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">

                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Product name') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Categories') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Regular price') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Sale price') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Featured') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Status') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Area price') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Actions') }}
                </th>
            </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                    <tr class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                        <td>
                            <img style="width: 70px" src="{{ $product['images'][0]['src'] ?? '' }}" alt="">
                        </td>
                        <td scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{ $product['name'] }}
                        </td>
                        <th scope="col" class="px-6 py-3">
                            @foreach($product['categories'] as $category)
                                <flux:badge color="indigo">
                                    {{ $category['name'] }}
                                </flux:badge>

                            @endforeach
                        </th>
                        <td>
                            {{ $product['regular_price'] }}
                        </td>
                        <td>
                            {{ $product['sale_price'] }}
                        </td>
                        <td>
                            {{ $product['featured'] }}
                        </td>
                        <td>
                            {{ $product['status'] }}
                        </td>
                        <td>
                            @foreach($product['meta_data'] as $meta)
                                @if($meta['key'] == 'mrbp_role')
                                    @foreach($meta['value'] as $area)
                                        <flux:badge color="lime">
                                            <span>{{ array_key_first($area) }} :&nbsp;</span>
                                            <span>{{ $area['mrbp_regular_price'] }} &nbsp;</span>
                                            <span>{{ $area['mrbp_sale_price'] }}</span>
                                        </flux:badge>
                                    @endforeach
                                @endif
                            @endforeach
                        </td>
                        <td>
                            <flux:dropdown>
                                <flux:button icon:trailing="chevron-down">{{ __('Options') }}</flux:button>

                                <flux:menu>
                                    <flux:menu.item wire:click="openPrintBarcodeModal({{ $product['id'] }})" icon="eye">{{ __('Barcode Product') }}</flux:menu.item>
                                    <flux:menu.item target="_black" href="{{ $product['permalink'] }}" icon="eye">{{ __('View in website') }}</flux:menu.item>
                                    <flux:menu.item icon="pencil-square">{{ __('Edit product') }}</flux:menu.item>
                                    <flux:menu.item variant="danger" wire:confirm="Are you sure you want to delete this product?" wire:click="deleteProduct({{ $product['id'] }})" icon="trash">{{ __('Delete') }}</flux:menu.item>
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

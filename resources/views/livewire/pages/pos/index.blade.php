<div>
    <flux:modal name="variations-modal" class="" style="min-width: 600px">
        <div class="space-y-6">

            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">
                                Product name
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Color
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Category
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Price
                            </th>
                            <th scope="col" class="px-6 py-3 text-center">
                                Qty
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($variations as $index => $item)
                            <tr
                                class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                                <th class="px-6 py-4">
                                    <img src="{{ $item['image']['src'] ?? '' }}" alt="{{ $item['name'] ?? '' }}"
                                        class="m-0 object-cover" style="max-height: 50px;min-height: 50px;">
                                </th>
                                <th scope="row"
                                    class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {{ $item['name'] ?? '' }}
                                </th>
                                <td class="px-6 py-4">
                                    {{ $item['attributes'][1]['name'] ?? '' }}
                                </td>
                                <td class="px-6 py-4 gap-2">
                                    {{ $item['price'] ?? '' }}
                                </td>
                                <td class="px-6 py-4 flex gap-2">
                                    <flux:button icon="plus" type="button" size="sm" variant="primary"
                                        wire:click="addVariation({{ $index }})">
                                    </flux:button>
                                    <flux:input type="number" size="sm" style="display:inline"
                                        wire:model.live.debounce.500ms="variations[{{ $index }}].qty"
                                        placeholder="Qty" />
                                    <flux:button icon="minus" type="button" size="sm" variant="primary"
                                        wire:click="addVariation({{ $index }})">
                                    </flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>


            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>
    <div class="grid gap-4 grid-cols-6">
        <div class="col-span-4">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <div class="flex items-center gap-2">
                    <flux:input id="searchInput" wire:model.live.debounce.500ms="search" placeholder="Search"
                        icon="magnifying-glass" />
                    <flux:button>
                        Scan
                    </flux:button>
                    <flux:button id="syncButton">
                        Sync
                    </flux:button>
                </div>

                <div class="mt-4">
                    {{-- <h1>{{ _('Categories') }}</h1> --}}
                    <div id="categoriesContainer" class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                        @if ($selectedCategory !== null)
                            <flux:button wire:click="selectCategory(null)">
                                {{ __('All') }}
                            </flux:button>
                        @endif
                        @foreach ($categories as $item)
                            @if ($item['id'] == $selectedCategory)
                                <flux:button wire:click="selectCategory({{ $item['id'] }})" variant="primary">
                                    {{ $item['name'] ?? '' }}
                                </flux:button>
                            @else
                                <flux:button wire:click="selectCategory({{ $item['id'] }})">
                                    {{ $item['name'] ?? '' }}
                                </flux:button>
                            @endif
                        @endforeach
                    </div>
                </div>
                <div class="mt-4">
                    <flux:separator />
                </div>
                <div class="mt-4 bg-gray-200 p-4 rounded-lg shadow-md">
                    {{-- <h1>{{ __('Products') }}</h1> --}}
                    <div id="productsContainer" class="grid grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                        @foreach ($products as $item)
                            <div wire:click="openVariationsModal({{ $item['id'] }}, '{{ $item['type'] ?? 'simple' }}')"
                                class="bg-white rounded-lg shadow-md relative">
                                <p style="width: 100%;position:absolute;background-color: #000;color: #fff;top: 0;left: 0;right: 0;z-index: 100;opacity: 0.5;"
                                    class="font-bold text-sm text-center">
                                    <span>{{ $item['id'] ?? '' }}</span>
                                </p>
                                <img src="{{ $item['images'][0]['src'] ?? '' }}" alt="{{ $item['name'] ?? '' }}"
                                    class="m-0 object-cover" style="max-height: 200px;min-height: 200px;">
                                <p style="position: absolute;bottom: 40px;left: 2px;z-index: 100;opacity: 0.7;min-width: 50px;"
                                    class="font-bold text-md bg-black text-white p-1 rounded-md text-center">
                                    {{ $item['price'] ?? '' }}</p>
                                <div class="">
                                    <div class="grid grid-cols-4 gap-2">
                                        <div class="col-span-4 bg-gray-200 p-2">
                                            <p class="font-bold text-sm text-center">{{ $item['name'] ?? '' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-span-2">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <h2 class="text-lg font-medium mb-2">إجمالي المبيعات</h2>
            </div>
        </div>
    </div>
</div>

<script type="module">
    import db from '/resources/js/db.js';
    alert('asd');
    async function saveToCart() {
        await db.cart.add({
            product_id: 1,
            quantity: 2
        });

        const cartItems = await db.cart.toArray();
        console.log(cartItems);
    }

    saveToCart();
</script>

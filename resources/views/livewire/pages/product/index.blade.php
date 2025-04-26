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

    <flux:modal name="list-variations" class="" style="min-width: 90vw; max-width: 90vw;">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('List of variations') }}</flux:heading>
            </div>

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
                                يمكن من خلال هذه الصفحة تحديد السعر الرئيسي للمنتج و الأسعار بناءا على المناطق او الفئات من خلال الجدول ادناه
                            </p>
                        </div>
                    </div>
                </div>

                <div>

                    <div
                        class="grid grid-cols-2 gap-4 max-w-full p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">

                        <div>
                            <flux:input type="number" wire:model.defer="main_price" label="{{ __('Price') }}" />
                        </div>
                        <div>
                            <flux:input type="number" wire:model.defer="sale_price" label="{{ __('Sale Price') }}" />
                        </div>
                    </div>

                </div>
            </div>

            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <th scope="col" class="px-6 py-3">
                        Variation Name
                    </th>
                    <th>{{ __('Price') }}</th>
                    @foreach ($this->getRoles as $role)
                        <th scope="col" class="px-6 py-3">
                            <div class="mb-1">{{ $role['name'] }}</div>
                            <div class="flex items-center gap-1">
                                <input type="number" id="column-price-{{ $role['role'] }}"
                                    placeholder="Set all for {{ $role['name'] }}"
                                    class="w-full text-xs p-1 border border-gray-300 rounded" min="0"
                                    step="0.01">
                                <button type="button" onclick="applyColumnPrice('{{ $role['role'] }}')"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                    Apply
                                </button>
                            </div>
                        </th>
                    @endforeach
                </thead>
                <tbody class="bg-white dark:bg-gray-800">
                    {{-- صف المنتج الأساسي --}}
                    <tr class="bg-gray-100">
                        <td class="px-6 py-3 font-bold">{{ $productData['name'] ?? 'المنتج الأساسي' }}</td>
                        <td>
                            <flux:input type="number" wire:model.defer="main_price" />
                        </td>
                        @foreach ($this->getRoles as $roleIndex => $role)
                        <td class="px-6 py-3">
                            <flux:input type="text"
                                    wire:change="updateProductMrbpRole('{{ $role['role'] }}', $event.target.value)"
                                    wire:model.defer="parentRoleValues.{{ $role['role'] }}" class="bg-gray-50" />
                            </td>
                        @endforeach
                    </tr>

                    {{-- صفوف المتغيرات --}}
                    @foreach ($productVariations as $variationIndex => $variation)
                        <tr>
                            <td class="px-6 py-3">{{ $variation['name'] }}</td>
                            <td>
                                <flux:input type="number" wire:model.defer="price.{{ $variationIndex }}" wire:change="updatePrice({{ $variation['id'] }}, $event.target.value)" />
                            </td>
                            @foreach ($this->getRoles as $roleIndex => $role)
                                <td class="px-6 py-3">
                                    <flux:input type="text"
                                        wire:change="updateVariationMrbpRole({{ $variation['id'] }}, '{{ $role['role'] }}', $event.target.value)"
                                        wire:model.defer="variationValues.{{ $variationIndex }}.{{ $role['role'] }}" />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Script para la funcionalidad de columna -->
            <script>
                // Función para aplicar precio a una columna específica
                function applyColumnPrice(roleId) {
                    // Obtener el valor del input de columna
                    const columnInput = document.getElementById(`column-price-${roleId}`);
                    const columnPrice = columnInput.value;

                    if (!columnPrice) {
                        alert('{{ __('Please enter a price value for this column') }}');
                        return;
                    }

                    // Buscar el índice de la columna en la tabla
                    const headings = Array.from(document.querySelectorAll('table thead th'));
                    const columnIndex = headings.findIndex(th => th.querySelector(`#column-price-${roleId}`));

                    if (columnIndex === -1) {
                        alert('{{ __('Column not found') }}');
                        return;
                    }

                    // Obtener el componente Livewire
                    const livewireComponent = window.Livewire.find(
                        document.querySelector('[wire\\:id]').getAttribute('wire:id')
                    );

                    // Obtener todos los inputs de la columna (uno por fila)
                    const rows = document.querySelectorAll('table tbody tr');

                    rows.forEach(row => {
                        // Obtener la celda en la posición columnIndex
                        const cells = row.querySelectorAll('td');
                        if (cells.length > columnIndex) {
                            const input = cells[columnIndex].querySelector('input[type="text"]');
                            if (input) {
                                // Actualizar el valor del input
                                input.value = columnPrice;

                                // Disparar evento de cambio
                                const event = new Event('change', {
                                    'bubbles': true
                                });
                                input.dispatchEvent(event);

                                // Actualizar el modelo Livewire
                                const wireModel = input.getAttribute('wire:model.defer');
                                if (wireModel) {
                                    livewireComponent.set(wireModel, columnPrice);
                                }

                                // Si hay un wire:change, extraer y ejecutar el comando
                                const wireChange = input.getAttribute('wire:change');
                                if (wireChange) {
                                    // Extraer los parámetros del wire:change
                                    const match = wireChange.match(/([^\(]+)\(([^\)]+)\)/);
                                    if (match && match.length >= 3) {
                                        const method = match[1];
                                        let params = match[2].split(',').map(p => p.trim());

                                        // Reemplazar "$event.target.value" por el valor real
                                        params = params.map(p => {
                                            if (p === "$event.target.value") return columnPrice;
                                            if (p.startsWith("'") && p.endsWith("'")) return p.slice(1, -1);
                                            return p;
                                        });

                                        // Llamar al método de Livewire
                                        livewireComponent.call(method, ...params);
                                    }
                                }
                            }
                        }
                    });

                    // Mostrar mensaje de confirmación
                    alert(`{{ __('Price applied to all rows for') }} ${roleId}`);
                }
            </script>
        </div>
    </flux:modal>


    <flux:button href="{{ route('product.add') }}" wire:navigate variant="primary" icon="plus">
        {{ __('Add product') }}</flux:button>

    <flux:input class="mt-3" wire:model.live.debounce.500ms="search" placeholder="{{ __('Search') }}" />

    <flux:button wire:click="resetCategory" class="mt-2">
        {{ __('All') }}
    </flux:button>

    @foreach ($categories as $category)
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
                        {{ __('Stock Quantity') }}
                    </th>
                    {{-- <th scope="col" class="px-6 py-3">

                </th> --}}
                    <th scope="col" class="px-6 py-3">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                    <tr
                        class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                        <td>
                            <img style="width: 70px" src="{{ $product['images'][0]['src'] ?? '' }}" alt="">
                        </td>
                        <td scope="row"
                            class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{ $product['name'] }}
                        </td>
                        <th scope="col" class="px-6 py-3">
                            @foreach ($product['categories'] as $category)
                                <flux:badge color="indigo">
                                    {{ $category['name'] }}
                                </flux:badge>
                            @endforeach
                        </th>
                        <td class="text-center">
                            {{ $product['regular_price'] }}
                        </td>
                        <td class="text-center">
                            {{ $product['sale_price'] }}
                        </td>
                        <td>
                            @if ($product['featured'])
                                <flux:icon.star wire:click="updateProductFeatured({{ $product['id'] }}, false)"
                                    variant="solid" color="orange" />
                            @else
                                <flux:icon.star wire:click="updateProductFeatured({{ $product['id'] }}, true)" />
                            @endif
                        </td>
                        <td>
                            {{ $product['status'] }}
                        </td>
                        <td>
                            <div class="flex items-center space-x-2">
                                <div wire:loading wire:target="openListVariationsModal({{ $product['id'] }})">
                                    <flux:icon.loading />
                                </div>
                                <flux:icon.cog-8-tooth wire:click="openListVariationsModal({{ $product['id'] }})" />
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
                            </div>
                        </td>
                        <td class="text-center">
                            {{ $product['stock_quantity'] }}
                        </td>
                        {{-- <td>
                            {{ $this->getMrbpRole($product['id']) ?? 'role' }}
                        </td> --}}
                        <td>
                            <flux:dropdown>
                                <flux:button icon:trailing="chevron-down">{{ __('Options') }}</flux:button>

                                <flux:menu>
                                    <flux:menu.item wire:click="openPrintBarcodeModal({{ $product['id'] }})"
                                        icon="eye">{{ __('Barcode Product') }}</flux:menu.item>
                                    <flux:menu.item target="_black" href="{{ $product['permalink'] }}"
                                        icon="eye">
                                        {{ __('View in website') }}</flux:menu.item>
                                    <flux:menu.item wire:navigate href="{{ route('products.edit', $product['id']) }}"
                                        icon="pencil-square">{{ __('Edit product') }}</flux:menu.item>
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

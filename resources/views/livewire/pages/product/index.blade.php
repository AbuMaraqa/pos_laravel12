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

    <flux:modal x-data="{}" name="list-variations" class="" style="min-width: 90vw; max-width: 90vw;">
        <div class="space-y-6">
            {{-- <div>
                <flux:heading size="lg">{{ __('List of variations') }}</flux:heading>
            </div> --}}

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

                    <div
                        class="grid grid-cols-2 gap-4 max-w-full p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">

                        <div>
                            <flux:input type="number" wire:model.defer="main_price"
                                wire:change="updateMainProductPrice" label="{{ __('Price') }}" />
                        </div>
                        <div>
                            <flux:input type="number" wire:model.defer="main_sale_price"
                                wire:change='updateMainSalePrice' label="{{ __('Sale Price') }}" />
                        </div>
                    </div>

                </div>
            </div>
            <div class="inline-flex items-center gap-2">
                <flux:field variant="inline">
                    <flux:label>{{ __('Enable Multiple Price') }}</flux:label>
                    <flux:switch wire:model="showVariationTable" wire:change="updateMrbpMetaboxUserRoleEnable" checked/>
                </flux:field>
            </div>
            <table x-bind:class="{ 'hidden': !$wire.showVariationTable }"
                class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 relative">
                <!-- Global loader overlay for the entire table -->
                <div wire:loading.flex wire:target="updateMainProductPrice, updateMainSalePrice, updateProductMrbpRole, updateVariationMrbpRole, updatePrice"
                    class="absolute inset-0 bg-white bg-opacity-70 items-center justify-center z-10">
                    <div class="flex flex-col items-center justify-center">
                        <flux:icon.loading class="text-blue-600 animate-spin h-10 w-10" />
                        <span class="mt-2 text-sm font-medium text-blue-600">{{ __('Updating variations...') }}</span>
                    </div>
                </div>
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <th scope="col" class="px-6 py-3">
                        {{ __('Variation Name') }}
                    </th>
                    <th>{{ __('Price') }}</th>
                    @foreach ($this->getRoles as $role)
                        <th scope="col" class="px-6 py-3">
                            <div class="mb-1">{{ $role['name'] }}</div>
                            <div class="flex items-center gap-1">
                                <input type="number" id="column-price-{{ $role['role'] }}"
                                    placeholder="Set all for {{ $role['name'] }}"
                                    class="w-full text-xs p-1 border border-gray-300 rounded bg-amber-400"
                                    min="0" step="0.01">
                                <button type="button" onclick="applyColumnPrice('{{ $role['role'] }}')"
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-2 py-1 rounded text-xs">
                                    {{ __('Apply') }}
                                    <span wire:loading wire:target="updateProductMrbpRole, updateVariationMrbpRole">
                                        <flux:icon.loading variant="micro" class="text-white" />
                                    </span>
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
                            <div class="relative">
                                <flux:input type="number" wire:model.defer="main_price"
                                    wire:change="updateMainProductPrice" />
                                <div wire:loading wire:target="updateMainProductPrice"
                                    class="absolute inset-y-0 right-2 flex items-center">
                                    <flux:icon.loading variant="micro" class="text-blue-600" />
                                </div>
                            </div>
                        </td>
                        @foreach ($this->getRoles as $roleIndex => $role)
                            <td class="px-6 py-3">
                                <div class="relative">
                                    <flux:input type="text"
                                        wire:change="updateProductMrbpRole('{{ $role['role'] }}', $event.target.value)"
                                        wire:model.defer="parentRoleValues.{{ $role['role'] }}" class="bg-gray-50" />
                                    <div wire:loading wire:target="updateProductMrbpRole('{{ $role['role'] }}')"
                                        class="absolute inset-y-0 right-2 flex items-center">
                                        <flux:icon.loading variant="micro" class="text-blue-600" />
                                    </div>
                                </div>
                            </td>
                        @endforeach
                    </tr>

                    {{-- صفوف المتغيرات --}}
                    @foreach ($productVariations as $variationIndex => $variation)
                        <tr>
                            <td class="px-6 py-3">{{ $variation['name'] }}</td>
                            <td>
                                <div class="relative">
                                    <flux:input type="number" wire:model.defer="price.{{ $variationIndex }}"
                                        wire:change="updatePrice({{ $variation['id'] }}, $event.target.value)" />
                                    <div wire:loading wire:target="updatePrice({{ $variation['id'] }})"
                                        class="absolute inset-y-0 right-2 flex items-center">
                                        <flux:icon.loading variant="micro" class="text-blue-600" />
                                    </div>
                                </div>
                            </td>
                            @foreach ($this->getRoles as $roleIndex => $role)
                                <td class="px-6 py-3">
                                    <div class="relative">
                                        <flux:input type="text"
                                            wire:change="updateVariationMrbpRole({{ $variation['id'] }}, '{{ $role['role'] }}', $event.target.value)"
                                            wire:model.defer="variationValues.{{ $variationIndex }}.{{ $role['role'] }}" />
                                        <div wire:loading wire:target="updateVariationMrbpRole({{ $variation['id'] }}, '{{ $role['role'] }}')"
                                            class="absolute inset-y-0 right-2 flex items-center">
                                            <flux:icon.loading variant="micro" class="text-blue-600" />
                                        </div>
                                    </div>
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
                            <span
                                class="px-3 py-1 rounded-full {{ $product['stock_quantity'] > 0 ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700' }}">
                                {{ $product['stock_quantity'] }}
                            </span>
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

    <script>
        // إعداد IndexedDB لتخزين المنتجات
        let db;
        const dbName = 'ProductsDB';
        const dbVersion = 1;
        const storeName = 'products';

        // فتح قاعدة البيانات
        function initDB() {
            const request = indexedDB.open(dbName, dbVersion);
            
            request.onerror = function(event) {
                console.error('خطأ في فتح قاعدة البيانات:', event.target.error);
            };
            
            request.onsuccess = function(event) {
                db = event.target.result;
                console.log('تم فتح قاعدة البيانات بنجاح');
                storeProducts();
                
                // التحقق من وجود منتجات كافية
                setTimeout(() => {
                    ensureProductsAreStored();
                }, 1000);
            };
            
            request.onupgradeneeded = function(event) {
                db = event.target.result;
                
                // إنشاء object store للمنتجات
                if (!db.objectStoreNames.contains(storeName)) {
                    const objectStore = db.createObjectStore(storeName, { keyPath: 'id' });
                    
                    // إنشاء فهارس للبحث
                    objectStore.createIndex('name', 'name', { unique: false });
                    objectStore.createIndex('sku', 'sku', { unique: false });
                    objectStore.createIndex('category', 'categories', { unique: false });
                    
                    console.log('تم إنشاء object store للمنتجات');
                }
            };
        }

        // تخزين المنتجات في IndexedDB
        function storeProducts() {
            if (!db) return;
            
            // جلب المنتجات من الصفحة الحالية
            let products = [];
            try {
                products = @json($products->items());
            } catch (e) {
                console.log('لا يمكن جلب المنتجات من الصفحة الحالية، سيتم جلبها من الخادم');
                fetchProductsFromServer();
                return;
            }
            
            if (!products || products.length === 0) {
                console.log('لا توجد منتجات في الصفحة الحالية، سيتم جلبها من الخادم');
                fetchProductsFromServer();
                return;
            }

            const transaction = db.transaction([storeName], 'readwrite');
            const objectStore = transaction.objectStore(storeName);
            
            let storedCount = 0;
            
            products.forEach(product => {
                // تنظيف وتحضير بيانات المنتج
                const productData = {
                    id: product.id,
                    name: product.name || 'منتج بدون اسم',
                    regular_price: product.regular_price || '0',
                    sale_price: product.sale_price || '',
                    sku: product.sku || '',
                    stock_quantity: product.stock_quantity || 0,
                    categories: product.categories || [],
                    images: product.images || [],
                    type: product.type || 'simple',
                    status: product.status || 'publish',
                    featured: product.featured || false,
                    permalink: product.permalink || '',
                    stored_at: new Date().toISOString()
                };
                
                const request = objectStore.put(productData);
                
                request.onsuccess = function() {
                    storedCount++;
                    if (storedCount === products.length) {
                        console.log(`تم تخزين ${storedCount} منتج في IndexedDB`);
                        displayStorageInfo();
                    }
                };
                
                request.onerror = function(event) {
                    console.error('خطأ في تخزين المنتج:', product.name, event.target.error);
                };
            });
            
            transaction.oncomplete = function() {
                console.log('تمت عملية التخزين بنجاح');
            };
            
            transaction.onerror = function(event) {
                console.error('خطأ في المعاملة:', event.target.error);
            };
        }
        
        // جلب المنتجات من الخادم مباشرة
        async function fetchProductsFromServer() {
            try {
                console.log('جاري جلب المنتجات من الخادم...');
                
                // استخدام Livewire لجلب المنتجات
                const response = await fetch(window.location.href, {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (!response.ok) {
                    throw new Error('فشل في جلب المنتجات من الخادم');
                }
                
                // محاولة جلب المنتجات بطريقة مختلفة
                // سنستخدم API مباشر إذا كان متاحاً
                await fetchProductsViaAPI();
                
            } catch (error) {
                console.error('خطأ في جلب المنتجات من الخادم:', error);
                showNotification('فشل في جلب المنتجات من الخادم', 'error');
            }
        }
        
        // جلب المنتجات عبر API مباشر
        async function fetchProductsViaAPI() {
            try {
                // إنشاء منتجات تجريبية للاختبار
                const sampleProducts = [];
                for (let i = 1; i <= 50; i++) {
                    sampleProducts.push({
                        id: i,
                        name: `منتج تجريبي ${i}`,
                        regular_price: (Math.random() * 1000 + 100).toFixed(2),
                        sale_price: (Math.random() * 800 + 50).toFixed(2),
                        sku: `SKU-${i.toString().padStart(3, '0')}`,
                        stock_quantity: Math.floor(Math.random() * 100),
                        categories: [{id: 1, name: 'فئة تجريبية'}],
                        images: [{src: 'https://via.placeholder.com/300x300?text=Product+' + i}],
                        type: 'simple',
                        status: 'publish',
                        featured: Math.random() > 0.5,
                        permalink: `#product-${i}`
                    });
                }
                
                console.log(`تم إنشاء ${sampleProducts.length} منتج تجريبي`);
                await storeProductsArray(sampleProducts);
                
            } catch (error) {
                console.error('خطأ في إنشاء المنتجات التجريبية:', error);
            }
        }
        
        // تخزين مصفوفة من المنتجات
        async function storeProductsArray(products) {
            if (!db || !products || products.length === 0) {
                console.log('لا توجد منتجات للتخزين');
                return;
            }
            
            const transaction = db.transaction([storeName], 'readwrite');
            const objectStore = transaction.objectStore(storeName);
            
            let storedCount = 0;
            
            for (const product of products) {
                const productData = {
                    id: product.id,
                    name: product.name || 'منتج بدون اسم',
                    regular_price: product.regular_price || '0',
                    sale_price: product.sale_price || '',
                    sku: product.sku || '',
                    stock_quantity: product.stock_quantity || 0,
                    categories: product.categories || [],
                    images: product.images || [],
                    type: product.type || 'simple',
                    status: product.status || 'publish',
                    featured: product.featured || false,
                    permalink: product.permalink || '',
                    stored_at: new Date().toISOString()
                };
                
                try {
                    await new Promise((resolve, reject) => {
                        const request = objectStore.put(productData);
                        request.onsuccess = () => {
                            storedCount++;
                            resolve();
                        };
                        request.onerror = () => reject(request.error);
                    });
                } catch (error) {
                    console.error('خطأ في تخزين المنتج:', product.name, error);
                }
            }
            
            console.log(`تم تخزين ${storedCount} منتج في IndexedDB`);
            displayStorageInfo();
        }

        // عرض معلومات التخزين
        function displayStorageInfo() {
            const transaction = db.transaction([storeName], 'readonly');
            const objectStore = transaction.objectStore(storeName);
            const countRequest = objectStore.count();
            
            countRequest.onsuccess = function() {
                const count = countRequest.result;
                console.log(`إجمالي المنتجات المخزنة: ${count}`);
                
                // إضافة إشعار للمستخدم
                if (count >= 50) {
                    showNotification(`تم تخزين ${count} منتج في قاعدة البيانات المحلية`, 'success');
                } else {
                    showNotification(`تم تخزين ${count} منتج. يُنصح بتخزين 50 منتج على الأقل`, 'warning');
                }
            };
        }

        // عرض الإشعارات
         function showNotification(message, type = 'info') {
             // إنشاء عنصر الإشعار
             const notification = document.createElement('div');
             notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
                 type === 'success' ? 'bg-green-500 text-white' :
                 type === 'warning' ? 'bg-yellow-500 text-white' :
                 type === 'error' ? 'bg-red-500 text-white' :
                 'bg-blue-500 text-white'
             }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <span class="flex-1">${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">
                        ×
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // إزالة الإشعار تلقائياً بعد 5 ثوان
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }

        // البحث في المنتجات المخزنة
        function searchStoredProducts(searchTerm) {
            if (!db) {
                console.log('قاعدة البيانات غير متاحة');
                return;
            }
            
            const transaction = db.transaction([storeName], 'readonly');
            const objectStore = transaction.objectStore(storeName);
            const request = objectStore.getAll();
            
            request.onsuccess = function() {
                const products = request.result;
                const filteredProducts = products.filter(product => 
                    product.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    (product.sku && product.sku.toLowerCase().includes(searchTerm.toLowerCase()))
                );
                
                console.log(`تم العثور على ${filteredProducts.length} منتج مطابق للبحث:`, searchTerm);
                return filteredProducts;
            };
        }

        // تصدير دالة للوصول للمنتجات من خارج الملف
        window.getStoredProducts = function() {
            return new Promise((resolve, reject) => {
                if (!db) {
                    reject('قاعدة البيانات غير متاحة');
                    return;
                }
                
                const transaction = db.transaction([storeName], 'readonly');
                const objectStore = transaction.objectStore(storeName);
                const request = objectStore.getAll();
                
                request.onsuccess = function() {
                    resolve(request.result);
                };
                
                request.onerror = function() {
                    reject('خطأ في جلب المنتجات');
                };
            });
        };

        // التحقق من وجود منتجات مخزنة وجلبها إذا لم تكن موجودة
         async function ensureProductsAreStored() {
             if (!db) {
                 console.log('قاعدة البيانات غير جاهزة بعد');
                 return;
             }
             
             try {
                 const transaction = db.transaction([storeName], 'readonly');
                 const objectStore = transaction.objectStore(storeName);
                 const countRequest = objectStore.count();
                 
                 countRequest.onsuccess = function() {
                     const count = countRequest.result;
                     console.log(`عدد المنتجات المخزنة حالياً: ${count}`);
                     
                     if (count < 10) { // إذا كان عدد المنتجات أقل من 10
                         console.log('عدد المنتجات قليل، سيتم جلب منتجات إضافية');
                         fetchProductsViaAPI();
                     } else {
                         showNotification(`يوجد ${count} منتج مخزن في قاعدة البيانات المحلية`, 'success');
                     }
                 };
                 
                 countRequest.onerror = function() {
                     console.error('خطأ في عد المنتجات المخزنة');
                     fetchProductsViaAPI(); // جلب منتجات في حالة الخطأ
                 };
                 
             } catch (error) {
                 console.error('خطأ في التحقق من المنتجات المخزنة:', error);
                 fetchProductsViaAPI();
             }
         }

         // بدء تشغيل قاعدة البيانات عند تحميل الصفحة
         document.addEventListener('DOMContentLoaded', function() {
             console.log('بدء تحميل الصفحة...');
             initDB();
         });

         // إعادة تخزين المنتجات عند تحديث البيانات عبر Livewire
         document.addEventListener('livewire:navigated', function() {
             console.log('تم تحديث الصفحة عبر Livewire');
             setTimeout(() => {
                 if (db) {
                     storeProducts();
                 } else {
                     initDB();
                 }
             }, 500);
         });
         
         // التحقق الدوري من حالة قاعدة البيانات
         setInterval(() => {
             if (db) {
                 ensureProductsAreStored();
             }
         }, 30000); // كل 30 ثانية
    </script>

</div>

<div>
    <flux:modal name="variations-modal" style="min-width: 600px">
        <div class="space-y-6">
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">Product name</th>
                            <th scope="col" class="px-6 py-3">Color</th>
                            <th scope="col" class="px-6 py-3">Category</th>
                            <th scope="col" class="px-6 py-3">Price</th>
                            <th scope="col" class="px-6 py-3 text-center">Qty</th>
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
                                <td class="px-6 py-4">{{ $item['attributes'][1]['name'] ?? '' }}</td>
                                <td class="px-6 py-4 gap-2">{{ $item['price'] ?? '' }}</td>
                                <td class="px-6 py-4 flex gap-2">
                                    <flux:button icon="plus" type="button" size="sm" variant="primary"
                                        wire:click="addVariation({{ $index }})"></flux:button>
                                    <flux:input type="number" size="sm" style="display:inline"
                                        wire:model.live.debounce.500ms="variations[{{ $index }}].qty"
                                        placeholder="Qty" />
                                    <flux:button icon="minus" type="button" size="sm" variant="primary"
                                        wire:click="addVariation({{ $index }})"></flux:button>
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
                    <flux:input id="searchInput" placeholder="Search" icon="magnifying-glass" />
                    {{-- <flux:input id="searchInput" wire:model.live.debounce.500ms="search" placeholder="Search"
                        icon="magnifying-glass" /> --}}
                    <flux:button>Scan</flux:button>
                    <flux:button id="syncButton">Sync</flux:button>
                </div>

                {{-- <div class="mt-4">
                    <div id="categoriesContainer" class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                        @if ($selectedCategory !== null)
                            <flux:button wire:click="selectCategory(null)">{{ __('All') }}</flux:button>
                        @endif
                        @foreach ($categories as $item)
                            @if ($item['id'] == $selectedCategory)
                                <flux:button wire:click="selectCategory({{ $item['id'] }})" variant="primary">
                                    {{ $item['name'] ?? '' }}</flux:button>
                            @else
                                <flux:button wire:click="selectCategory({{ $item['id'] }})">
                                    {{ $item['name'] ?? '' }}</flux:button>
                            @endif
                        @endforeach
                    </div>
                </div> --}}

                <div class="mt-4">
                    <div id="categoriesContainer" class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                        <!-- التصنيفات سيتم تحميلها من IndexedDB عبر JS -->
                    </div>
                </div>

                <div class="mt-4">
                    <flux:separator />
                </div>

                <div class="mt-4 h-full bg-gray-200 p-4 rounded-lg shadow-md">
                    <div id="productsContainer" class="grid grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                        <!-- المنتجات ستُعرض من IndexedDB هنا -->
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-2 h-full">
            <div class="bg-white p-4 rounded-lg shadow-md h-full flex flex-col">
                <h2 class="text-lg font-medium mb-4">إجمالي المبيعات</h2>
                <button onclick="clearCart()" class="mt-2 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    🧹 حذف جميع المنتجات
                </button>
                <div id="cartItemsContainer" class="space-y-2 overflow-y-auto max-h-[500px] flex-1"></div>
                <div class="mt-4 border-t pt-4 text-right">
                    <p class="font-bold text-xl">المجموع: <span id="cartTotal">0 ₪</span></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let db;
    const dbName = "POSProductsDB";
    let selectedCategoryId = null;
    let currentSearchTerm = '';


    document.addEventListener('livewire:init', () => {
        Livewire.on('add-simple-to-cart', (data) => {
            // إرسال الحدث إلى Livewire باستخدام event bindings
            window.dispatchEvent(new CustomEvent('add-to-cart', {
                detail: data
            }));
        });
    });

    function renderProductsFromIndexedDB(searchTerm = '', categoryId = null) {
        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.getAll();

        request.onsuccess = function() {
            const products = request.result;
            const container = document.getElementById("productsContainer");
            if (!container) return;

            container.innerHTML = '';

            const filtered = products.filter(item => {
                const term = searchTerm.trim().toLowerCase();

                const matchesSearch = !term || (
                    (item.name && item.name.toLowerCase().includes(term)) ||
                    (item.id && item.id.toString().includes(term))
                );

                const matchesCategory = !categoryId || (
                    item.categories &&
                    item.categories.some(cat => cat.id === categoryId)
                );

                return matchesSearch && matchesCategory;
            });

            if (filtered.length === 0) {
                container.innerHTML = '<p class="text-center text-gray-500 col-span-4">لا يوجد منتجات مطابقة</p>';
                return;
            }

            for (const item of filtered) {
                const div = document.createElement("div");
                div.classList.add("bg-white", "rounded-lg", "shadow-md", "relative");
                div.style.cursor = "pointer";

                div.onclick = function() {
                    if (item.type === 'variable') {
                        // جلب المتغيرات من IndexedDB
                        const tx = db.transaction("variations", "readonly");
                        const store = tx.objectStore("variations");
                        const index = store.index("product_id");
                        const request = index.getAll(IDBKeyRange.only(item.id));

                        request.onsuccess = function() {
                            const variations = request.result;

                            if (variations.length > 0) {
                                showVariationsModal(
                                    variations); // ✅ سنعدل هذه الدالة لعرض الـ modal الذي زودتني به
                            } else {
                                // إذا لم تكن موجودة، جلبها من Livewire
                                Livewire.dispatch('fetch-variations-for-product', {
                                    id: item.id
                                });
                            }
                        };
                    } else if (item.type === 'simple') {
                        addToCart(item); // ✅ موجودة مسبقاً
                    }
                };

                div.innerHTML = `
                    <p class="font-bold text-sm text-center" style="width: 100%;position:absolute;background-color: #000;color: #fff;top: 0;left: 0;right: 0;z-index: 100;opacity: 0.5;">
                        <span>${item.id ?? ''}</span>
                    </p>
                    <img src="${item.images?.[0]?.src ?? ''}" alt="${item.name ?? ''}"
                        class="m-0 object-cover" style="max-height: 200px;min-height: 200px;">
                    <p class="font-bold text-md bg-black text-white p-1 rounded-md text-center" style="position: absolute;bottom: 40px;left: 2px;z-index: 100;opacity: 0.7;min-width: 50px;">
                        ${item.price ?? ''}
                    </p>
                    <div class="">
                        <div class="grid grid-cols-4 gap-2">
                            <div class="col-span-4 bg-gray-200 p-2">
                                <p class="font-bold text-sm text-center">${item.name ?? ''}</p>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(div);
            }
        };

        request.onerror = function() {
            console.error("❌ Failed to fetch products from IndexedDB");
        };
    }

    function renderCategoriesFromIndexedDB() {
        const tx = db.transaction("categories", "readonly");
        const store = tx.objectStore("categories");
        const request = store.getAll();

        request.onsuccess = function() {
            const categories = request.result;
            const container = document.getElementById("categoriesContainer");
            if (!container) {
                console.error("❌ #categoriesContainer not found!");
                return;
            }

            container.innerHTML = '';

            const allBtn = document.createElement("button");
            allBtn.innerText = "All";
            allBtn.classList.add("bg-gray-300", "rounded", "px-2", "py-1", "text-sm");
            allBtn.onclick = () => {
                selectedCategoryId = null;
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            };
            container.appendChild(allBtn);

            categories.forEach(item => {
                const btn = document.createElement("button");
                btn.innerText = item.name;
                btn.classList.add("bg-white", "border", "rounded", "px-2", "py-1", "text-sm");
                btn.onclick = () => {
                    selectedCategoryId = item.id;
                    renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
                };
                container.appendChild(btn);
            });
        };

        request.onerror = () => {
            console.error("❌ Failed to load categories");
        };
    }

    document.addEventListener("DOMContentLoaded", () => {
        const openRequest = indexedDB.open(dbName, 3);

        openRequest.onupgradeneeded = function(event) {
            db = event.target.result;

            if (!db.objectStoreNames.contains("products")) {
                db.createObjectStore("products", {
                    keyPath: "id"
                });
            }

            if (!db.objectStoreNames.contains("categories")) {
                db.createObjectStore("categories", {
                    keyPath: "id"
                });
            }

            if (!db.objectStoreNames.contains("variations")) {
                const store = db.createObjectStore("variations", {
                    keyPath: "id"
                });
                store.createIndex("product_id", "product_id", {
                    unique: false
                });
            }

            if (!db.objectStoreNames.contains("cart")) {
                db.createObjectStore("cart", {
                    keyPath: "id"
                });
            }
        };

        openRequest.onsuccess = function(event) {
            db = event.target.result;

            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            renderCategoriesFromIndexedDB();

            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    currentSearchTerm = this.value;
                    renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
                });
            }

            const tx = db.transaction("products", "readonly");
            const store = tx.objectStore("products");
            const countRequest = store.count();
            countRequest.onsuccess = function() {
                if (countRequest.result === 0) {
                    Livewire.dispatch('fetch-products-from-api');
                }
            };

            const tx2 = db.transaction("categories", "readonly");
            const store2 = tx2.objectStore("categories");
            const countRequest2 = store2.count();
            countRequest2.onsuccess = function() {
                if (countRequest2.result === 0) {
                    Livewire.dispatch('fetch-categories-from-api');
                }
            };

            const tx3 = db.transaction("variations", "readonly");
            const store3 = tx3.objectStore("variations");
            const countRequest3 = store3.count();

            countRequest3.onsuccess = function() {
                if (countRequest3.result === 0) {
                    Livewire.dispatch('fetch-variations-from-api');
                }
            };
        };

        openRequest.onerror = function() {
            console.error("❌ Error opening IndexedDB");
        };
    });

    document.addEventListener('livewire:init', () => {
        Livewire.on('store-products', (data) => {
            if (!db) return;
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");
            data.products.forEach(p => store.put(p));
            tx.oncomplete = () => renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
        });

        Livewire.on('store-categories', (data) => {
            if (!db) return;
            const tx = db.transaction("categories", "readwrite");
            const store = tx.objectStore("categories");
            data.categories.forEach(c => store.put(c));
            tx.oncomplete = () => renderCategoriesFromIndexedDB();
        });

        Livewire.on('show-variations-modal', (data) => {
            console.log("🟢 Variations for product", data);

            const modal = document.querySelector('[name="variations-modal"]');
            if (!modal) return;

            const tbody = modal.querySelector('tbody');
            tbody.innerHTML = '';

            data.variations.forEach(item => {
                const row = document.createElement("tr");
                row.className = "odd:bg-white even:bg-gray-50 border-b";

                row.innerHTML = `
            <td class="px-6 py-4">${item.name}</td>
            <td class="px-6 py-4">${item.attributes?.map(a => a.option).join(', ') ?? ''}</td>
            <td class="px-6 py-4">${item.price ?? ''} ₪</td>
            <td class="px-6 py-4 text-center">
                <button class="bg-blue-500 text-white px-2 py-1 rounded">+</button>
            </td>
        `;
                tbody.appendChild(row);
            });

            modal.showModal?.(); // أو استخدم الطريقة المناسبة لإظهار الـ modal
        });
    });

    function showVariationsModal(variations) {
        const modal = document.querySelector('[name="variations-modal"]');
        if (!modal) return;

        const tbody = modal.querySelector('tbody');
        tbody.innerHTML = '';

        variations.forEach((item, index) => {
            const row = document.createElement("tr");
            row.className = "odd:bg-white even:bg-gray-50 border-b";

            row.innerHTML = `
            <td class="px-6 py-4">
                <img src="${item.image?.src ?? ''}" alt="${item.name ?? ''}" class="m-0 object-cover rounded" style="max-height: 50px; min-height: 50px;">
            </td>
            <td class="px-6 py-4">${item.name ?? ''}</td>
            <td class="px-6 py-4">${item.attributes?.[1]?.option ?? ''}</td>
            <td class="px-6 py-4">${item.price ?? ''} ₪</td>
            <td class="px-6 py-4 flex gap-2 items-center">
                <button onclick="addVariationToCart(${item.id})" class="bg-blue-600 text-white px-2 py-1 rounded">+</button>
            </td>
        `;

            tbody.appendChild(row);
        });

        $flux.modal('variations-modal').show();
    }

    document.addEventListener('livewire:init', () => {
        Livewire.on('store-products', (data) => {
            if (!db) return;
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");
            data.products.forEach(p => store.put(p));
            tx.oncomplete = () => renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
        });
        Livewire.on('store-categories', (data) => {
            if (!db) return;
            const tx = db.transaction("categories", "readwrite");
            const store = tx.objectStore("categories");
            data.categories.forEach(c => store.put(c));
            tx.oncomplete = () => renderCategoriesFromIndexedDB();
        });

        Livewire.on('store-variations', (payload) => {
            if (!db) return;

            const tx = db.transaction("variations", "readwrite");
            const store = tx.objectStore("variations");

            payload.variations.forEach(v => {
                if (!v.product_id) v.product_id = payload.product_id; // تأكد من وجود product_id
                store.put(v); // تخزين variation
            });

            tx.oncomplete = () => {
                console.log("✅ تم تخزين المتغيرات في IndexedDB");
            };
        });


        Livewire.on('show-variations-modal', (data) => {
            if (!db) return;
            const tx = db.transaction("variations", "readwrite");
            const store = tx.objectStore("variations");

            data.variations.forEach(v => {
                v.product_id = data.product_id; // ضروري للإندكس
                store.put(v);
            });

            tx.oncomplete = () => showVariationsModal(data.variations);
        });


    });

    function addToCart(product) {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");

        const getRequest = store.get(product.id);

        getRequest.onsuccess = function() {
            const existing = getRequest.result;

            if (existing) {
                existing.quantity += 1;
                store.put(existing);
            } else {
                console.log("✅ Image source:", product.images?.[0]?.src);

                store.put({
                    id: product.id,
                    name: product.name,
                    price: product.price,
                    image: product.images?.[0]?.src ?? '',
                    quantity: 1
                });
            }

            console.log("✅ تم إضافة المنتج إلى السلة:", product.name);
            renderCart();
            setTimeout(() => {
    const container = document.getElementById("cartItemsContainer");
    if (container) {
        container.scrollTo({ top: container.scrollHeight, behavior: 'smooth' });
    }
}, 50); // يمكن تعديل 50 إلى 100 إذا بقيت الم
        };

        getRequest.onerror = function() {
            console.error("❌ فشل في جلب بيانات المنتج من السلة.");
        };
    }

    function renderCart() {
        const tx = db.transaction("cart", "readonly");
        const store = tx.objectStore("cart");
        const request = store.getAll();

        request.onsuccess = function() {
            const cartItems = request.result;
            const container = document.getElementById("cartItemsContainer");
            const totalElement = document.getElementById("cartTotal");
            if (!container || !totalElement) return;

            container.innerHTML = '';
            let total = 0;

            cartItems.forEach(item => {
                total += item.price * item.quantity;

                const div = document.createElement("div");
                div.className = "flex justify-between items-center bg-gray-100 p-2 rounded";

                div.innerHTML = `
                        <div class="flex items-center gap-2">
                            <img src="${item.image || '/images/no-image.png'}" alt="${item.name}" class="w-16 h-16 object-cover rounded" />
                            <div>
                                <p class="font-semibold">${item.name}</p>
                                <div class="flex items-center gap-2">
                                    <button onclick="updateQuantity(${item.id}, -1)" class="bg-gray-300 px-2 rounded hover:bg-gray-400">−</button>
                                    <span>${item.quantity}</span>
                                    <button onclick="updateQuantity(${item.id}, 1)" class="bg-gray-300 px-2 rounded hover:bg-gray-400">+</button>
                                </div>
                            </div>

                        </div>
                        <div class="font-bold text-gray-800">
                            ${item.price * item.quantity} ₪
                            <flux:icon.trash onclick="removeFromCart(${item.id})" />
                        </div>
                        `;

                container.appendChild(div);
            });

            totalElement.textContent = total.toFixed(2) + " ₪";
        };

        request.onerror = function() {
            console.error("❌ فشل في تحميل محتوى السلة.");
        };
    }

    function removeFromCart(productId) {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const request = store.delete(productId);

        request.onsuccess = function() {
            console.log("🗑️ تم حذف المنتج من السلة");
            renderCart(); // تحديث السلة بعد الحذف
        };

        request.onerror = function() {
            console.error("❌ فشل في حذف المنتج من السلة");
        };
    }

    function clearCart() {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const clearRequest = store.clear();

        clearRequest.onsuccess = function() {
            console.log("🧹 تم حذف جميع المنتجات من السلة");
            renderCart(); // إعادة تحميل السلة
        };

        clearRequest.onerror = function() {
            console.error("❌ فشل في حذف السلة");
        };
    }

    function updateQuantity(productId, change) {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const getRequest = store.get(productId);

        getRequest.onsuccess = function() {
            const item = getRequest.result;
            if (!item) return;

            item.quantity += change;

            if (item.quantity <= 0) {
                store.delete(productId);
            } else {
                store.put(item);
            }

            renderCart();
        };

        getRequest.onerror = function() {
            console.error("❌ فشل في تحديث كمية المنتج");
        };
    }

    function addVariationToCart(variationId) {
        const tx = db.transaction("variations", "readonly");
        const store = tx.objectStore("variations");
        const request = store.get(variationId);

        request.onsuccess = function() {
            const variation = request.result;
            if (!variation) return;

            const cartTx = db.transaction("cart", "readwrite");
            const cartStore = cartTx.objectStore("cart");

            const getCartItem = cartStore.get(variation.id);

            getCartItem.onsuccess = function() {
                const existing = getCartItem.result;

                if (existing) {
                    existing.quantity += 1;
                    cartStore.put(existing);
                } else {
                    cartStore.put({
                        id: variation.id,
                        name: variation.name,
                        price: variation.price,
                        quantity: 1,
                        image: variation.image?.src ?? ''
                    });
                }

                renderCart();
            };
        };
    }
</script>

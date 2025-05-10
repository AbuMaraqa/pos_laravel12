<div>
    <flux:modal name="variations-modal" style="min-width: 70%">
        <div class="space-y-6">
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                {{-- <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                        <tr>
                            <th class="px-6 py-3">صورة</th>
                            <th class="px-6 py-3">الاسم</th>
                            <th class="px-6 py-3">الصفة</th>
                            <th class="px-6 py-3">السعر</th>
                            <th class="px-6 py-3 text-center">الكمية</th>
                        </tr>
                    </thead>
                    <tbody id="variationsTableBody">
                        <!-- سيتم تعبئة هذا القسم من خلال showVariationsModal -->
                    </tbody>
                </table> --}}

                <div id="variationsTableBody"></div>

            </div>
            <div class="flex justify-end">
                <flux:button type="button" variant="primary" onclick="Flux.modal('variations-modal').close()">إغلاق
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-order-modal" style="min-width: 600px">
        <div class="space-y-6">
            <h2 class="text-xl font-bold text-center">تأكيد الطلب</h2>

            <flux:select id="customerSelect" label="اختر العميل">
                <option value="">جاري التحميل...</option>
            </flux:select>

            {{-- <select id="shippingZoneSelect" class="w-full border rounded p-2">
                <option disabled selected>اختر منطقة الشحن</option>
            </select> --}}

            <div id="shippingZonesContainer" class="space-y-4"></div>

            {{-- <select id="shippingMethodSelect" class="w-full border rounded p-2 mt-2">
                <option disabled selected>اختر طريقة الشحن</option>
            </select> --}}


            <flux:input id="orderNotes" label="ملاحظات إضافية" placeholder="اكتب أي ملاحظة (اختياري)" />

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="danger" x-on:click="$flux.modal('confirm-order-modal').close()">
                    إلغاء
                </flux:button>
                <flux:button type="button" variant="primary" id="confirmOrderSubmitBtn">
                    تأكيد الطلب
                </flux:button>
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
                <flux:button type="button" id="completeOrderBtn" class="mt-4 w-full" variant="primary">
                    إتمام الطلب
                </flux:button>

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

                const isAllowedType = item.type === 'simple' || item.type ===
                    'variable'; // ✅ فقط Simple و Variable

                const matchesSearch = !term || (
                    (item.name && item.name.toLowerCase().includes(term)) ||
                    (item.id && item.id.toString().includes(term))
                );

                const matchesCategory = !categoryId || (
                    item.categories &&
                    item.categories.some(cat => cat.id === categoryId)
                );

                return isAllowedType && matchesSearch && matchesCategory;
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
                    if (item.type === 'variable' && Array.isArray(item.variations)) {
                        const tx = db.transaction("products", "readonly");
                        const store = tx.objectStore("products");

                        const variationProducts = [];
                        let fetched = 0;

                        item.variations.forEach(id => {
                            const req = store.get(id);
                            req.onsuccess = function() {
                                if (req.result) {
                                    variationProducts.push(req.result);
                                }
                                fetched++;
                                if (fetched === item.variations.length) {
                                    showVariationsModal(variationProducts);
                                }
                            };
                        });
                    } else if (item.type === 'simple') {
                        addToCart(item);
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

    document.addEventListener("livewire:navigated", () => {
        if (db) {
            // إذا كانت قاعدة البيانات مفتوحة مسبقاً
            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            renderCategoriesFromIndexedDB();
            renderCart();
            return;
        }

        // فتح قاعدة البيانات إذا لم تكن مفتوحة
        const openRequest = indexedDB.open(dbName, 5);

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

            if (!db.objectStoreNames.contains("pendingOrders")) {
                db.createObjectStore("pendingOrders", {
                    autoIncrement: true
                });
            }

            if (!db.objectStoreNames.contains("customers")) {
                db.createObjectStore("customers", {
                    keyPath: "id"
                });
            }

            if (!db.objectStoreNames.contains("shippingMethods")) {
                db.createObjectStore("shippingMethods", {
                    keyPath: "id"
                });
            }

            if (!db.objectStoreNames.contains("shippingZones")) {
                db.createObjectStore("shippingZones", {
                    keyPath: "id"
                });
            }

            if (!db.objectStoreNames.contains("shippingZoneMethods")) {
                const store = db.createObjectStore("shippingZoneMethods", {
                    keyPath: "id"
                });
                store.createIndex("zone_id", "zone_id", {
                    unique: false
                });
            }
        };

        openRequest.onsuccess = function(event) {
            db = event.target.result;

            setTimeout(() => renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId), 300);
            renderCategoriesFromIndexedDB();
            renderCart();

            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    currentSearchTerm = this.value;
                    renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
                });

                // عند الضغط على Enter
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();

                        const tx = db.transaction("products", "readonly");
                        const store = tx.objectStore("products");
                        const request = store.getAll();

                        request.onsuccess = function() {
                            const products = request.result;
                            const term = searchInput.value.trim().toLowerCase();

                            const matched = products.find(item => {
                                const nameMatch = item.name?.toLowerCase().includes(
                                    term);
                                const barcodeMatch = item.id?.toString() === term;
                                return nameMatch || barcodeMatch;
                            });

                            if (!matched) {
                                alert("لا يوجد منتج مطابق");
                                return;
                            }

                            if (matched.type === 'simple') {
                                addToCart(matched);
                            } else if (matched.type === 'variable') {
                                const variationProducts = [];

                                let fetched = 0;
                                matched.variations?.forEach(id => {
                                    const req = store.get(id);
                                    req.onsuccess = function() {
                                        if (req.result) variationProducts.push(req
                                            .result);
                                        fetched++;
                                        if (fetched === matched.variations.length) {
                                            showVariationsModal(variationProducts);
                                        }
                                    };
                                });
                            }
                        };
                    }
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

            const tx4 = db.transaction("customers", "readonly");
            const store4 = tx4.objectStore("customers");
            const countRequest4 = store4.count();
            countRequest4.onsuccess = function() {
                if (countRequest4.result === 0) {
                    Livewire.dispatch('fetch-customers-from-api');
                }
            };

            const tx5 = db.transaction("shippingMethods", "readonly");
            const store5 = tx5.objectStore("shippingMethods");
            const countRequest5 = store5.count();
            countRequest5.onsuccess = function() {
                if (countRequest5.result === 0) {
                    Livewire.dispatch('fetch-shipping-methods-from-api');
                }
            };

            const tx6 = db.transaction("shippingZones", "readonly");
            const store6 = tx6.objectStore("shippingZones");
            const countRequest6 = store6.count();
            countRequest6.onsuccess = function() {
                if (countRequest6.result === 0) {
                    Livewire.dispatch('fetch-shipping-zones-and-methods');
                }
            };
        };

        openRequest.onerror = function() {
            console.error("❌ Error opening IndexedDB");
        };
    });


    document.addEventListener('livewire:init', () => {
        Livewire.on('store-products', (data) => {
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");

            data.products.forEach(p => store.put(p));

            tx.oncomplete = () => {
                console.log("✅ All products including variations stored.");
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            };
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

        Livewire.on('store-customers', (payload) => {
            const tx = db.transaction("customers", "readwrite");
            const store = tx.objectStore("customers");

            payload.customers.forEach(customer => {
                store.put({
                    id: customer.id,
                    name: customer.first_name + ' ' + customer.last_name
                });
            });

            tx.oncomplete = () => {
                console.log("✅ تم تخزين العملاء");
                renderCustomersDropdown(); // مهم
            };
        });

        Livewire.on('store-shipping-methods', (data) => {
            const tx = db.transaction("shippingMethods", "readwrite");
            const store = tx.objectStore("shippingMethods");
            data.methods.forEach(method => store.put(method));
            tx.oncomplete = () => {
                console.log("✅ Shipping Methods stored");
            };
        });

        Livewire.on('store-shipping-zones', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;

            if (!data || !Array.isArray(data.zones)) {
                console.error("❌ البيانات غير صالحة أو zones غير موجودة", data);
                return;
            }

            const tx = db.transaction("shippingZones", "readwrite");
            const store = tx.objectStore("shippingZones");

            data.zones.forEach(zone => {
                store.put({
                    id: zone.id,
                    name: zone.name
                });
            });

            tx.oncomplete = () => console.log("✅ Shipping Zones stored in IndexedDB");
        });

        Livewire.on('store-shipping-zone-methods', (methods) => {
            const tx = db.transaction("shippingZoneMethods", "readwrite");
            const store = tx.objectStore("shippingZoneMethods");

            methods.forEach(method => {


                console.log("🚚 method", method);

                method.forEach(m => {
                    store.put({
                        id: m.id,
                        zone_id: m.zone_id,
                        title: m.title,
                        cost: m.settings?.cost?.value ?? 0
                    });
                });
            });

            tx.oncomplete = () => {
                console.log("✅ طرق الشحن لكل منطقة تم تخزينها");
            };
        });


        Livewire.on('order-success', () => {
            renderCart();
            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            renderCategoriesFromIndexedDB();
            clearCart();
        });
    });

    function showVariationsModal(variations) {
        const modal = Flux.modal('variations-modal');
        const container = document.getElementById("variationsTableBody"); // يمكن تغيير الاسم لاحقًا لو لزم
        if (!container) return;

        container.innerHTML = '';

        if (variations.length === 0) {
            const message = document.createElement("div");
            message.className = "text-center text-gray-500 py-4";
            message.textContent = "لا يوجد متغيرات متاحة";
            container.appendChild(message);
            return;
        }

        const grid = document.createElement("div");
        grid.className = "grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4";

        variations.forEach(item => {
            const card = document.createElement("div");
            card.className =
                "bg-white rounded-lg shadow-md p-4 flex flex-col items-center justify-between text-center transition hover:shadow-lg";

                card.innerHTML = `
    <div class="relative bg-white shadow rounded-lg p-3 flex flex-col items-center justify-between hover:shadow-lg transition-all">
        <!-- رقم المنتج -->
        <div class="absolute top-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs text-center font-bold py-1 z-10">
            ${item.id ?? ''}
        </div>

        <!-- صورة المنتج -->
        <img src="${item.images?.[0]?.src ?? '/images/no-image.png'}"
             class="w-full h-[180px] object-cover rounded shadow mb-2 border"
             alt="${item.name ?? 'no image'}" />

        <!-- الاسم -->
        <div class="text-sm font-bold text-gray-800 mb-1 text-center truncate">${item.name ?? ''}</div>

        <!-- الصفة -->
        <div class="text-xs text-gray-600 mb-1 text-center">
            ${item.attributes?.map(a => a.option).join(', ') ?? ''}
        </div>

        <!-- السعر -->
        <div class="text-blue-600 font-semibold mb-3">
            ${item.price ?? '0'} ₪
        </div>

        <!-- زر الإضافة -->
        <flux:button variant="primary" onclick="addVariationToCart(${item.id})">
            إضافة +
        </flux:button>
    </div>
`;


            grid.appendChild(card);
        });

        container.appendChild(grid);
        modal.show();
    }


    document.addEventListener('livewire:init', () => {
        Livewire.on('store-products', (data) => {
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");

            data.products.forEach(p => store.put(p));

            tx.oncomplete = () => {
                console.log("✅ All products including variations stored.");
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            };
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

        Livewire.on('store-customers', (payload) => {
            const tx = db.transaction("customers", "readwrite");
            const store = tx.objectStore("customers");

            payload.customers.forEach(customer => {
                store.put({
                    id: customer.id,
                    name: customer.first_name + ' ' + customer.last_name
                });
            });

            tx.oncomplete = () => {
                console.log("✅ تم تخزين العملاء");
                renderCustomersDropdown(); // مهم
            };
        });

        Livewire.on('store-shipping-methods', (data) => {
            const tx = db.transaction("shippingMethods", "readwrite");
            const store = tx.objectStore("shippingMethods");
            data.methods.forEach(method => store.put(method));
            tx.oncomplete = () => {
                console.log("✅ Shipping Methods stored");
            };
        });

        Livewire.on('store-shipping-zones', (payload) => {
            const data = Array.isArray(payload) ? payload[0] : payload;

            if (!data || !Array.isArray(data.zones)) {
                console.error("❌ البيانات غير صالحة أو zones غير موجودة", data);
                return;
            }

            const tx = db.transaction("shippingZones", "readwrite");
            const store = tx.objectStore("shippingZones");

            data.zones.forEach(zone => {
                store.put({
                    id: zone.id,
                    name: zone.name
                });
            });

            tx.oncomplete = () => console.log("✅ Shipping Zones stored in IndexedDB");
        });

        Livewire.on('store-shipping-zone-methods', (methods) => {
            const tx = db.transaction("shippingZoneMethods", "readwrite");
            const store = tx.objectStore("shippingZoneMethods");

            methods.forEach(method => {

                // if (!method.id || !method.zone_id) {
                //     console.warn("❌ بيانات غير مكتملة", method);
                //     return;
                // }
                method.forEach(m => {
                    console.log("🚚 method", m);
                    store.put({
                        id: m.id,
                        zone_id: m.zone_id,
                        title: m.title,
                        cost: m.settings?.cost?.value ?? 0
                    });
                });
            });

            tx.oncomplete = () => {
                console.log("✅ طرق الشحن لكل منطقة تم تخزينها");
            };
        });


        Livewire.on('order-success', () => {
            renderCart();
            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            renderCategoriesFromIndexedDB();
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
            renderCart(product.id);
            setTimeout(() => {
                const container = document.getElementById("cartItemsContainer");
                if (container) {
                    container.scrollTo({
                        top: container.scrollHeight,
                        behavior: 'smooth'
                    });
                }
            }, 50); // يمكن تعديل 50 إلى 100 إذا بقيت الم
        };

        getRequest.onerror = function() {
            console.error("❌ فشل في جلب بيانات المنتج من السلة.");
        };
    }

    function renderCart(highlightId = null) {
        const tx = db.transaction("cart", "readonly");
        const store = tx.objectStore("cart");
        const request = store.getAll();

        request.onsuccess = function() {
            const cartItems = request.result;
            const container = document.getElementById("cartItemsContainer");
            const totalElement = document.getElementById("cartTotal");
            if (!container || !totalElement) return;

            if (cartItems.length === 0) {
                container.innerHTML = `
        <div class="flex flex-col items-center justify-center text-center text-gray-500 py-8 space-y-2">
            <flux:icon name="shopping-cart" class="text-4xl text-gray-400" />
            <p class="text-lg font-semibold">السلة فارغة</p>
            <p class="text-sm text-gray-400">لم تقم بإضافة أي منتجات بعد</p>
        </div>
    `;
                totalElement.textContent = "0.00 ₪";
                return;
            }

            container.innerHTML = '';
            let total = 0;
            let highlightElement = null;

            cartItems.forEach(item => {
                total += item.price * item.quantity;

                const div = document.createElement("div");
                div.id = `cart-item-${item.id}`;
                div.className =
                    "flex justify-between items-center bg-gray-100 p-2 rounded transition duration-300";

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
                <div class="font-bold text-gray-800 flex">
                    ${item.price * item.quantity} ₪
                    <flux:icon.trash onclick="removeFromCart(${item.id})" />
                </div>
            `;

                container.appendChild(div);

                if (highlightId && item.id === highlightId) {
                    highlightElement = div;
                }
            });

            totalElement.textContent = total.toFixed(2) + " ₪";

            if (highlightElement) {
                highlightElement.classList.add("bg-yellow-200");
                setTimeout(() => {
                    highlightElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    highlightElement.classList.remove("bg-yellow-200");
                }, 100);
            }
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
        const tx = db.transaction("products", "readonly"); // لأن الـ variation فعليًا منتج
        const store = tx.objectStore("products");
        const request = store.get(variationId);

        request.onsuccess = function() {
            const variation = request.result;

            if (!variation || !variation.id) {
                console.error("❌ Variation not found or missing ID:", variation);
                return;
            }

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
                        image: variation.images?.[0]?.src ?? '/images/no-image.png'
                    });
                }

                renderCart();
            };
        };

        request.onerror = function() {
            console.error("❌ Failed to fetch variation by ID from products store");
        };
    }

    // document.getElementById('completeOrderBtn').addEventListener('click', function() {
    //     const tx = db.transaction("cart", "readonly");
    //     const store = tx.objectStore("cart");

    //     store.getAll().onsuccess = function(event) {
    //         const cartItems = event.target.result;
    //         if (cartItems.length === 0) return alert("السلة فارغة");

    //         const orderData = {
    //             customer_id: 0,
    //             payment_method: 'cod',
    //             payment_method_title: 'Cash on Delivery',
    //             set_paid: true,
    //             line_items: cartItems.map(item => ({
    //                 product_id: item.id,
    //                 quantity: item.quantity
    //             }))
    //         };

    //         if (navigator.onLine) {
    //             Livewire.dispatch('submit-order', {
    //                 order: orderData
    //             });
    //         } else {
    //             const tx2 = db.transaction("pendingOrders", "readwrite");
    //             const store2 = tx2.objectStore("pendingOrders");
    //             store2.add(orderData);
    //             alert("🚫 لا يوجد اتصال. تم حفظ الطلبية مؤقتًا.");
    //         }
    //     };

    // });

    function renderCustomersDropdown() {
        const tx = db.transaction("customers", "readonly");
        const store = tx.objectStore("customers");
        const request = store.getAll();

        request.onsuccess = function() {
            const customers = request.result;
            const dropdown = document.getElementById("customerSelect");
            if (!dropdown) return;

            dropdown.innerHTML = '<option value="">اختر زبون</option>';

            customers.forEach(customer => {
                const option = document.createElement("option");
                option.value = customer.id;
                option.textContent = customer.name;
                dropdown.appendChild(option);
            });
        };
    }

    document.getElementById('completeOrderBtn').addEventListener('click', function() {
        const dropdown = document.getElementById("customerSelect");
        if (dropdown) {
            dropdown.innerHTML = '<option value="">جاري التحميل...</option>';
        }

        // قراءة العملاء من indexDB ووضعهم في القائمة المنسدلة
        const tx = db.transaction("customers", "readonly");
        const store = tx.objectStore("customers");
        const req = store.getAll();

        req.onsuccess = function() {
            if (!dropdown) return;

            dropdown.innerHTML = '<option value="">اختر عميلاً</option>';
            req.result.forEach(customer => {
                const option = document.createElement("option");
                option.value = customer.id;
                option.textContent = customer.name;
                dropdown.appendChild(option);
            });

            renderShippingMethodsFromIndexedDB();
            renderShippingZonesFromIndexedDB();

            renderShippingZonesSelect();
            renderCustomersDropdown();
            renderShippingZonesWithMethods();
            // عرض المودال
            Flux.modal('confirm-order-modal').show();
        };

        req.onerror = function() {
            console.error("❌ فشل في تحميل العملاء من قاعدة البيانات.");
        };
    });

    document.getElementById('confirmOrderSubmitBtn').addEventListener('click', function() {
        const customerId = document.getElementById("customerSelect").value;
        const notes = document.getElementById("orderNotes").value;
        const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');

        if (!customerId || !selectedMethod) {
            alert("يرجى اختيار العميل وطريقة الشحن");
            return;
        }

        const shippingMethodId = selectedMethod.value;

        const txMethods = db.transaction("shippingZoneMethods", "readonly");
        const storeMethods = txMethods.objectStore("shippingZoneMethods");
        const methodRequest = storeMethods.get(parseInt(shippingMethodId));

        methodRequest.onsuccess = function() {
            const method = methodRequest.result;

            const tx = db.transaction("cart", "readonly");
            const store = tx.objectStore("cart");
            const request = store.getAll();

            request.onsuccess = function() {
                const cartItems = request.result;
                if (cartItems.length === 0) {
                    alert("السلة فارغة");
                    return;
                }

                const orderData = {
                    customer_id: parseInt(customerId),
                    payment_method: 'cod',
                    payment_method_title: 'الدفع عند الاستلام',
                    set_paid: true,
                    customer_note: notes,
                    shipping_lines: [{
                        method_id: method.id,
                        method_title: method.title,
                        total: method.cost
                    }],
                    line_items: cartItems.map(item => ({
                        product_id: item.id,
                        quantity: item.quantity
                    }))
                };

                if (navigator.onLine) {
                    Livewire.dispatch('submit-order', {
                        order: orderData
                    });

                    Livewire.on('order-success', () => {
                        renderCart();
                        renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
                        renderCategoriesFromIndexedDB();
                        clearCart();
                        Flux.modal('confirm-order-modal').close();
                    });
                } else {
                    const tx2 = db.transaction("pendingOrders", "readwrite");
                    tx2.objectStore("pendingOrders").add(orderData);
                    alert("🚫 لا يوجد اتصال. تم حفظ الطلب مؤقتًا.");
                }
            };
        };
    });



    function renderShippingMethodsFromIndexedDB() {
        const tx = db.transaction("shippingMethods", "readonly");
        const store = tx.objectStore("shippingMethods");
        const request = store.getAll();

        request.onsuccess = function() {
            const methods = request.result;
            const select = document.getElementById("shippingMethodSelect");
            if (!select) return;

            methods.forEach(method => {
                const option = document.createElement("option");
                option.value = method.id;
                option.textContent = `${method.title} - ${method.settings?.cost?.value ?? 0} ₪`;
                select.appendChild(option);
            });
        };
    }


    function renderShippingZonesFromIndexedDB() {
        const select = document.getElementById("shippingZoneSelect");
        if (!select) return;

        const tx = db.transaction("shippingZones", "readonly");
        const store = tx.objectStore("shippingZones");
        const request = store.getAll();

        request.onsuccess = function() {
            const zones = request.result;
            select.innerHTML = '<option value="">اختر منطقة الشحن</option>';

            zones.forEach(zone => {
                const option = document.createElement("option");
                option.value = zone.id;
                option.textContent = zone.name;
                select.appendChild(option);
            });
        };

        request.onerror = function() {
            console.error("❌ فشل في تحميل مناطق الشحن");
        };
    }

    document.getElementById("shippingZoneSelect").addEventListener("change", function() {
        const selectedZoneId = parseInt(this.value);

        const tx = db.transaction("shippingMethods", "readonly");
        const store = tx.objectStore("shippingMethods");
        const request = store.getAll();

        renderShippingMethodsForZone(selectedZoneId);

        request.onsuccess = function() {
            const methods = request.result.filter(method => method.zone_id === selectedZoneId);

            const shippingSelect = document.getElementById("shippingMethodSelect");
            shippingSelect.innerHTML = '<option value="">اختر طريقة الشحن</option>';

            methods.forEach(method => {
                const cost = method.settings?.cost?.value ?? 0;
                const label = `${method.title} (${cost} ₪)`;
                const option = document.createElement("option");
                option.value = method.id;
                option.textContent = label;
                shippingSelect.appendChild(option);
            });
        };

        request.onerror = function() {
            console.error("❌ فشل في تحميل طرق الشحن");
        };
    });

    function renderShippingMethodsForZone(zoneId) {
        const tx = db.transaction("shippingZoneMethods", "readonly");
        const store = tx.objectStore("shippingZoneMethods");
        const request = store.getAll();

        request.onsuccess = function() {
            const methods = request.result.filter(method => method.zone_id === zoneId);
            const select = document.getElementById("shippingMethodSelect");

            if (!select) return;

            select.innerHTML = '<option disabled selected>اختر طريقة الشحن</option>';

            methods.forEach(method => {
                const option = document.createElement("option");
                option.value = method.id;
                option.textContent = `${method.title} - ${method.cost} ₪`;
                select.appendChild(option);
            });
        };

        request.onerror = function() {
            console.error("❌ فشل في تحميل طرق الشحن من قاعدة البيانات.");
        };
    }

    function renderShippingZonesSelect() {
        const tx = db.transaction("shippingZones", "readonly");
        const store = tx.objectStore("shippingZones");
        const request = store.getAll();

        request.onsuccess = function() {
            const zones = request.result;
            const select = document.getElementById("shippingZoneSelect");

            if (!select) return;

            select.innerHTML = '<option disabled selected>اختر منطقة الشحن</option>';

            zones.forEach(zone => {
                const option = document.createElement("option");
                option.value = zone.id;
                option.textContent = zone.name;
                select.appendChild(option);
            });
        };
    }

    function renderShippingZonesWithMethods() {
        const container = document.getElementById("shippingZonesContainer");
        if (!container) return;

        const txZones = db.transaction("shippingZones", "readonly");
        const storeZones = txZones.objectStore("shippingZones");
        const zonesRequest = storeZones.getAll();

        zonesRequest.onsuccess = function() {
            const zones = zonesRequest.result;

            const txMethods = db.transaction("shippingZoneMethods", "readonly");
            const storeMethods = txMethods.objectStore("shippingZoneMethods");
            const methodsRequest = storeMethods.getAll();

            methodsRequest.onsuccess = function() {
                const methods = methodsRequest.result;

                container.innerHTML = ''; // تنظيف السابق

                zones.forEach(zone => {
                    // 🔹 قسم لكل منطقة
                    const zoneDiv = document.createElement("div");
                    zoneDiv.classList.add("border", "rounded", "p-4", "shadow");

                    const zoneTitle = document.createElement("h3");
                    zoneTitle.classList.add("font-bold", "mb-2", "text-gray-800");
                    zoneTitle.textContent = `📦 ${zone.name}`;
                    zoneDiv.appendChild(zoneTitle);

                    const zoneMethods = methods.filter(m => m.zone_id === zone.id);
                    if (zoneMethods.length === 0) {
                        const noMethods = document.createElement("p");
                        noMethods.textContent = "لا يوجد طرق شحن لهذه المنطقة.";
                        zoneDiv.appendChild(noMethods);
                    } else {
                        zoneMethods.forEach(method => {
                            const wrapper = document.createElement("div");
                            wrapper.classList.add("flex", "items-center", "gap-2", "mb-1");

                            const radio = document.createElement("input");
                            radio.type = "radio";
                            radio.name = "shippingMethod"; // يجب أن تكون موحدة للاختيار الواحد
                            radio.value = method.id;
                            radio.id = `method-${method.id}`;

                            const label = document.createElement("label");
                            label.setAttribute("for", radio.id);
                            label.classList.add("text-sm");
                            label.textContent = `${method.title} - ${method.cost} ₪`;

                            wrapper.appendChild(radio);
                            wrapper.appendChild(label);
                            zoneDiv.appendChild(wrapper);
                        });
                    }

                    container.appendChild(zoneDiv);
                });
            };
        };
    }

    document.getElementById("syncButton").addEventListener("click", function() {

        if (!db) return alert("قاعدة البيانات غير جاهزة");

        const storesToClear = [
            "products",
            "categories",
            "variations",
            "cart",
            "pendingOrders",
            "customers",
            "shippingMethods",
            "shippingZones",
            "shippingZoneMethods"
        ];

        const tx = db.transaction(storesToClear, "readwrite");

        storesToClear.forEach(storeName => {
            const store = tx.objectStore(storeName);
            store.clear();
        });

        tx.oncomplete = function() {
            console.log("✅ تم مسح كل البيانات من IndexedDB");

            // إعادة جلب البيانات من API عبر Livewire
            Livewire.dispatch('fetch-products-from-api');
            Livewire.dispatch('fetch-categories-from-api');
            Livewire.dispatch('fetch-variations-from-api');
            Livewire.dispatch('fetch-customers-from-api');
            Livewire.dispatch('fetch-shipping-methods-from-api');
            Livewire.dispatch('fetch-shipping-zones-and-methods');

            alert("✅ تمت المزامنة بنجاح!");
        };

        tx.onerror = function() {
            console.error("❌ فشل في مسح البيانات");
            alert("حدث خطأ أثناء المزامنة");
        };
    });
</script>

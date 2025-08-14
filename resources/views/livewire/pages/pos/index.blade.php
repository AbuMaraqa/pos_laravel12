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

            <div class="mt-4 p-4 bg-gray-50 rounded text-center space-y-1 text-sm font-semibold text-gray-700">
                <p id="subTotalDisplay">المجموع قبل التوصيل: 0 ₪</p>
                <p id="shippingCostDisplay">قيمة التوصيل: 0 ₪</p>
                <p id="finalTotalDisplay" style="font-size: 60px" class="text-lg font-bold text-black">0 ₪</p>
            </div>


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


            <flux:input id="orderNotes" label="ملاحظات إضافية" placeholder="اكتب أي ملاحظة (اختياري)"/>

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


    <flux:modal name="add-customer-modal">
        <div class="space-y-4">
            <h3 class="text-lg font-bold">إضافة زبون جديد</h3>

            <input id="newCustomerName" type="text" placeholder="اسم الزبون"
                   class="w-full border rounded px-3 py-2"/>

            <div class="flex justify-end gap-2">
                <flux:button variant="danger" onclick="Flux.modal('add-customer-modal').close()">إلغاء</flux:button>
                <flux:button variant="primary" onclick="addNewCustomer()">حفظ</flux:button>
            </div>
        </div>
    </flux:modal>

    <div class="grid gap-4 grid-cols-6">
        <div class="col-span-4">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <div class="flex items-center gap-2">
                    <flux:input id="searchInput" placeholder="Search" icon="magnifying-glass"/>
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
                    <flux:separator/>
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
    // ============================================
    // متغيرات عامة
    // ============================================
    window.onload = function () {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.focus();
    }

    let db;
    const dbName = "POSProductsDB";
    let selectedCategoryId = null;
    let currentSearchTerm = '';
    let cart = [];

    // ============================================
    // تهيئة قاعدة البيانات
    // ============================================
    document.addEventListener("livewire:navigated", () => {
        if (db) {
            // إذا كانت قاعدة البيانات مفتوحة مسبقاً
            initializeUI();
            return;
        }

        // فتح قاعدة البيانات إذا لم تكن مفتوحة
        const openRequest = indexedDB.open(dbName, 5);

        openRequest.onupgradeneeded = function (event) {
            db = event.target.result;
            createObjectStores(db);
        };

        openRequest.onsuccess = function (event) {
            db = event.target.result;
            initializeUI();
            setupEventListeners();
            checkAndFetchInitialData();
        };

        openRequest.onerror = function () {
            console.error("❌ Error opening IndexedDB");
        };
    });

    function createObjectStores(db) {
        const stores = [
            {name: "products", keyPath: "id"},
            {name: "categories", keyPath: "id"},
            {name: "variations", keyPath: "id", indexes: [{name: "product_id", unique: false}]},
            {name: "cart", keyPath: "id"},
            {name: "pendingOrders", autoIncrement: true},
            {name: "customers", keyPath: "id"},
            {name: "shippingMethods", keyPath: "id"},
            {name: "shippingZones", keyPath: "id"},
            {name: "shippingZoneMethods", keyPath: "id", indexes: [{name: "zone_id", unique: false}]}
        ];

        stores.forEach(store => {
            if (!db.objectStoreNames.contains(store.name)) {
                const objectStore = db.createObjectStore(store.name,
                    store.autoIncrement ? {autoIncrement: true} : {keyPath: store.keyPath}
                );

                if (store.indexes) {
                    store.indexes.forEach(index => {
                        objectStore.createIndex(index.name, index.name, {unique: index.unique});
                    });
                }
            }
        });
    }

    function initializeUI() {
        setTimeout(() => renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId), 300);
        renderCategoriesFromIndexedDB();
        renderCart();
    }

    function setupEventListeners() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            // إزالة المستمعات القديمة أولاً
            searchInput.removeEventListener('input', handleSearchInput);
            searchInput.removeEventListener('keydown', handleEnterKeySearch);

            // إضافة المستمعات الجديدة
            searchInput.addEventListener('input', handleSearchInput);
            searchInput.addEventListener('keydown', handleEnterKeySearch);
        }

        // إعداد أزرار أخرى
        setupSyncButton();
        setupOrderButton();
        setupConfirmOrderButton();
    }

    // ============================================
    // دوال معالجة البحث
    // ============================================
    function handleSearchInput(event) {
        currentSearchTerm = event.target.value;
        renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
    }

    function handleEnterKeySearch(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const searchTerm = e.target.value.trim().toLowerCase();

            if (!searchTerm) {
                showNotification("يرجى إدخال اسم المنتج أو الباركود", 'warning');
                return;
            }

            searchProductInIndexedDB(searchTerm);
        }
    }

    function searchProductInIndexedDB(searchTerm) {
        if (!db) {
            console.error('Database not initialized');
            return;
        }

        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.getAll();

        request.onsuccess = function () {
            const products = request.result;

            // البحث في المنتجات الموجودة
            const matched = products.find(item => {
                const nameMatch = item.name?.toLowerCase().includes(searchTerm);
                const barcodeMatch = item.id?.toString() === searchTerm;
                const skuMatch = item.sku?.toLowerCase() === searchTerm;
                return nameMatch || barcodeMatch || skuMatch;
            });

            if (matched) {
                // المنتج موجود في IndexedDB
                console.log("✅ تم العثور على المنتج في IndexedDB:", matched);
                handleFoundProduct(matched);
                clearSearchInput();
            } else {
                // المنتج غير موجود، البحث في API
                console.log('🔍 لم يتم العثور على المنتج في IndexedDB، جاري البحث في API...');
                searchProductFromAPI(searchTerm);
            }
        };

        request.onerror = function () {
            console.error('Error searching in IndexedDB');
            showNotification("حدث خطأ في البحث", 'error');
        };
    }

    function searchProductFromAPI(searchTerm) {
        console.log('🌐 إرسال طلب البحث إلى API:', searchTerm);

        // إظهار مؤشر التحميل
        showLoadingIndicator(true);

        // إرسال طلب البحث إلى Livewire
        Livewire.dispatch('search-product-from-api', {searchTerm: searchTerm});
    }

    function handleFoundProduct(product) {
        if (product.type === 'simple') {
            addToCart(product);
        } else if (product.type === 'variable') {
            fetchVariationsAndShowModal(product);
        }
    }

    function fetchVariationsAndShowModal(product) {
        if (!product.variations || product.variations.length === 0) {
            showNotification("لا توجد متغيرات لهذا المنتج", 'warning');
            return;
        }

        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const variationProducts = [];
        let fetched = 0;

        product.variations.forEach(id => {
            const req = store.get(id);
            req.onsuccess = function () {
                if (req.result) {
                    variationProducts.push(req.result);
                }
                fetched++;
                if (fetched === product.variations.length) {
                    showVariationsModal(variationProducts);
                }
            };
        });
    }

    function clearSearchInput() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.value = '';
            currentSearchTerm = '';
        }
    }

    // ============================================
    // دوال عرض المنتجات والفئات
    // ============================================
    function renderProductsFromIndexedDB(searchTerm = '', categoryId = null) {
        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.getAll();

        request.onsuccess = function () {
            const products = request.result;
            const container = document.getElementById("productsContainer");
            if (!container) return;

            container.innerHTML = '';

            const filtered = products.filter(item => {
                const term = searchTerm.trim().toLowerCase();
                const isAllowedType = item.type === 'simple' || item.type === 'variable';
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

                div.onclick = function () {
                    if (item.type === 'variable' && Array.isArray(item.variations)) {
                        fetchVariationsAndShowModal(item);
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

        request.onerror = function () {
            console.error("❌ Failed to fetch products from IndexedDB");
        };
    }

    function renderCategoriesFromIndexedDB() {
        const tx = db.transaction("categories", "readonly");
        const store = tx.objectStore("categories");
        const request = store.getAll();

        request.onsuccess = function () {
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

    // ============================================
    // دوال السلة
    // ============================================
    function addToCart(product) {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const getRequest = store.get(product.id);

        getRequest.onsuccess = function () {
            const existing = getRequest.result;

            if (existing) {
                existing.quantity += 1;
                store.put(existing);
            } else {
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

            // تمرير سلس إلى المنتج الجديد في السلة
            setTimeout(() => {
                const container = document.getElementById("cartItemsContainer");
                if (container) {
                    container.scrollTo({
                        top: container.scrollHeight,
                        behavior: 'smooth'
                    });
                }
            }, 50);
        };

        getRequest.onerror = function () {
            console.error("❌ فشل في جلب بيانات المنتج من السلة.");
        };
    }

    function renderCart(highlightId = null) {
        const tx = db.transaction("cart", "readonly");
        const store = tx.objectStore("cart");
        const request = store.getAll();

        request.onsuccess = function () {
            const cartItems = request.result;
            const container = document.getElementById("cartItemsContainer");
            const totalElement = document.getElementById("cartTotal");
            if (!container || !totalElement) return;

            if (cartItems.length === 0) {
                container.innerHTML = `
                    <div class="flex flex-col items-center justify-center text-center text-gray-500 py-8 space-y-2">
                        <div class="text-4xl">🛒</div>
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
                div.className = "flex justify-between items-center bg-gray-100 p-2 rounded transition duration-300";

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
                    <div class="font-bold text-gray-800 flex items-center gap-2">
                        <span>${(item.price * item.quantity).toFixed(2)} ₪</span>
                        <button onclick="removeFromCart(${item.id})" class="text-red-500 hover:text-red-700">🗑️</button>
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

        request.onerror = function () {
            console.error("❌ فشل في تحميل محتوى السلة.");
        };
    }

    function removeFromCart(productId) {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const request = store.delete(productId);

        request.onsuccess = function () {
            console.log("🗑️ تم حذف المنتج من السلة");
            renderCart();
        };

        request.onerror = function () {
            console.error("❌ فشل في حذف المنتج من السلة");
        };
    }

    function clearCart() {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const clearRequest = store.clear();

        clearRequest.onsuccess = function () {
            console.log("🧹 تم حذف جميع المنتجات من السلة");
            renderCart();
        };

        clearRequest.onerror = function () {
            console.error("❌ فشل في حذف السلة");
        };
    }

    function updateQuantity(productId, change) {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const getRequest = store.get(productId);

        getRequest.onsuccess = function () {
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

        getRequest.onerror = function () {
            console.error("❌ فشل في تحديث كمية المنتج");
        };
    }

    // ============================================
    // دوال المتغيرات
    // ============================================
    function showVariationsModal(variations) {
        const modal = Flux.modal('variations-modal');
        const container = document.getElementById("variationsTableBody");
        if (!container) return;

        container.innerHTML = '';

        if (!variations || variations.length === 0) {
            const message = document.createElement("div");
            message.className = "text-center text-gray-500 py-8";
            message.innerHTML = `
            <div class="text-4xl mb-4">📦</div>
            <p class="text-lg font-semibold">لا يوجد متغيرات متاحة</p>
            <p class="text-sm">هذا المنتج لا يحتوي على متغيرات للعرض</p>
        `;
            container.appendChild(message);
            modal.show();
            return;
        }

        // إنشاء عنوان للمودال
        const header = document.createElement("div");
        header.className = "text-center mb-4 p-4 bg-blue-50 rounded-lg";
        header.innerHTML = `
        <h3 class="text-lg font-bold text-blue-800">اختر من المتغيرات المتاحة</h3>
        <p class="text-sm text-blue-600">عدد المتغيرات: ${variations.length}</p>
    `;
        container.appendChild(header);

        const grid = document.createElement("div");
        grid.className = "grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4";

        variations.forEach(variation => {
            const card = document.createElement("div");
            card.className = "relative bg-white rounded-lg shadow-md overflow-hidden cursor-pointer hover:shadow-xl transition-all border border-gray-200 hover:border-blue-300";

            // إضافة تأثير hover
            card.onmouseenter = () => card.classList.add('transform', 'scale-105');
            card.onmouseleave = () => card.classList.remove('transform', 'scale-105');

            card.onclick = () => {
                addVariationToCart(variation.id);
                showNotification(`تم إضافة "${variation.name}" للسلة`, 'success');
            };

            // تحضير معلومات الخصائص
            let attributesText = '';
            if (variation.attributes && variation.attributes.length > 0) {
                const attrs = variation.attributes.map(attr => attr.option || attr.value).filter(Boolean);
                attributesText = attrs.length > 0 ? attrs.join(' • ') : '';
            }

            // تحضير معلومات المخزون
            let stockInfo = '';
            let stockClass = 'bg-green-500';
            if (variation.stock_status === 'outofstock') {
                stockInfo = 'نفدت الكمية';
                stockClass = 'bg-red-500';
            } else if (variation.stock_quantity !== undefined && variation.stock_quantity !== null) {
                stockInfo = `متوفر: ${variation.stock_quantity}`;
                stockClass = variation.stock_quantity > 10 ? 'bg-green-500' : 'bg-yellow-500';
            } else {
                stockInfo = 'متوفر';
            }

            card.innerHTML = `
            <!-- ID Badge -->
            <div class="absolute top-2 left-2 bg-black text-white text-xs px-2 py-1 rounded z-10 opacity-75">
                #${variation.id}
            </div>

            <!-- Stock Badge -->
            <div class="absolute top-2 right-2 ${stockClass} text-white text-xs px-2 py-1 rounded z-10">
                ${stockInfo}
            </div>

            <!-- Product Image -->
            <div class="relative h-48 bg-gray-100">
                <img src="${variation.images?.[0]?.src || '/images/no-image.png'}"
                     alt="${variation.name || ''}"
                     class="w-full h-full object-cover">

                <!-- Price Badge -->
                <div class="absolute bottom-2 left-2 bg-blue-600 text-white px-3 py-1 rounded-full font-bold text-sm">
                    ${variation.price || 0} ₪
                </div>
            </div>

            <!-- Product Info -->
            <div class="p-3 space-y-2">
                <h4 class="font-semibold text-sm text-gray-800 line-clamp-2">
                    ${variation.name || 'متغير'}
                </h4>

                ${attributesText ? `
                    <div class="text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded">
                        ${attributesText}
                    </div>
                ` : ''}

                ${variation.sku ? `
                    <div class="text-xs text-gray-500">
                        SKU: ${variation.sku}
                    </div>
                ` : ''}

                <!-- Add Button -->
                <button class="w-full mt-2 bg-green-500 hover:bg-green-600 text-white py-2 px-3 rounded-md text-sm font-semibold transition-colors">
                    إضافة للسلة
                </button>
            </div>
        `;

            // تعطيل الكارد إذا كان المنتج غير متوفر
            if (variation.stock_status === 'outofstock') {
                card.classList.add('opacity-60', 'cursor-not-allowed');
                card.onclick = () => {
                    showNotification('هذا المتغير غير متوفر حالياً', 'warning');
                };
            }

            grid.appendChild(card);
        });

        container.appendChild(grid);

        // إضافة footer للمودال
        const footer = document.createElement("div");
        footer.className = "text-center mt-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-600";
        footer.textContent = "اضغط على أي متغير لإضافته إلى السلة";
        container.appendChild(footer);

        modal.show();
    }

    function addVariationToCart(variationId) {
        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.get(variationId);

        request.onsuccess = function () {
            const variation = request.result;

            if (!variation || !variation.id) {
                console.error("❌ Variation not found:", variationId);
                showNotification("لم يتم العثور على هذا المتغير", 'error');
                return;
            }

            // فحص المخزون
            if (variation.stock_status === 'outofstock') {
                showNotification("هذا المتغير غير متوفر حالياً", 'warning');
                return;
            }

            const cartTx = db.transaction("cart", "readwrite");
            const cartStore = cartTx.objectStore("cart");
            const getCartItem = cartStore.get(variation.id);

            getCartItem.onsuccess = function () {
                const existing = getCartItem.result;

                if (existing) {
                    existing.quantity += 1;
                    cartStore.put(existing);
                    showNotification(`تم زيادة كمية "${variation.name}"`, 'success');
                } else {
                    cartStore.put({
                        id: variation.id,
                        name: variation.name,
                        price: variation.price || 0,
                        quantity: 1,
                        image: variation.images?.[0]?.src || '/images/no-image.png',
                        sku: variation.sku || '',
                        type: 'variation'
                    });
                    showNotification(`تم إضافة "${variation.name}" للسلة`, 'success');
                }

                renderCart();
                Flux.modal('variations-modal').close();
            };
        };

        request.onerror = function () {
            console.error("❌ Failed to fetch variation:", variationId);
            showNotification("حدث خطأ أثناء إضافة المتغير", 'error');
        };
    }

    // ============================================
    // دوال الطلبات والعملاء
    // ============================================
    function setupOrderButton() {
        document.getElementById('completeOrderBtn').addEventListener('click', function () {
            const dropdown = document.getElementById("customerSelect");
            if (dropdown) {
                dropdown.innerHTML = '<option value="">جاري التحميل...</option>';
            }

            renderCustomersDropdown();
            renderShippingZonesWithMethods();
            setTimeout(() => {
                updateOrderTotalInModal();
            }, 300);

            Flux.modal('confirm-order-modal').show();
        });
    }

    function setupConfirmOrderButton() {
        // انتظار قليل للتأكد من تحميل العناصر
        setTimeout(attachConfirmOrderListener, 500);

        // كمان نحطه في livewire:navigated للتأكد
        document.addEventListener("livewire:navigated", function () {
            setTimeout(attachConfirmOrderListener, 500);
        });
    }

    function attachConfirmOrderListener() {
        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');

        if (confirmBtn) {
            // إزالة أي listener قديم لتجنب التكرار
            confirmBtn.removeEventListener('click', handleOrderSubmit);

            // إضافة listener جديد
            confirmBtn.addEventListener('click', handleOrderSubmit);

            console.log("✅ تم ربط زر تأكيد الطلب بنجاح");
        } else {
            console.warn("⚠️ لم يتم العثور على زر تأكيد الطلب");

            // محاولة أخرى بعد ثانية
            setTimeout(attachConfirmOrderListener, 1000);
        }
    }

    function handleOrderSubmit(e) {
        e.preventDefault();

        // جلب زر التأكيد من الصفحة
        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');

        console.log("🔄 بدء عملية إرسال الطلب...");

        // جلب بيانات النموذج
        const customerId = document.getElementById("customerSelect")?.value;
        const notes = document.getElementById("orderNotes")?.value || '';
        const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');

        // التحقق من البيانات المطلوبة
        if (!customerId) {
            showNotification("يرجى اختيار العميل", 'warning');
            return;
        }

        if (!selectedMethod) {
            showNotification("يرجى اختيار طريقة الشحن", 'warning');
            return;
        }

        // إظهار مؤشر التحميل
        showLoadingIndicator(true);

        // تعطيل الزر وتغيير النص
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = "جاري الإرسال...";
        }

        // جلب تفاصيل طريقة الشحن
        const shippingMethodId = parseInt(selectedMethod.value);

        const txMethods = db.transaction("shippingZoneMethods", "readonly");
        const storeMethods = txMethods.objectStore("shippingZoneMethods");
        const methodRequest = storeMethods.get(shippingMethodId);

        methodRequest.onsuccess = function () {
            const shippingMethod = methodRequest.result;

            if (!shippingMethod) {
                showNotification("خطأ في بيانات طريقة الشحن", 'error');
                resetOrderButton();
                return;
            }

            // جلب عناصر السلة
            const cartTx = db.transaction("cart", "readonly");
            const cartStore = cartTx.objectStore("cart");
            const cartRequest = cartStore.getAll();

            cartRequest.onsuccess = function () {
                const cartItems = cartRequest.result;

                if (cartItems.length === 0) {
                    showNotification("السلة فارغة! يرجى إضافة منتجات أولاً", 'warning');
                    resetOrderButton();
                    return;
                }

                // تحضير بيانات الطلب
                const orderData = {
                    customer_id: parseInt(customerId),
                    payment_method: 'cod',
                    payment_method_title: 'الدفع عند الاستلام',
                    set_paid: false,
                    status: 'processing',
                    customer_note: notes,
                    shipping_lines: [{
                        method_id: shippingMethod.id.toString(),
                        method_title: shippingMethod.title,
                        total: shippingMethod.cost.toString()
                    }],
                    line_items: cartItems.map(item => ({
                        product_id: item.id,
                        quantity: item.quantity,
                        name: item.name,
                        price: item.price
                    })),
                    meta_data: [
                        {
                            key: '_pos_order',
                            value: 'true'
                        },
                        {
                            key: '_order_source',
                            value: 'POS System'
                        }
                    ]
                };

                console.log("📤 إرسال بيانات الطلب:", orderData);

                // التحقق من صحة البيانات
                const validationErrors = validateOrderData(orderData);
                if (validationErrors.length > 0) {
                    showNotification("خطأ في البيانات: " + validationErrors.join(', '), 'error');
                    resetOrderButton();
                    return;
                }

                // عرض ملخص الطلب في الكونسول
                showOrderSummary(orderData);

                // إرسال الطلب
                if (navigator.onLine) {
                    // إرسال عبر Livewire
                    Livewire.dispatch('submit-order', {
                        order: orderData
                    });

                    console.log("✅ تم إرسال الطلب إلى الخادم");
                } else {
                    // حفظ محلياً إذا لم يكن هناك اتصال
                    savePendingOrder(orderData);
                }
            };

            cartRequest.onerror = function () {
                showNotification("خطأ في قراءة السلة", 'error');
                resetOrderButton();
            };
        };

        methodRequest.onerror = function () {
            showNotification("خطأ في جلب بيانات طريقة الشحن", 'error');
            resetOrderButton();
        };
    }

    function resetOrderButton() {
        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = "تأكيد الطلب";
        }
    }


    function setupConfirmButton() {
        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');

        if (confirmBtn) {
            console.log("✅ تم العثور على زر تأكيد الطلب");

            // إزالة listeners قديمة
            confirmBtn.removeEventListener('click', debugOrderSubmit);

            // إضافة listener مع debugging
            confirmBtn.addEventListener('click', debugOrderSubmit);

        } else {
            console.warn("❌ زر التأكيد غير موجود، محاولة أخرى...");
            setTimeout(setupConfirmButton, 1000);
        }
    }

    // 2. دالة debugging للطلب
    function debugOrderSubmit(e) {
        e.preventDefault();

        console.log("🚀 === بدء تشخيص تأكيد الطلب ===");
        console.log("🕐 الوقت:", new Date().toLocaleString());

        try {
            // فحص 1: البيانات الأساسية
            console.log("📋 فحص 1: جلب البيانات من النموذج...");

            const customerSelect = document.getElementById("customerSelect");
            const notesInput = document.getElementById("orderNotes");
            const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');

            console.log("👤 عنصر اختيار العميل:", customerSelect ? "موجود" : "غير موجود");
            console.log("📝 عنصر الملاحظات:", notesInput ? "موجود" : "غير موجود");
            console.log("🚚 طريقة الشحن المختارة:", selectedMethod ? "موجود" : "غير موجود");

            if (!customerSelect) {
                console.error("❌ لم يتم العثور على عنصر اختيار العميل");
                alert("خطأ: لم يتم العثور على قائمة العملاء");
                return;
            }

            const customerId = customerSelect.value;
            const notes = notesInput ? notesInput.value || '' : '';

            console.log("👤 معرف العميل:", customerId);
            console.log("📝 الملاحظات:", notes);

            // فحص 2: التحقق من البيانات المطلوبة
            console.log("✅ فحص 2: التحقق من البيانات المطلوبة...");

            if (!customerId) {
                console.error("❌ لم يتم اختيار عميل");
                alert("يرجى اختيار عميل من القائمة");
                customerSelect.focus();
                return;
            }

            if (!selectedMethod) {
                console.error("❌ لم يتم اختيار طريقة شحن");
                alert("يرجى اختيار طريقة شحن");
                return;
            }

            console.log("🚚 معرف طريقة الشحن:", selectedMethod.value);
            console.log("🚚 عنوان طريقة الشحن:", selectedMethod.getAttribute('data-title') || 'غير محدد');
            console.log("🚚 تكلفة الشحن:", selectedMethod.getAttribute('data-cost') || '0');

            // فحص 3: قاعدة البيانات
            console.log("🗃️ فحص 3: التحقق من قاعدة البيانات...");

            if (!db) {
                console.error("❌ قاعدة البيانات غير متاحة");
                alert("خطأ: قاعدة البيانات غير متاحة. يرجى إعادة تحميل الصفحة");
                return;
            }

            console.log("✅ قاعدة البيانات متاحة");

            // فحص 4: السلة
            console.log("🛒 فحص 4: التحقق من السلة...");

            const cartTx = db.transaction("cart", "readonly");
            const cartStore = cartTx.objectStore("cart");
            const cartRequest = cartStore.getAll();

            cartRequest.onsuccess = function() {
                const cartItems = cartRequest.result;

                console.log("🛒 عدد عناصر السلة:", cartItems.length);
                console.log("🛒 تفاصيل السلة:", cartItems);

                if (cartItems.length === 0) {
                    console.error("❌ السلة فارغة");
                    alert("السلة فارغة! يرجى إضافة منتجات أولاً");
                    return;
                }

                // فحص عناصر السلة
                let hasInvalidItems = false;
                cartItems.forEach((item, index) => {
                    console.log(`📦 منتج ${index + 1}:`, {
                        id: item.id,
                        name: item.name,
                        price: item.price,
                        quantity: item.quantity
                    });

                    if (!item.id || !item.quantity || item.quantity <= 0) {
                        console.error(`❌ منتج ${index + 1} غير صالح:`, item);
                        hasInvalidItems = true;
                    }
                });

                if (hasInvalidItems) {
                    alert("يوجد منتجات غير صالحة في السلة. يرجى إعادة تحميل الصفحة");
                    return;
                }

                // فحص 5: طريقة الشحن من قاعدة البيانات
                console.log("🚚 فحص 5: جلب تفاصيل طريقة الشحن من قاعدة البيانات...");

                const shippingMethodId = parseInt(selectedMethod.value);
                const txMethods = db.transaction("shippingZoneMethods", "readonly");
                const storeMethods = txMethods.objectStore("shippingZoneMethods");
                const methodRequest = storeMethods.get(shippingMethodId);

                methodRequest.onsuccess = function() {
                    const shippingMethod = methodRequest.result;

                    console.log("🚚 تفاصيل طريقة الشحن:", shippingMethod);

                    if (!shippingMethod) {
                        console.error("❌ لم يتم العثور على تفاصيل طريقة الشحن");
                        alert("خطأ: لم يتم العثور على تفاصيل طريقة الشحن");
                        return;
                    }

                    // فحص 6: تحضير بيانات الطلب
                    console.log("📤 فحص 6: تحضير بيانات الطلب...");

                    const orderData = {
                        customer_id: parseInt(customerId),
                        payment_method: 'cod',
                        payment_method_title: 'الدفع عند الاستلام',
                        set_paid: false,
                        status: 'processing',
                        customer_note: notes,
                        shipping_lines: [{
                            method_id: shippingMethod.id.toString(),
                            method_title: shippingMethod.title,
                            total: shippingMethod.cost.toString()
                        }],
                        line_items: cartItems.map(item => ({
                            product_id: item.id,
                            quantity: item.quantity,
                            name: item.name,
                            price: item.price
                        })),
                        meta_data: [
                            {
                                key: '_pos_order',
                                value: 'true'
                            },
                            {
                                key: '_order_source',
                                value: 'POS System'
                            }
                        ]
                    };

                    console.log("📤 بيانات الطلب النهائية:", orderData);

                    // فحص 7: التحقق من Livewire
                    console.log("🔌 فحص 7: التحقق من Livewire...");

                    if (typeof Livewire === 'undefined') {
                        console.error("❌ Livewire غير متاح");
                        alert("خطأ: Livewire غير متاح. يرجى إعادة تحميل الصفحة");
                        return;
                    }

                    console.log("✅ Livewire متاح");

                    // فحص 8: الاتصال بالإنترنت
                    console.log("🌐 فحص 8: التحقق من الاتصال...");

                    if (!navigator.onLine) {
                        console.warn("⚠️ لا يوجد اتصال بالإنترنت");
                        alert("لا يوجد اتصال بالإنترنت. سيتم حفظ الطلب محلياً");
                        return;
                    }

                    console.log("✅ الاتصال متاح");

                    // تعطيل الزر وإظهار التحميل
                    const confirmBtn = document.getElementById('confirmOrderSubmitBtn');
                    if (confirmBtn) {
                        confirmBtn.disabled = true;
                        confirmBtn.textContent = "جاري الإرسال...";
                        console.log("🔒 تم تعطيل زر التأكيد");
                    }

                    // إرسال الطلب
                    console.log("📡 إرسال الطلب إلى الخادم...");
                    console.log("🕐 وقت الإرسال:", new Date().toLocaleString());

                    try {
                        Livewire.dispatch('submit-order', {
                            order: orderData
                        });

                        console.log("✅ تم إرسال الطلب بنجاح إلى Livewire");

                        // إضافة timeout للتحقق من الاستجابة
                        setTimeout(function() {
                            const btn = document.getElementById('confirmOrderSubmitBtn');
                            if (btn && btn.disabled) {
                                console.warn("⏰ لم تصل استجابة خلال 15 ثانية");
                                console.log("🔍 فحص آخر رسائل الكونسول لمعرفة السبب");
                            }
                        }, 15000);

                    } catch (livewireError) {
                        console.error("❌ خطأ في إرسال Livewire:", livewireError);
                        alert("خطأ في إرسال الطلب: " + livewireError.message);
                        resetOrderButton();
                    }
                };

                methodRequest.onerror = function() {
                    console.error("❌ خطأ في جلب تفاصيل طريقة الشحن من قاعدة البيانات");
                    alert("خطأ في جلب تفاصيل طريقة الشحن");
                };
            };

            cartRequest.onerror = function() {
                console.error("❌ خطأ في قراءة السلة من قاعدة البيانات");
                alert("خطأ في قراءة السلة");
            };

        } catch (generalError) {
            console.error("❌ خطأ عام في معالجة الطلب:", generalError);
            alert("حدث خطأ غير متوقع: " + generalError.message);
        }
    }

    function savePendingOrder(orderData) {
        const tx = db.transaction("pendingOrders", "readwrite");
        const store = tx.objectStore("pendingOrders");

        const pendingOrder = {
            ...orderData,
            created_at: new Date().toISOString(),
            status: 'pending_sync'
        };

        const request = store.add(pendingOrder);

        request.onsuccess = function () {
            showNotification("🚫 لا يوجد اتصال. تم حفظ الطلب محلياً وسيتم إرساله عند الاتصال", 'warning');

            // مسح السلة حتى لو لم يتم الإرسال
            clearCartAfterOrder();
            resetOrderButton();

            // إغلاق المودال
            const modal = Flux.modal('confirm-order-modal');
            if (modal) {
                modal.close();
            }
        };

        request.onerror = function () {
            showNotification("خطأ في حفظ الطلب محلياً", 'error');
            resetOrderButton();
        };
    }

    function clearCartAfterOrder() {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");

        store.clear().onsuccess = function () {
            console.log("🧹 تم مسح السلة بعد الطلب");
            renderCart();
        };
    }

    document.addEventListener('livewire:init', function() {
        console.log("🔌 Livewire تم تهيئته");

        // نجاح الطلب
        Livewire.on('order-success', function(data) {
            console.log("🎉 === نجح إرسال الطلب ===");
            console.log("📊 بيانات النجاح:", data);
            console.log("🕐 وقت النجاح:", new Date().toLocaleString());

            resetOrderButton();

            // مسح السلة
            if (db) {
                const tx = db.transaction("cart", "readwrite");
                tx.objectStore("cart").clear();
                tx.oncomplete = function() {
                    console.log("🧹 تم مسح السلة");
                    renderCart();
                };
            }

            // إغلاق المودال
            try {
                Flux.modal('confirm-order-modal').close();
                console.log("🔒 تم إغلاق المودال");
            } catch (e) {
                console.log("⚠️ المودال مُغلق مسبقاً");
            }

            alert("✅ تم إرسال الطلب بنجاح!");
        });

        // فشل الطلب
        Livewire.on('order-failed', function(data) {
            console.log("❌ === فشل إرسال الطلب ===");
            console.log("📊 بيانات الفشل:", data);
            console.log("🕐 وقت الفشل:", new Date().toLocaleString());

            resetOrderButton();

            let errorMessage = "فشل في إرسال الطلب";

            if (data && Array.isArray(data) && data[0]) {
                errorMessage = data[0].message || data[0].detailed_error || errorMessage;
            } else if (data && data.message) {
                errorMessage = data.message;
            }

            console.error("📄 رسالة الخطأ التفصيلية:", errorMessage);
            alert("❌ " + errorMessage);
        });
    });

    setupConfirmOrderButton();

    function validateOrderData(orderData) {
        const errors = [];

        if (!orderData.customer_id) {
            errors.push("معرف العميل مطلوب");
        }

        if (!orderData.line_items || orderData.line_items.length === 0) {
            errors.push("يجب إضافة منتجات للطلب");
        }

        if (!orderData.shipping_lines || orderData.shipping_lines.length === 0) {
            errors.push("يجب اختيار طريقة شحن");
        }

        // فحص المنتجات
        orderData.line_items?.forEach((item, index) => {
            if (!item.product_id) {
                errors.push(`المنتج رقم ${index + 1} لا يحتوي على معرف`);
            }
            if (!item.quantity || item.quantity <= 0) {
                errors.push(`كمية المنتج رقم ${index + 1} غير صالحة`);
            }
        });

        return errors;
    }

    function showOrderSummary(orderData) {
        const totalItems = orderData.line_items.reduce((sum, item) => sum + item.quantity, 0);
        const subtotal = orderData.line_items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
        const shippingCost = parseFloat(orderData.shipping_lines[0]?.total || 0);
        const total = subtotal + shippingCost;

        console.log("📋 ملخص الطلب:", {
            عدد_المنتجات: totalItems,
            المجموع_الفرعي: subtotal + " ₪",
            تكلفة_الشحن: shippingCost + " ₪",
            المجموع_النهائي: total + " ₪",
            العميل: orderData.customer_id,
            طريقة_الدفع: orderData.payment_method_title
        });
    }

    function submitOrder() {
        const customerId = document.getElementById("customerSelect").value;
        const notes = document.getElementById("orderNotes").value;
        const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');

        if (!customerId || !selectedMethod) {
            showNotification("يرجى اختيار العميل وطريقة الشحن", 'warning');
            return;
        }

        const shippingMethodId = selectedMethod.value;

        const txMethods = db.transaction("shippingZoneMethods", "readonly");
        const storeMethods = txMethods.objectStore("shippingZoneMethods");
        const methodRequest = storeMethods.get(parseInt(shippingMethodId));

        methodRequest.onsuccess = function () {
            const method = methodRequest.result;

            const tx = db.transaction("cart", "readonly");
            const store = tx.objectStore("cart");
            const request = store.getAll();

            request.onsuccess = function () {
                const cartItems = request.result;
                if (cartItems.length === 0) {
                    showNotification("السلة فارغة", 'warning');
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
                } else {
                    const tx2 = db.transaction("pendingOrders", "readwrite");
                    tx2.objectStore("pendingOrders").add(orderData);
                    showNotification("🚫 لا يوجد اتصال. تم حفظ الطلب مؤقتاً.", 'warning');
                }
            };
        };
    }

    function loadCustomersForModal() {
        console.log("👥 تحميل العملاء...");

        if (!db) {
            console.error("❌ قاعدة البيانات غير متاحة");
            return;
        }

        const tx = db.transaction("customers", "readonly");
        const store = tx.objectStore("customers");
        const request = store.getAll();

        request.onsuccess = function() {
            const customers = request.result;
            const dropdown = document.getElementById("customerSelect");

            if (!dropdown) {
                console.error("❌ عنصر اختيار العميل غير موجود");
                return;
            }

            // مسح الخيارات الموجودة
            dropdown.innerHTML = '';

            // إضافة خيار افتراضي
            const defaultOption = document.createElement("option");
            defaultOption.value = "";
            defaultOption.textContent = "اختر عميل";
            dropdown.appendChild(defaultOption);

            // فحص وجود عملاء
            if (customers.length === 0) {
                console.warn("⚠️ لا يوجد عملاء في قاعدة البيانات");

                // إضافة عميل افتراضي
                const guestOption = document.createElement("option");
                guestOption.value = "guest";
                guestOption.textContent = "عميل ضيف";
                dropdown.appendChild(guestOption);

                return;
            }

            // إضافة العملاء الموجودين
            let validCustomersCount = 0;
            customers.forEach(customer => {
                if (customer.id && customer.name) {
                    const option = document.createElement("option");
                    option.value = customer.id;
                    option.textContent = customer.name;
                    dropdown.appendChild(option);
                    validCustomersCount++;
                } else {
                    console.warn("⚠️ عميل غير صالح:", customer);
                }
            });

            // إضافة خيار عميل ضيف
            const guestOption = document.createElement("option");
            guestOption.value = "guest";
            guestOption.textContent = "عميل ضيف";
            dropdown.appendChild(guestOption);

            // إضافة خيار إنشاء عميل جديد
            const addOption = document.createElement("option");
            addOption.value = "add_new_customer";
            addOption.textContent = "+ إضافة عميل جديد";
            dropdown.appendChild(addOption);

            console.log(`✅ تم تحميل ${validCustomersCount} عميل + خيارات إضافية`);

            // إضافة معالج للتغيير
            dropdown.addEventListener('change', function() {
                if (this.value === "add_new_customer") {
                    this.value = "";
                    showAddCustomerModal();
                }
            });
        };

        request.onerror = function() {
            console.error("❌ خطأ في جلب العملاء من قاعدة البيانات");

            // إضافة عميل ضيف كبديل
            const dropdown = document.getElementById("customerSelect");
            if (dropdown) {
                dropdown.innerHTML = `
                <option value="">اختر عميل</option>
                <option value="guest">عميل ضيف</option>
            `;
            }
        };
    }

    function showAddCustomerModal() {
        try {
            const modal = Flux.modal('add-customer-modal');
            if (modal) {
                modal.show();
            }
        } catch (e) {
            console.error("خطأ في فتح مودال إضافة العميل:", e);
        }
    }

    function handleConfirmOrder(e) {
        e.preventDefault();
        console.log("✅ تم الضغط على تأكيد الطلب");

        try {
            // جلب البيانات
            const customerSelect = document.getElementById("customerSelect");
            const notesInput = document.getElementById("orderNotes");
            const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');

            if (!customerSelect) {
                alert("خطأ: لم يتم العثور على قائمة العملاء");
                return;
            }

            const selectedCustomer = customerSelect.value;
            const notes = notesInput ? notesInput.value || '' : '';

            console.log("👤 العميل المختار:", selectedCustomer);

            // التحقق من اختيار العميل
            if (!selectedCustomer) {
                alert("يرجى اختيار عميل أو 'عميل ضيف'");
                customerSelect.focus();
                return;
            }

            // التحقق من طريقة الشحن
            if (!selectedMethod) {
                alert("يرجى اختيار طريقة شحن");
                return;
            }

            // تحضير معرف العميل
            let customerId = null;

            if (selectedCustomer === "guest") {
                console.log("🎭 إنشاء طلب كعميل ضيف");
                customerId = null; // سيتم التعامل معه في PHP
            } else {
                customerId = parseInt(selectedCustomer);

                if (isNaN(customerId) || customerId <= 0) {
                    console.error("❌ معرف عميل غير صالح:", selectedCustomer);
                    alert("معرف العميل غير صالح. سيتم إنشاء الطلب كعميل ضيف.");
                    customerId = null;
                }
            }

            console.log("👤 معرف العميل النهائي:", customerId);

            // معالجة باقي الطلب
            processOrderWithCustomer(customerId, notes, selectedMethod);

        } catch (error) {
            console.error("❌ خطأ في معالجة تأكيد الطلب:", error);
            alert("حدث خطأ: " + error.message);
        }
    }

    function processOrderWithCustomer(customerId, notes, selectedMethod) {
        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');

        // تعطيل الزر
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = "جاري الإرسال...";
        }

        // جلب تفاصيل الشحن
        const shippingMethodId = parseInt(selectedMethod.value);
        const txMethods = db.transaction("shippingZoneMethods", "readonly");
        const storeMethods = txMethods.objectStore("shippingZoneMethods");
        const methodRequest = storeMethods.get(shippingMethodId);

        methodRequest.onsuccess = function() {
            const shippingMethod = methodRequest.result;

            if (!shippingMethod) {
                alert("خطأ في تفاصيل طريقة الشحن");
                resetOrderButton();
                return;
            }

            // جلب السلة
            const cartTx = db.transaction("cart", "readonly");
            const cartStore = cartTx.objectStore("cart");
            const cartRequest = cartStore.getAll();

            cartRequest.onsuccess = function() {
                const cartItems = cartRequest.result;

                if (cartItems.length === 0) {
                    alert("السلة فارغة!");
                    resetOrderButton();
                    return;
                }

                // تحضير بيانات الطلب
                const orderData = {
                    payment_method: 'cod',
                    payment_method_title: 'الدفع عند الاستلام',
                    set_paid: false,
                    status: 'processing',
                    customer_note: notes,
                    shipping_lines: [{
                        method_id: shippingMethod.id.toString(),
                        method_title: shippingMethod.title,
                        total: shippingMethod.cost.toString()
                    }],
                    line_items: cartItems.map(item => ({
                        product_id: item.id,
                        quantity: item.quantity,
                        name: item.name,
                        price: item.price
                    })),
                    meta_data: [
                        {
                            key: '_pos_order',
                            value: 'true'
                        },
                        {
                            key: '_order_source',
                            value: 'POS System'
                        }
                    ]
                };

                // إضافة معرف العميل إذا كان متوفراً
                if (customerId) {
                    orderData.customer_id = customerId;
                    console.log("👤 تم إضافة معرف العميل:", customerId);
                } else {
                    console.log("🎭 طلب بدون معرف عميل (ضيف)");

                    // إضافة معلومة أنه عميل ضيف
                    orderData.meta_data.push({
                        key: '_pos_guest_order',
                        value: 'true'
                    });
                }

                console.log("📤 بيانات الطلب النهائية:", orderData);

                // إرسال الطلب
                try {
                    Livewire.dispatch('submit-order', {
                        order: orderData
                    });

                    console.log("✅ تم إرسال الطلب");

                } catch (error) {
                    console.error("❌ خطأ في إرسال الطلب:", error);
                    alert("خطأ في إرسال الطلب: " + error.message);
                    resetOrderButton();
                }
            };
        };
    }

    function renderCustomersDropdown() {
        const tx = db.transaction("customers", "readonly");
        const store = tx.objectStore("customers");
        const request = store.getAll();

        request.onsuccess = function () {
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

            const addOption = document.createElement("option");
            addOption.value = "add_new_customer";
            addOption.textContent = "+ إضافة زبون جديد";
            dropdown.appendChild(addOption);

            dropdown.addEventListener('change', function () {
                if (this.value === "add_new_customer") {
                    this.value = "";
                    Flux.modal('add-customer-modal').show();
                }
            });
        };
    }

    function addNewCustomer() {
        const nameInput = document.getElementById("newCustomerName");
        const name = nameInput.value.trim();

        if (!name) {
            showNotification("يرجى إدخال اسم الزبون", 'warning');
            return;
        }

        const tx = db.transaction("customers", "readwrite");
        const store = tx.objectStore("customers");

        const newCustomer = {
            id: Date.now(),
            name: name
        };

        store.add(newCustomer);

        tx.oncomplete = () => {
            Flux.modal('add-customer-modal').close();
            renderCustomersDropdown();
            setTimeout(() => {
                const dropdown = document.getElementById("customerSelect");
                if (dropdown) {
                    dropdown.value = newCustomer.id;
                }
            }, 300);

            // مسح الحقل
            nameInput.value = '';
        };

        tx.onerror = () => {
            showNotification("حدث خطأ أثناء إضافة الزبون", 'error');
        };
    }

    // ============================================
    // دوال الشحن
    // ============================================
    function renderShippingZonesWithMethods() {
        const container = document.getElementById("shippingZonesContainer");
        if (!container) return;

        const txZones = db.transaction("shippingZones", "readonly");
        const storeZones = txZones.objectStore("shippingZones");
        const zonesRequest = storeZones.getAll();

        zonesRequest.onsuccess = function () {
            const zones = zonesRequest.result;

            const txMethods = db.transaction("shippingZoneMethods", "readonly");
            const storeMethods = txMethods.objectStore("shippingZoneMethods");
            const methodsRequest = storeMethods.getAll();

            methodsRequest.onsuccess = function () {
                const methods = methodsRequest.result;

                container.innerHTML = '';

                zones.forEach(zone => {
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
                            radio.name = "shippingMethod";
                            radio.value = method.id;
                            radio.id = `method-${method.id}`;
                            radio.addEventListener("change", () => {
                                updateOrderTotalInModal();
                            });

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

    function updateOrderTotalInModal() {
        const cartTx = db.transaction("cart", "readonly");
        const cartStore = cartTx.objectStore("cart");
        const cartRequest = cartStore.getAll();

        cartRequest.onsuccess = function () {
            const cartItems = cartRequest.result;
            const subTotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);

            const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');
            let shippingCost = 0;

            if (selectedMethod) {
                const shippingMethodId = parseInt(selectedMethod.value);
                const shippingTx = db.transaction("shippingZoneMethods", "readonly");
                const shippingStore = shippingTx.objectStore("shippingZoneMethods");
                const shippingReq = shippingStore.get(shippingMethodId);

                shippingReq.onsuccess = function () {
                    const method = shippingReq.result;
                    shippingCost = parseFloat(method?.cost ?? 0);
                    updateTotalDisplays(subTotal, shippingCost);
                };
            } else {
                updateTotalDisplays(subTotal, shippingCost);
            }
        };
    }

    function updateTotalDisplays(subTotal, shippingCost) {
        const subTotalDisplay = document.getElementById("subTotalDisplay");
        const shippingDisplay = document.getElementById("shippingCostDisplay");
        const finalDisplay = document.getElementById("finalTotalDisplay");

        if (subTotalDisplay) subTotalDisplay.textContent = `المجموع قبل التوصيل: ${subTotal.toFixed(2)} ₪`;
        if (shippingDisplay) shippingDisplay.textContent = `قيمة التوصيل: ${shippingCost.toFixed(2)} ₪`;
        if (finalDisplay) finalDisplay.textContent = ` ${(subTotal + shippingCost).toFixed(2)} ₪`;
    }

    // ============================================
    // دوال المزامنة
    // ============================================
    function setupSyncButton() {
        document.getElementById("syncButton").addEventListener("click", function () {
            if (!db) {
                showNotification("قاعدة البيانات غير جاهزة", 'error');
                return;
            }

            const storesToClear = [
                "products", "categories", "variations", "cart", "pendingOrders",
                "customers", "shippingMethods", "shippingZones", "shippingZoneMethods"
            ];

            const tx = db.transaction(storesToClear, "readwrite");

            storesToClear.forEach(storeName => {
                const store = tx.objectStore(storeName);
                store.clear();
            });

            tx.oncomplete = function () {
                console.log("✅ تم مسح كل البيانات من IndexedDB");

                Livewire.dispatch('fetch-products-from-api');
                Livewire.dispatch('fetch-categories-from-api');
                Livewire.dispatch('fetch-customers-from-api');
                Livewire.dispatch('fetch-shipping-methods-from-api');
                Livewire.dispatch('fetch-shipping-zones-and-methods');

                showNotification("✅ تمت المزامنة بنجاح!", 'success');
            };

            tx.onerror = function () {
                console.error("❌ فشل في مسح البيانات");
                showNotification("حدث خطأ أثناء المزامنة", 'error');
            };
        });
    }

    function checkAndFetchInitialData() {
        const checks = [
            {store: "products", action: 'fetch-products-from-api'},
            {store: "categories", action: 'fetch-categories-from-api'},
            {store: "customers", action: 'fetch-customers-from-api'},
            {store: "shippingMethods", action: 'fetch-shipping-methods-from-api'},
            {store: "shippingZones", action: 'fetch-shipping-zones-and-methods'}
        ];

        checks.forEach(check => {
            const tx = db.transaction(check.store, "readonly");
            const store = tx.objectStore(check.store);
            const countRequest = store.count();

            countRequest.onsuccess = function () {
                if (countRequest.result === 0) {
                    Livewire.dispatch(check.action);
                }
            };
        });
    }

    // ============================================
    // دوال المساعدة والإشعارات
    // ============================================
    function showLoadingIndicator(show) {
        const indicator = document.getElementById('searchLoadingIndicator');
        if (indicator) {
            indicator.style.display = show ? 'block' : 'none';
        }
    }

    function hideLoadingIndicator() {
        showLoadingIndicator(false);
    }

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 transform translate-x-full`;

        const colors = {
            'success': 'bg-green-500 text-white',
            'error': 'bg-red-500 text-white',
            'info': 'bg-blue-500 text-white',
            'warning': 'bg-yellow-500 text-black'
        };

        notification.className += ` ${colors[type] || colors.info}`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, 3000);
    }

    // ============================================
    // Livewire Event Listeners
    // ============================================
    document.addEventListener('livewire:init', () => {
        // تخزين المنتجات
        Livewire.on('store-products', (data) => {
            if (!db) return;
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");

            data.products.forEach(p => store.put(p));

            tx.oncomplete = () => {
                console.log("✅ All products including variations stored.");
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            };
        });

        // تخزين الفئات
        Livewire.on('store-categories', (data) => {
            if (!db) return;
            const tx = db.transaction("categories", "readwrite");
            const store = tx.objectStore("categories");
            data.categories.forEach(c => store.put(c));
            tx.oncomplete = () => renderCategoriesFromIndexedDB();
        });

        // تخزين المتغيرات
        Livewire.on('store-variations', (payload) => {
            if (!db) return;

            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");

            payload.variations.forEach(v => {
                if (!v.product_id) v.product_id = payload.product_id;
                store.put(v);
            });

            tx.oncomplete = () => {
                console.log("✅ تم تخزين المتغيرات في IndexedDB");
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            };
        });

        // تخزين العملاء
        Livewire.on('store-customers', (payload) => {
            if (!db) return;
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
            };
        });

        // تخزين طرق الشحن
        Livewire.on('store-shipping-methods', (data) => {
            if (!db) return;
            const tx = db.transaction("shippingMethods", "readwrite");
            const store = tx.objectStore("shippingMethods");
            data.methods.forEach(method => store.put(method));
            tx.oncomplete = () => {
                console.log("✅ Shipping Methods stored");
            };
        });

        // تخزين مناطق الشحن
        Livewire.on('store-shipping-zones', (payload) => {
            if (!db) return;
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

        // تخزين طرق الشحن للمناطق
        Livewire.on('store-shipping-zone-methods', (methods) => {
            if (!db) return;
            const tx = db.transaction("shippingZoneMethods", "readwrite");
            const store = tx.objectStore("shippingZoneMethods");

            methods.forEach(method => {
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

        // استقبال المنتج الموجود من API
        Livewire.on('product-found-from-api', (data) => {
            hideLoadingIndicator();
            const product = data[0].product;
            console.log("✅ تم العثور على المنتج من API:", product);

            // تخزين المنتج في IndexedDB
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");
            store.put(product);

            // إذا كان المنتج متغير وله متغيرات
            if (product.type === 'variable' && product.variations_full) {
                // تخزين المتغيرات في IndexedDB
                product.variations_full.forEach(variation => {
                    store.put(variation);
                });
            }

            tx.oncomplete = () => {
                console.log("✅ تم تخزين المنتج والمتغيرات في IndexedDB");
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);

                // معالجة المنتج حسب نوعه
                if (product.type === 'simple') {
                    addToCart(product);
                    showNotification(`تم العثور على "${product.name}" وإضافته للسلة`, 'success');
                } else if (product.type === 'variable') {
                    // إظهار modal للمتغيرات مباشرة
                    if (product.variations_full && product.variations_full.length > 0) {
                        showVariationsModal(product.variations_full);
                        showNotification(`تم العثور على "${product.name}" مع ${product.variations_full.length} متغير`, 'success');
                    } else {
                        showNotification(`تم العثور على "${product.name}" لكن لا توجد متغيرات متاحة`, 'warning');
                    }
                }

                clearSearchInput();
            };

            tx.onerror = () => {
                console.error("❌ فشل في تخزين المنتج");
            };
        });
        // استقبال إشعار عدم وجود المنتج
        Livewire.on('product-not-found', (data) => {
            hideLoadingIndicator();
            console.log("❌ لم يتم العثور على المنتج:", data[0].term);
            showNotification(`لم يتم العثور على المنتج: "${data[0].term}"`, 'error');
        });

        // استقبال خطأ في البحث
        Livewire.on('search-error', (data) => {
            hideLoadingIndicator();
            console.error("❌ خطأ في البحث:", data[0].message);
            showNotification(data[0].message, 'error');
        });

        // نجاح الطلب
        Livewire.on('order-success', () => {
            hideLoadingIndicator();
            clearCart();
            Flux.modal('confirm-order-modal').close();
            showNotification("✅ تم إرسال الطلب بنجاح!", 'success');
        });

        // فشل الطلب
        Livewire.on('order-failed', () => {
            hideLoadingIndicator();
            showNotification("❌ فشل في إرسال الطلب", 'error');
        });
    });

    // ============================================
    // إضافة event listeners للعملاء الجدد
    // ============================================
    document.addEventListener('livewire:init', () => {
        Livewire.on('add-simple-to-cart', (data) => {
            window.dispatchEvent(new CustomEvent('add-to-cart', {
                detail: data
            }));
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(setupConfirmButton, 1000);
    });
</script>

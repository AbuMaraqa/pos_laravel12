<div>
    <!-- Modals -->
    <flux:modal name="variations-modal" style="min-width: 70%">
        <div class="space-y-6">
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
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

            <div id="shippingZonesContainer" class="space-y-4"></div>

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
            <input id="newCustomerName" type="text" placeholder="اسم الزبون" class="w-full border rounded px-3 py-2"/>
            <div class="flex justify-end gap-2">
                <flux:button variant="danger" onclick="Flux.modal('add-customer-modal').close()">إلغاء</flux:button>
                <flux:button variant="primary" onclick="addNewCustomer()">حفظ</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Main Interface -->
    <div class="grid gap-4 grid-cols-6">
        <div class="col-span-4">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <!-- Search Bar -->
                <div class="flex items-center gap-2">
                    <flux:input id="searchInput" placeholder="Search" icon="magnifying-glass"/>
                    <flux:button>Scan</flux:button>
                    <flux:button id="syncButton">Sync</flux:button>
                </div>

                <!-- Categories -->
                <div class="mt-4">
                    <div id="categoriesContainer" class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                        <!-- التصنيفات سيتم تحميلها من IndexedDB عبر JS -->
                    </div>
                </div>

                <div class="mt-4">
                    <flux:separator/>
                </div>

                <!-- Products Grid -->
                <div class="mt-4 h-full bg-gray-200 p-4 rounded-lg shadow-md">
                    <div id="productsContainer" class="grid grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                        <!-- Loading indicator -->
                        <div id="searchLoadingIndicator" class="col-span-4 text-center py-8" style="display: none;">
                            <div
                                class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-2"></div>
                            <p class="text-gray-500">جاري البحث...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Sidebar -->
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
    // متغيرات عامة ومبدئية
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

    // متغيرات تحسين البحث
    let lastSearchTerm = '';
    let lastCategoryId = null;
    let searchTimeout = null;
    let isSearching = false;

    // متغيرات التحميل التدريجي
    let currentPage = 1;
    let totalPages = 1;
    let isLoadingMore = false;
    let allProductsLoaded = false;

    // ============================================
    // تهيئة قاعدة البيانات
    // ============================================
    document.addEventListener("livewire:navigated", () => {
        if (db) {
            initializeUI();
            return;
        }

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
            preventUnnecessaryReloads();
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

    // ============================================
    // إعداد Event Listeners
    // ============================================
    function setupEventListeners() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.removeEventListener('input', handleSearchInput);
            searchInput.removeEventListener('keydown', handleEnterKeySearch);
            searchInput.addEventListener('input', handleSearchInput);
            searchInput.addEventListener('keydown', handleEnterKeySearch);
        }

        setupSyncButton();
        setupOrderButton();
        setupConfirmOrderButton();
    }

    // ============================================
    // نظام البحث المحسن مع منع إعادة التحميل
    // ============================================
    function handleSearchInput(event) {
        const newSearchTerm = event.target.value.trim();

        // إلغاء البحث السابق
        if (searchTimeout) {
            clearTimeout(searchTimeout);
        }

        // منع البحث المتكرر لنفس المصطلح
        if (newSearchTerm === lastSearchTerm && selectedCategoryId === lastCategoryId) {
            return;
        }

        // تأخير البحث لتجنب الاستدعاءات المتكررة (Debouncing)
        searchTimeout = setTimeout(() => {
            if (isSearching) return;

            isSearching = true;
            currentSearchTerm = newSearchTerm;
            lastSearchTerm = newSearchTerm;
            lastCategoryId = selectedCategoryId;

            console.log(`🔍 البحث عن: "${currentSearchTerm}"`);

            // إظهار مؤشر التحميل للبحث الجديد فقط
            if (newSearchTerm !== lastSearchTerm) {
                showSearchLoadingIndicator(true);
            }

            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);

            setTimeout(() => {
                isSearching = false;
                showSearchLoadingIndicator(false);
            }, 300);

        }, 300);
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

            const matched = products.find(item => {
                const nameMatch = item.name?.toLowerCase().includes(searchTerm);
                const barcodeMatch = item.id?.toString() === searchTerm;
                const skuMatch = item.sku?.toLowerCase() === searchTerm;
                return nameMatch || barcodeMatch || skuMatch;
            });

            if (matched) {
                // حالة المنتج الأب/البسيط كما هي
                if (matched.type === 'variable' || (!matched.product_id && matched.type !== 'variation')) {
                    handleFoundProduct(matched);
                    clearSearchInput();
                    return;
                }

                // لو نتيجة البحث كانت متغيّر (ابن)
                if (matched.product_id) {
                    const ptx = db.transaction("products", "readonly");
                    const pstore = ptx.objectStore("products");
                    const pReq = pstore.get(parseInt(matched.product_id));

                    pReq.onsuccess = function () {
                        const parent = pReq.result;
                        if (parent && parent.type === 'variable') {
                            addVariationUnderParentToCart(parent, matched);
                            clearSearchInput();
                        } else {
                            // الأب غير موجود محلياً → جيبه من API ثم أضف الابن بعد التخزين
                            searchProductFromAPI(matched.product_id.toString());
                            // ملاحظة: سنغطي حالة API في (C)
                        }
                    };
                    pReq.onerror = function () {
                        showNotification("تعذر جلب المنتج الأب", 'error');
                    };

                    return;
                }

                // fallback
                handleFoundProduct(matched);
                clearSearchInput();
            } else {
                // كما هي
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
        showLoadingIndicator(true);
        Livewire.dispatch('search-product-from-api', {searchTerm: searchTerm});
    }

    function clearSearchInput() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput && searchInput.value) {
            searchInput.value = '';

            if (currentSearchTerm) {
                currentSearchTerm = '';
                lastSearchTerm = '';
                console.log("🧹 تم مسح البحث");
                renderProductsFromIndexedDB('', selectedCategoryId);
            }
        }
    }

    // ============================================
    // نظام منع إعادة التحميل غير الضرورية
    // ============================================
    function preventUnnecessaryReloads() {
        const saveSearchState = () => {
            try {
                const searchState = {
                    searchTerm: currentSearchTerm,
                    categoryId: selectedCategoryId,
                    timestamp: Date.now()
                };
                localStorage.setItem('posSearchState', JSON.stringify(searchState));
            } catch (e) {
                console.warn('localStorage not available');
            }
        };

        const restoreSearchState = () => {
            try {
                const saved = localStorage.getItem('posSearchState');
                if (saved) {
                    const state = JSON.parse(saved);

                    if (Date.now() - state.timestamp < 300000) { // 5 دقائق
                        currentSearchTerm = state.searchTerm || '';
                        selectedCategoryId = state.categoryId;
                        lastSearchTerm = currentSearchTerm;
                        lastCategoryId = selectedCategoryId;

                        const searchInput = document.getElementById('searchInput');
                        if (searchInput && currentSearchTerm) {
                            searchInput.value = currentSearchTerm;
                        }

                        return true;
                    }
                }
            } catch (e) {
                console.warn('Error restoring search state');
            }
            return false;
        };

        // استرداد الحالة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', () => {
            if (restoreSearchState()) {
                console.log("📁 تم استرداد حالة البحث السابقة");
            }
        });

        // حفظ الحالة عند التغيير
        window.addEventListener('beforeunload', saveSearchState);
    }

    // ============================================
    // عرض المنتجات مع Lazy Loading للصور
    // ============================================
    function renderProductsFromIndexedDB(searchTerm = '', categoryId = null) {
        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.getAll();

        request.onsuccess = function () {
            const products = request.result;
            const container = document.getElementById("productsContainer");
            if (!container) return;

            // إخفاء مؤشر التحميل
            showSearchLoadingIndicator(false);

            container.innerHTML = '';

            const filtered = products.filter(item => {
                const term = searchTerm.trim().toLowerCase();
                const isAllowedType = item.type === 'simple' || item.type === 'variable';
                const matchesSearch = !term || (
                    (item.name && item.name.toLowerCase().includes(term)) ||
                    (item.id && item.id.toString().includes(term)) ||
                    (item.sku && item.sku.toLowerCase().includes(term))
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

            filtered.forEach(item => {
                const div = document.createElement("div");
                div.classList.add("bg-white", "rounded-lg", "shadow-md", "relative", "product-card", "hover:shadow-lg", "transition-shadow");
                div.style.cursor = "pointer";
                div.setAttribute('data-product-id', item.id);

                div.onclick = function () {
                    if (item.type === 'variable' && Array.isArray(item.variations)) {
                        fetchVariationsAndShowModal(item);
                    } else if (item.type === 'simple') {
                        addToCart(item);
                    }
                };

                // استخدام placeholder بدلاً من الصورة الحقيقية لتسريع التحميل
                div.innerHTML = `
                    <div class="relative h-32 bg-gray-100 rounded-t-lg flex items-center justify-center image-placeholder" data-product-id="${item.id}">
                        <div class="text-gray-400 text-4xl">📦</div>
                        <div class="absolute top-2 left-2 bg-black text-white text-xs px-2 py-1 rounded opacity-75">
                            #${item.id}
                        </div>
                        <div class="absolute bottom-2 left-2 bg-blue-600 text-white px-2 py-1 rounded font-bold text-sm">
                            ${item.price || 0} ₪
                        </div>
                        ${item.stock_status === 'outofstock' ? '<div class="absolute inset-0 bg-red-500 bg-opacity-50 flex items-center justify-center"><span class="text-white font-bold">نفدت الكمية</span></div>' : ''}
                    </div>
                    <div class="p-3">
                        <p class="font-bold text-sm text-center truncate" title="${item.name || ''}">${item.name || ''}</p>
                        ${item.sku ? `<p class="text-xs text-gray-500 text-center mt-1">SKU: ${item.sku}</p>` : ''}
                        ${item.type === 'variable' ? '<p class="text-xs text-blue-500 text-center mt-1">منتج متغير</p>' : ''}
                    </div>
                `;

                container.appendChild(div);
            });

            // تطبيق Lazy Loading للصور
            setupLazyImageLoading();
        };

        request.onerror = function () {
            console.error("❌ Failed to fetch products from IndexedDB");
            showSearchLoadingIndicator(false);
        };
    }

    // ============================================
    // نظام Lazy Loading للصور
    // ============================================
    function setupLazyImageLoading() {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const card = entry.target;
                    const productId = card.getAttribute('data-product-id');

                    loadProductImage(productId, card);
                    observer.unobserve(card);
                }
            });
        }, {
            rootMargin: '50px'
        });

        document.querySelectorAll('.product-card').forEach(card => {
            imageObserver.observe(card);
        });
    }

    async function loadProductImage(productId, cardElement) {
        try {
            const tx = db.transaction("products", "readonly");
            const store = tx.objectStore("products");
            const request = store.get(parseInt(productId));

            request.onsuccess = function () {
                const product = request.result;
                if (product && product.images && product.images.length > 0) {
                    const imageUrl = product.images[0].src;

                    const img = new Image();
                    img.onload = function () {
                        const placeholder = cardElement.querySelector('.image-placeholder');
                        if (placeholder) {
                            placeholder.innerHTML = `
                                <img src="${imageUrl}" class="w-full h-full object-cover rounded-t-lg" alt="${product.name || ''}">
                                <div class="absolute top-2 left-2 bg-black text-white text-xs px-2 py-1 rounded opacity-75">
                                    #${product.id}
                                </div>
                                <div class="absolute bottom-2 left-2 bg-blue-600 text-white px-2 py-1 rounded font-bold text-sm">
                                    ${product.price || 0} ₪
                                </div>
                                ${product.stock_status === 'outofstock' ? '<div class="absolute inset-0 bg-red-500 bg-opacity-50 flex items-center justify-center"><span class="text-white font-bold">نفدت الكمية</span></div>' : ''}
                            `;
                        }
                    };

                    img.onerror = function () {
                        console.warn(`فشل تحميل صورة المنتج ${productId}`);
                    };

                    img.src = imageUrl;
                }
            };
        } catch (error) {
            console.warn(`خطأ في تحميل صورة المنتج ${productId}:`, error);
        }
    }

    // ============================================
    // إدارة الفئات المحسنة
    // ============================================
    function selectCategory(categoryId) {
        if (categoryId === selectedCategoryId) {
            return; // منع إعادة التحديد لنفس الفئة
        }

        selectedCategoryId = categoryId;
        lastCategoryId = categoryId;

        console.log(`📂 تغيير الفئة إلى: ${categoryId || 'الكل'}`);

        updateCategoryButtons();
        renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
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

            // زر "الكل"
            const allBtn = document.createElement("button");
            allBtn.innerText = "الكل";
            allBtn.classList.add("px-3", "py-1", "text-sm", "rounded", "transition-colors");

            if (selectedCategoryId === null) {
                allBtn.classList.add("bg-blue-500", "text-white");
            } else {
                allBtn.classList.add("bg-white", "border", "text-gray-700", "hover:bg-gray-100");
            }

            allBtn.onclick = () => selectCategory(null);
            container.appendChild(allBtn);

            // أزرار الفئات
            categories.forEach(item => {
                const btn = document.createElement("button");
                btn.innerText = item.name;
                btn.classList.add("px-3", "py-1", "text-sm", "rounded", "transition-colors");

                if (selectedCategoryId === item.id) {
                    btn.classList.add("bg-blue-500", "text-white");
                } else {
                    btn.classList.add("bg-white", "border", "text-gray-700", "hover:bg-gray-100");
                }

                btn.onclick = () => selectCategory(item.id);
                container.appendChild(btn);
            });
        };

        request.onerror = () => {
            console.error("❌ Failed to load categories");
        };
    }

    function updateCategoryButtons() {
        const container = document.getElementById("categoriesContainer");
        if (!container) return;

        container.querySelectorAll('button').forEach((btn, index) => {
            btn.classList.remove('bg-blue-500', 'text-white');
            btn.classList.add('bg-white', 'border', 'text-gray-700');

            if ((index === 0 && selectedCategoryId === null) ||
                (btn.textContent !== 'الكل' && selectedCategoryId && btn.onclick.toString().includes(selectedCategoryId))) {
                btn.classList.remove('bg-white', 'border', 'text-gray-700');
                btn.classList.add('bg-blue-500', 'text-white');
            }
        });
    }

    // ============================================
    // إدارة السلة
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
                container.innerHTML = `...`; // كما كان
                totalElement.textContent = "0.00 ₪";
                return;
            }

            container.innerHTML = '';
            let total = 0;

            cartItems.forEach(item => {
                let itemTotal = 0;
                if (item.type === 'variable' && Array.isArray(item.variations)) {
                    itemTotal = item.variations.reduce((sum, v) => sum + (parseFloat(v.price || 0) * (v.quantity || 0)), 0);
                } else {
                    itemTotal = (parseFloat(item.price || 0) * (item.quantity || 0));
                }
                total += itemTotal;

                const div = document.createElement("div");
                div.className = "bg-gray-100 rounded p-2 mb-2";

                if (item.type === 'variable' && Array.isArray(item.variations)) {
                    // سطر رأس للأب
                    div.innerHTML = `
                    <div class="flex justify-between items-center">
                        <p class="font-semibold">${item.name}</p>
                        <div class="font-bold">${itemTotal.toFixed(2)} ₪
                            <button onclick="removeFromCart(${item.id})" class="text-red-500 hover:text-red-700 ml-2">🗑️</button>
                        </div>
                    </div>
                    <div class="mt-2 space-y-2" id="children-of-${item.id}"></div>
                `;

                    const childrenContainer = div.querySelector(`#children-of-${item.id}`);
                    item.variations.forEach(v => {
                        const row = document.createElement('div');
                        row.className = "flex justify-between items-center bg-white p-2 rounded border";
                        row.innerHTML = `
                        <div>
                            <p class="text-sm">${v.name}</p>
                            <div class="flex items-center gap-2 text-sm">
                                <button onclick="updateVariationQuantity(${item.id}, ${v.id}, -1)" class="bg-gray-300 px-2 rounded">−</button>
                                <span>${v.quantity}</span>
                                <button onclick="updateVariationQuantity(${item.id}, ${v.id}, 1)" class="bg-gray-300 px-2 rounded">+</button>
                                <button onclick="removeVariationFromCart(${item.id}, ${v.id})" class="text-red-500 hover:text-red-700 ml-2">🗑️</button>
                            </div>
                        </div>
                        <div class="font-semibold">${(parseFloat(v.price || 0) * (v.quantity || 0)).toFixed(2)} ₪</div>
                    `;
                        childrenContainer.appendChild(row);
                    });
                } else {
                    // السلوك القديم للـ simple
                    div.innerHTML = `
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-semibold">${item.name}</p>
                            <div class="flex items-center gap-2">
                                <button onclick="updateQuantity(${item.id}, -1)" class="bg-gray-300 px-2 rounded">−</button>
                                <span>${item.quantity}</span>
                                <button onclick="updateQuantity(${item.id}, 1)" class="bg-gray-300 px-2 rounded">+</button>
                            </div>
                        </div>
                        <div class="font-bold text-gray-800 flex items-center gap-2">
                            <span>${(parseFloat(item.price || 0) * (item.quantity || 0)).toFixed(2)} ₪</span>
                            <button onclick="removeFromCart(${item.id})" class="text-red-500 hover:text-red-700">🗑️</button>
                        </div>
                    </div>
                `;
                }

                container.appendChild(div);
            });

            totalElement.textContent = `${total.toFixed(2)} ₪`;
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

    function resetToOriginalCode() {
        console.log("🔄 إعادة ضبط إلى الكود الأصلي...");

        // إعادة تعريف جميع الدوال في النطاق العام
        window.addToCart = addToCart;
        window.renderCart = renderCart;
        window.removeFromCart = removeFromCart;
        window.updateQuantity = updateQuantity;
        window.clearCart = clearCart;

        // إعادة تهيئة قاعدة البيانات إذا لزم الأمر
        if (!db) {
            const openRequest = indexedDB.open(dbName, 5);

            openRequest.onsuccess = function (event) {
                db = event.target.result;
                window.db = db;
                renderCart();
                console.log("✅ تم إعادة تهيئة قاعدة البيانات");
            };

            openRequest.onerror = function () {
                console.error("❌ خطأ في فتح قاعدة البيانات");
            };
        } else {
            renderCart();
        }

        console.log("✅ تم إعادة الضبط للكود الأصلي");
    }

    // دالة اختبار بسيطة
    function testOriginalCart() {
        console.log("🧪 اختبار الكود الأصلي...");

        const testProduct = {
            id: 99999,
            name: "منتج اختبار",
            price: 25.00,
            images: []
        };

        addToCart(testProduct);
    }

    // تشغيل تلقائي
    window.resetToOriginalCode = resetToOriginalCode;
    window.testOriginalCart = testOriginalCart;

    // ============================================
    // إدارة المتغيرات المحسنة
    // ============================================
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

    function updateVariationQuantity(parentId, variationId, change) {
        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const req = store.get(parentId);

        req.onsuccess = function(){
            const item = req.result;
            if (!item || !Array.isArray(item.variations)) return;

            const idx = item.variations.findIndex(v => v.id === variationId);
            if (idx === -1) return;

            item.variations[idx].quantity += change;

            // لو أصبحت 0 احذف الابن
            if (item.variations[idx].quantity <= 0) {
                item.variations.splice(idx, 1);
            }

            // لو لم يبقَ أي ابن احذف الأب كله
            if (item.variations.length === 0) {
                store.delete(parentId);
            } else {
                store.put(item);
            }
            renderCart(parentId);
        };
    }

    function removeVariationFromCart(parentId, variationId) {
        updateVariationQuantity(parentId, variationId, -9999); // اختصار للحذف
    }

    // تعريضها للعالمية إن لزم
    window.updateVariationQuantity = updateVariationQuantity;
    window.removeVariationFromCart = removeVariationFromCart;


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

        // عنوان المودال
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

            card.onmouseenter = () => card.classList.add('transform', 'scale-105');
            card.onmouseleave = () => card.classList.remove('transform', 'scale-105');

            const isOutOfStock = variation.stock_status === 'outofstock';

            card.onclick = () => {
                if (isOutOfStock) {
                    showNotification('هذا المتغير غير متوفر حالياً', 'warning');
                    return;
                }
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
            let stockInfo = 'متوفر';
            let stockClass = 'bg-green-500';
            if (isOutOfStock) {
                stockInfo = 'نفدت الكمية';
                stockClass = 'bg-red-500';
            } else if (variation.stock_quantity !== undefined && variation.stock_quantity !== null) {
                stockInfo = `متوفر: ${variation.stock_quantity}`;
                stockClass = variation.stock_quantity > 10 ? 'bg-green-500' : 'bg-yellow-500';
            }

            card.innerHTML = `
                <div class="absolute top-2 left-2 bg-black text-white text-xs px-2 py-1 rounded z-10 opacity-75">
                    #${variation.id}
                </div>
                <div class="absolute top-2 right-2 ${stockClass} text-white text-xs px-2 py-1 rounded z-10">
                    ${stockInfo}
                </div>
                <div class="relative h-48 bg-gray-100 flex items-center justify-center">
                    <div class="text-gray-400 text-4xl">📦</div>
                    <div class="absolute bottom-2 left-2 bg-blue-600 text-white px-3 py-1 rounded-full font-bold text-sm">
                        ${variation.price || 0} ₪
                    </div>
                </div>
                <div class="p-3 space-y-2">
                    <h4 class="font-semibold text-sm text-gray-800 line-clamp-2" title="${variation.name || 'متغير'}">
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
                    <button class="w-full mt-2 ${isOutOfStock ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-500 hover:bg-green-600'} text-white py-2 px-3 rounded-md text-sm font-semibold transition-colors">
                        ${isOutOfStock ? 'غير متوفر' : 'إضافة للسلة'}
                    </button>
                </div>
            `;

            if (isOutOfStock) {
                card.classList.add('opacity-60');
            }

            grid.appendChild(card);
        });

        container.appendChild(grid);

        // footer للمودال
        const footer = document.createElement("div");
        footer.className = "text-center mt-4 p-3 bg-gray-50 rounded-lg text-xs text-gray-600";
        footer.textContent = "اضغط على أي متغير متوفر لإضافته إلى السلة";
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
                showNotification("لم يتم العثور على هذا المتغير", 'error');
                return;
            }
            if (variation.stock_status === 'outofstock') {
                showNotification("هذا المتغير غير متوفر حالياً", 'warning');
                return;
            }

            const ptx = db.transaction("products", "readonly");
            const pstore = ptx.objectStore("products");
            const pReq = pstore.get(parseInt(variation.product_id));

            pReq.onsuccess = function(){
                const parent = pReq.result;
                if (parent && parent.type === 'variable') {
                    addVariationUnderParentToCart(parent, variation);
                    Flux.modal('variations-modal').close();
                } else {
                    showNotification("تعذر تحديد المنتج الأب للمتغير", 'error');
                }
            };
            pReq.onerror = function(){ showNotification("تعذر جلب المنتج الأب", 'error'); };
        };

        request.onerror = function () {
            showNotification("حدث خطأ أثناء إضافة المتغير", 'error');
        };
    }

    // ============================================
    // إدارة الطلبات المحسنة
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
        setTimeout(attachConfirmOrderListener, 500);
        document.addEventListener("livewire:navigated", function () {
            setTimeout(attachConfirmOrderListener, 500);
        });
    }

    function attachConfirmOrderListener() {
        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');

        if (confirmBtn) {
            confirmBtn.removeEventListener('click', handleOrderSubmit);
            confirmBtn.addEventListener('click', handleOrderSubmit);
            console.log("✅ تم ربط زر تأكيد الطلب بنجاح");
        } else {
            console.warn("⚠️ لم يتم العثور على زر تأكيد الطلب");
            setTimeout(attachConfirmOrderListener, 1000);
        }
    }

    function handleOrderSubmit(e) {
        e.preventDefault();

        console.log("🔄 بدء عملية إرسال الطلب...");

        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');
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

        // تعطيل الزر وإظهار التحميل
        if (confirmBtn) {
            confirmBtn.disabled = true;
            confirmBtn.textContent = "جاري الإرسال...";
        }
        showLoadingIndicator(true);

        // معالجة الطلب
        processOrder(customerId, notes, selectedMethod, confirmBtn);
    }

    function processOrder(customerId, notes, selectedMethod, confirmBtn) {
        const shippingMethodId = parseInt(selectedMethod.value);

        // جلب تفاصيل طريقة الشحن
        const txMethods = db.transaction("shippingZoneMethods", "readonly");
        const storeMethods = txMethods.objectStore("shippingZoneMethods");
        const methodRequest = storeMethods.get(shippingMethodId);

        methodRequest.onsuccess = function () {
            const shippingMethod = methodRequest.result;

            if (!shippingMethod) {
                showNotification("خطأ في بيانات طريقة الشحن", 'error');
                resetOrderButton(confirmBtn);
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
                    resetOrderButton(confirmBtn);
                    return;
                }

                // تحضير بيانات الطلب
                const orderData = prepareOrderData(customerId, notes, shippingMethod, cartItems);

                // التحقق من صحة البيانات
                const validationErrors = validateOrderData(orderData);
                if (validationErrors.length > 0) {
                    showNotification("خطأ في البيانات: " + validationErrors.join(', '), 'error');
                    resetOrderButton(confirmBtn);
                    return;
                }

                // إرسال الطلب
                submitOrderData(orderData);
            };

            cartRequest.onerror = function () {
                showNotification("خطأ في قراءة السلة", 'error');
                resetOrderButton(confirmBtn);
            };
        };

        methodRequest.onerror = function () {
            showNotification("خطأ في جلب بيانات طريقة الشحن", 'error');
            resetOrderButton(confirmBtn);
        };
    }

    function prepareOrderData(customerId, notes, shippingMethod, cartItems) {
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

        // إضافة معرف العميل أو تحديد أنه ضيف
        if (customerId === "guest") {
            orderData.meta_data.push({
                key: '_pos_guest_order',
                value: 'true'
            });
            console.log("🎭 طلب بدون معرف عميل (ضيف)");
        } else {
            orderData.customer_id = parseInt(customerId);
            console.log("👤 تم إضافة معرف العميل:", customerId);
        }

        return orderData;
    }

    function submitOrderData(orderData) {
        console.log("📤 إرسال بيانات الطلب:", orderData);

        if (navigator.onLine) {
            try {
                Livewire.dispatch('submit-order', {order: orderData});
                console.log("✅ تم إرسال الطلب إلى الخادم");
            } catch (error) {
                console.error("❌ خطأ في إرسال الطلب:", error);
                showNotification("خطأ في إرسال الطلب: " + error.message, 'error');
                resetOrderButton();
            }
        } else {
            savePendingOrder(orderData);
        }
    }

    function resetOrderButton(confirmBtn = null) {
        showLoadingIndicator(false);
        const btn = confirmBtn || document.getElementById('confirmOrderSubmitBtn');
        if (btn) {
            btn.disabled = false;
            btn.textContent = "تأكيد الطلب";
        }
    }

    function validateOrderData(orderData) {
        const errors = [];

        if (!orderData.customer_id && !orderData.meta_data.some(m => m.key === '_pos_guest_order')) {
            errors.push("معرف العميل مطلوب");
        }

        if (!orderData.line_items || orderData.line_items.length === 0) {
            errors.push("يجب إضافة منتجات للطلب");
        }

        if (!orderData.shipping_lines || orderData.shipping_lines.length === 0) {
            errors.push("يجب اختيار طريقة شحن");
        }

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
            clearCartAfterOrder();
            resetOrderButton();
            Flux.modal('confirm-order-modal').close();
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

    // ============================================
    // إدارة العملاء والشحن
    // ============================================
    function renderCustomersDropdown() {
        const tx = db.transaction("customers", "readonly");
        const store = tx.objectStore("customers");
        const request = store.getAll();

        request.onsuccess = function () {
            const customers = request.result;
            const dropdown = document.getElementById("customerSelect");
            if (!dropdown) return;

            dropdown.innerHTML = '<option value="">اختر عميل</option>';

            customers.forEach(customer => {
                if (customer.id && customer.name) {
                    const option = document.createElement("option");
                    option.value = customer.id;
                    option.textContent = customer.name;
                    dropdown.appendChild(option);
                }
            });

            // إضافة خيار عميل ضيف
            const guestOption = document.createElement("option");
            guestOption.value = "guest";
            guestOption.textContent = "عميل ضيف";
            dropdown.appendChild(guestOption);

            // إضافة خيار عميل جديد
            const addOption = document.createElement("option");
            addOption.value = "add_new_customer";
            addOption.textContent = "+ إضافة عميل جديد";
            dropdown.appendChild(addOption);

            dropdown.addEventListener('change', function () {
                if (this.value === "add_new_customer") {
                    this.value = "";
                    Flux.modal('add-customer-modal').show();
                }
            });

            console.log(`✅ تم تحميل ${customers.length} عميل + خيارات إضافية`);
        };

        request.onerror = function () {
            console.error("❌ خطأ في جلب العملاء من قاعدة البيانات");
        };
    }

    function addNewCustomer() {
        const nameInput = document.getElementById("newCustomerName");
        const name = nameInput.value.trim();

        if (!name) {
            showNotification("يرجى إدخال اسم العميل", 'warning');
            return;
        }

        const tx = db.transaction("customers", "readwrite");
        const store = tx.objectStore("customers");

        const newCustomer = {
            id: Date.now(),
            name: name
        };

        store.add(newCustomer);

// بعد tx.oncomplete = () => { ... }
        tx.oncomplete = () => {
            console.log("✅ تم تخزين المنتج والمتغيرات في IndexedDB");
            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);

            if (product.type === 'simple') {
                addToCart(cleanedProduct);
                showNotification(`تم العثور على "${product.name}" وإضافته للسلة`, 'success');
            } else if (product.type === 'variable') {
                const matchedId = product.matched_variation_id || null;

                if (matchedId && Array.isArray(product.variations_full)) {
                    const matchedVar = product.variations_full.find(v => v.id === matchedId);
                    if (matchedVar) {
                        addVariationUnderParentToCart(cleanedProduct, matchedVar);
                        clearSearchInput();
                        return; // انتهى
                    }
                }

                // إن لم نعرف الابن المطابق نرجع للسلوك السابق (فتح المودال)
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
            showNotification("حدث خطأ أثناء إضافة العميل", 'error');
        };
    }

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
                    zoneDiv.classList.add("border", "rounded", "p-4", "shadow", "bg-white");

                    const zoneTitle = document.createElement("h3");
                    zoneTitle.classList.add("font-bold", "mb-2", "text-gray-800");
                    zoneTitle.textContent = `📦 ${zone.name}`;
                    zoneDiv.appendChild(zoneTitle);

                    const zoneMethods = methods.filter(m => m.zone_id === zone.id);
                    if (zoneMethods.length === 0) {
                        const noMethods = document.createElement("p");
                        noMethods.className = "text-gray-500 text-sm";
                        noMethods.textContent = "لا يوجد طرق شحن لهذه المنطقة.";
                        zoneDiv.appendChild(noMethods);
                    } else {
                        zoneMethods.forEach(method => {
                            const wrapper = document.createElement("div");
                            wrapper.classList.add("flex", "items-center", "gap-2", "mb-2", "p-2", "hover:bg-gray-50", "rounded");

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
                            label.classList.add("text-sm", "cursor-pointer", "flex-1");
                            label.innerHTML = `
                                <span class="font-medium">${method.title}</span>
                                <span class="text-blue-600 font-bold ml-2">${method.cost} ₪</span>
                            `;

                            wrapper.appendChild(radio);
                            wrapper.appendChild(label);
                            zoneDiv.appendChild(wrapper);
                        });
                    }

                    container.appendChild(zoneDiv);
                });

                // اختيار أول طريقة شحن تلقائياً
                const firstMethod = container.querySelector('input[name="shippingMethod"]');
                if (firstMethod) {
                    firstMethod.checked = true;
                    updateOrderTotalInModal();
                }
            };
        };
    }

    function updateOrderTotalInModal() {
        const cartTx = db.transaction("cart", "readonly");
        const cartStore = cartTx.objectStore("cart");
        const cartRequest = cartStore.getAll();

        cartRequest.onsuccess = function () {
            const cartItems = cartRequest.result;
            const subTotal = cartItems.reduce((sum, item) => sum + ((item.price || 0) * (item.quantity || 1)), 0);

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
        if (finalDisplay) finalDisplay.textContent = `${(subTotal + shippingCost).toFixed(2)} ₪`;
    }

    // ============================================
    // نظام المزامنة والتحميل الأولي
    // ============================================
    function setupSyncButton() {
        document.getElementById("syncButton").addEventListener("click", function () {
            if (!db) {
                showNotification("قاعدة البيانات غير جاهزة", 'error');
                return;
            }

            if (!confirm("هل أنت متأكد من مزامنة البيانات؟ سيتم حذف جميع البيانات المحلية وتحميل بيانات جديدة.")) {
                return;
            }

            showLoadingIndicator(true);
            console.log("🔄 بدء عملية المزامنة...");

            const storesToClear = [
                "products", "categories", "variations", "customers",
                "shippingMethods", "shippingZones", "shippingZoneMethods"
            ];

            const tx = db.transaction(storesToClear, "readwrite");

            storesToClear.forEach(storeName => {
                const store = tx.objectStore(storeName);
                store.clear();
            });

            tx.oncomplete = function () {
                console.log("✅ تم مسح البيانات المحلية");

                // تحميل البيانات الجديدة
                Livewire.dispatch('fetch-products-from-api');
                Livewire.dispatch('fetch-categories-from-api');
                Livewire.dispatch('fetch-customers-from-api');
                Livewire.dispatch('fetch-shipping-methods-from-api');
                Livewire.dispatch('fetch-shipping-zones-and-methods');

                showNotification("✅ تمت المزامنة بنجاح!", 'success');
                showLoadingIndicator(false);
            };

            tx.onerror = function () {
                console.error("❌ فشل في مسح البيانات");
                showNotification("حدث خطأ أثناء المزامنة", 'error');
                showLoadingIndicator(false);
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
                    console.log(`📥 تحميل ${check.store} للمرة الأولى...`);
                    Livewire.dispatch(check.action);
                }
            };
        });
    }

    // ============================================
    // مؤشرات التحميل والإشعارات
    // ============================================
    function showLoadingIndicator(show) {
        const indicator = document.getElementById('searchLoadingIndicator');
        if (indicator) {
            indicator.style.display = show ? 'flex' : 'none';
        }
    }

    function showSearchLoadingIndicator(show) {
        const container = document.getElementById("productsContainer");
        if (!container) return;

        const existingIndicator = container.querySelector('.search-loading');

        if (show && !existingIndicator) {
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'col-span-4 text-center py-8 search-loading';
            loadingDiv.innerHTML = `
                <div class="flex items-center justify-center space-x-2">
                    <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-500"></div>
                    <span class="text-gray-500 mr-2">جاري البحث...</span>
                </div>
            `;
            container.insertBefore(loadingDiv, container.firstChild);
        } else if (!show && existingIndicator) {
            existingIndicator.remove();
        }
    }

    function hideLoadingIndicator() {
        showLoadingIndicator(false);
        showSearchLoadingIndicator(false);
    }

    function showNotification(message, type = 'info', duration = 3000) {
        // إزالة الإشعارات السابقة
        const existingNotifications = document.querySelectorAll('.pos-notification');
        existingNotifications.forEach(notif => notif.remove());

        const notification = document.createElement('div');
        notification.className = `pos-notification fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg max-w-sm transition-all duration-300 transform translate-x-full`;

        const colors = {
            'success': 'bg-green-500 text-white border-green-600',
            'error': 'bg-red-500 text-white border-red-600',
            'info': 'bg-blue-500 text-white border-blue-600',
            'warning': 'bg-yellow-500 text-black border-yellow-600'
        };

        const icons = {
            'success': '✅',
            'error': '❌',
            'info': 'ℹ️',
            'warning': '⚠️'
        };

        notification.className += ` ${colors[type] || colors.info} border-l-4`;
        notification.innerHTML = `
            <div class="flex items-start gap-2">
                <span class="text-lg">${icons[type] || icons.info}</span>
                <span class="flex-1">${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="text-lg opacity-70 hover:opacity-100">&times;</button>
            </div>
        `;

        document.body.appendChild(notification);

        // إظهار الإشعار
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 100);

        // إخفاء الإشعار تلقائياً
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.add('translate-x-full');
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, duration);
    }

    // ============================================
    // Livewire Event Listeners
    // ============================================
    document.addEventListener('livewire:init', () => {
        console.log("🔌 Livewire تم تهيئته");

        // تخزين المنتجات
        Livewire.on('store-products', (data) => {
            if (!db) return;
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");

            // تنظيف المنتجات من الصور لتسريع التخزين
            const cleanedProducts = data.products.map(product => ({
                ...product,
                images: [], // إزالة الصور لتسريع التحميل
                description: '', // إزالة الوصف الطويل
                short_description: product.short_description || '',
                meta_data: [] // إزالة البيانات الإضافية
            }));

            let processed = 0;
            cleanedProducts.forEach(p => {
                const request = store.put(p);
                request.onsuccess = () => {
                    processed++;
                    if (processed === cleanedProducts.length) {
                        console.log(`✅ تم تخزين ${processed} منتج`);
                        renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
                        showNotification(`تم تحميل ${processed} منتج بنجاح`, 'success');
                    }
                };
            });
        });

        // تخزين الفئات
        Livewire.on('store-categories', (data) => {
            if (!db) return;
            const tx = db.transaction("categories", "readwrite");
            const store = tx.objectStore("categories");

            data.categories.forEach(c => store.put(c));

            tx.oncomplete = () => {
                console.log("✅ تم تخزين الفئات");
                renderCategoriesFromIndexedDB();
                showNotification(`تم تحميل ${data.categories.length} فئة`, 'success');
            };
        });

        // تخزين المتغيرات
        Livewire.on('store-variations', (payload) => {
            if (!db) return;

            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");

            const cleanedVariations = payload.variations.map(v => ({
                ...v,
                product_id: v.product_id || payload.product_id,
                images: [], // إزالة الصور
                description: ''
            }));

            cleanedVariations.forEach(v => store.put(v));

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
                    name: `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'عميل',
                    email: customer.email || '',
                    phone: customer.billing?.phone || ''
                });
            });

            tx.oncomplete = () => {
                console.log("✅ تم تخزين العملاء");
                showNotification(`تم تحميل ${payload.customers.length} عميل`, 'success');
            };
        });

        // تخزين طرق الشحن
        Livewire.on('store-shipping-methods', (data) => {
            if (!db) return;
            const tx = db.transaction("shippingMethods", "readwrite");
            const store = tx.objectStore("shippingMethods");
            data.methods.forEach(method => store.put(method));
            tx.oncomplete = () => {
                console.log("✅ تم تخزين طرق الشحن");
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

            tx.oncomplete = () => {
                console.log("✅ تم تخزين مناطق الشحن");
            };
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
                        cost: parseFloat(m.settings?.cost?.value || 0)
                    });
                });
            });

            tx.oncomplete = () => {
                console.log("✅ تم تخزين طرق الشحن للمناطق");
            };
        });

        // استقبال المنتج الموجود من API
        Livewire.on('product-found-from-api', (data) => {
            hideLoadingIndicator();
            const product = data[0].product;
            console.log("✅ تم العثور على المنتج من API:", product);

            // تنظيف المنتج من الصور قبل التخزين
            const cleanedProduct = {
                ...product,
                images: [], // إزالة الصور لتسريع التخزين
                description: '',
                meta_data: []
            };

            // تخزين المنتج في IndexedDB
            const tx = db.transaction("products", "readwrite");
            const store = tx.objectStore("products");
            store.put(cleanedProduct);

            // إذا كان المنتج متغير وله متغيرات
            if (product.type === 'variable' && product.variations_full) {
                product.variations_full.forEach(variation => {
                    const cleanedVariation = {
                        ...variation,
                        images: [],
                        description: ''
                    };
                    store.put(cleanedVariation);
                });
            }

            tx.oncomplete = () => {
                console.log("✅ تم تخزين المنتج والمتغيرات في IndexedDB");
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);

                // معالجة المنتج حسب نوعه
                if (product.type === 'simple') {
                    addToCart(cleanedProduct);
                    showNotification(`تم العثور على "${product.name}" وإضافته للسلة`, 'success');
                } else if (product.type === 'variable') {
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
                showNotification("فشل في حفظ المنتج محلياً", 'error');
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
        Livewire.on('order-success', (data) => {
            console.log("🎉 === نجح إرسال الطلب ===");
            console.log("📊 بيانات النجاح:", data);

            hideLoadingIndicator();
            resetOrderButton();

            // مسح السلة
            if (db) {
                const tx = db.transaction("cart", "readwrite");
                tx.objectStore("cart").clear();
                tx.oncomplete = function () {
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

            const orderInfo = data[0] || {};
            const orderNumber = orderInfo.order_number || orderInfo.order_id || 'غير محدد';
            showNotification(`✅ تم إرسال الطلب بنجاح! رقم الطلب: ${orderNumber}`, 'success', 5000);
        });

        // فشل الطلب
        Livewire.on('order-failed', (data) => {
            console.log("❌ === فشل إرسال الطلب ===");
            console.log("📊 بيانات الفشل:", data);

            hideLoadingIndicator();
            resetOrderButton();

            let errorMessage = "فشل في إرسال الطلب";

            if (data && Array.isArray(data) && data[0]) {
                errorMessage = data[0].message || data[0].detailed_error || errorMessage;
            } else if (data && data.message) {
                errorMessage = data.message;
            }

            console.error("📄 رسالة الخطأ التفصيلية:", errorMessage);
            showNotification(errorMessage, 'error', 5000);
        });
    });

    // ============================================
    // تهيئة التطبيق
    // ============================================
    document.addEventListener('DOMContentLoaded', function () {
        console.log("🚀 تم تحميل صفحة POS");

        // إعداد متغيرات التحسين
        preventUnnecessaryReloads();

        // إعداد event listeners للنوافذ
        window.addEventListener('online', () => {
            showNotification("تم استعادة الاتصال بالإنترنت", 'success');
            console.log("🌐 تم استعادة الاتصال");
        });

        window.addEventListener('offline', () => {
            showNotification("تم فقدان الاتصال بالإنترنت. سيتم العمل في وضع عدم الاتصال", 'warning');
            console.log("🚫 تم فقدان الاتصال");
        });

        // إعداد اختصارات لوحة المفاتيح
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + F للتركيز على البحث
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.focus();
                    searchInput.select();
                }
            }

            // ESC لإغلاق المودالات
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('[data-flux-modal]');
                modals.forEach(modal => {
                    if (modal.style.display !== 'none') {
                        const modalName = modal.getAttribute('data-flux-modal');
                        if (modalName) {
                            try {
                                Flux.modal(modalName).close();
                            } catch (err) {
                                // تجاهل الأخطاء
                            }
                        }
                    }
                });
            }
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

    // التأكد من تهيئة الأزرار
    setTimeout(() => {
        setupConfirmOrderButton();
    }, 1000);

    function reinitializeDatabase() {
        console.log("🔄 إعادة تهيئة قاعدة البيانات...");

        if (db) {
            db.close();
        }

        const openRequest = indexedDB.open("POSProductsDB", 5);

        openRequest.onupgradeneeded = function (event) {
            db = event.target.result;
            createObjectStores(db);
            console.log("✅ تم إنشاء هياكل قاعدة البيانات");
        };

        openRequest.onsuccess = function (event) {
            db = event.target.result;
            console.log("✅ تم فتح قاعدة البيانات بنجاح");
            renderCartWithDebug();
        };

        openRequest.onerror = function () {
            console.error("❌ فشل في فتح قاعدة البيانات");
        };
    }

    function recreateCartElements() {
        console.log("🔄 إعادة إنشاء عناصر السلة...");

        // البحث عن الحاوية الرئيسية
        let cartContainer = document.getElementById("cartItemsContainer");

        if (!cartContainer) {
            console.log("🏗️ إنشاء حاوية السلة المفقودة...");

            // البحث عن الحاوية الأب
            const parentContainer = document.querySelector('.col-span-2 .bg-white');

            if (parentContainer) {
                // إنشاء حاوية السلة
                const newCartContainer = document.createElement('div');
                newCartContainer.id = 'cartItemsContainer';
                newCartContainer.className = 'space-y-2 overflow-y-auto max-h-[500px] flex-1';

                // إنشاء عنصر المجموع إذا لم يكن موجوداً
                let totalElement = document.getElementById("cartTotal");
                if (!totalElement) {
                    const totalDiv = document.createElement('div');
                    totalDiv.className = 'mt-4 border-t pt-4 text-right';
                    totalDiv.innerHTML = '<p class="font-bold text-xl">المجموع: <span id="cartTotal">0 ₪</span></p>';
                    parentContainer.appendChild(totalDiv);
                }

                // إدراج الحاوية في المكان الصحيح
                const titleElement = parentContainer.querySelector('h2');
                if (titleElement) {
                    titleElement.parentNode.insertBefore(newCartContainer, titleElement.nextSibling.nextSibling);
                } else {
                    parentContainer.appendChild(newCartContainer);
                }

                console.log("✅ تم إنشاء حاوية السلة");
            } else {
                console.error("❌ لم يتم العثور على الحاوية الأب للسلة");
            }
        }

        // التحقق من وجود زر إتمام الطلب
        let completeOrderBtn = document.getElementById("completeOrderBtn");
        if (!completeOrderBtn) {
            console.log("🏗️ إنشاء زر إتمام الطلب المفقود...");

            const parentContainer = document.querySelector('.col-span-2 .bg-white');
            if (parentContainer) {
                const btn = document.createElement('button');
                btn.id = 'completeOrderBtn';
                btn.className = 'mt-4 w-full bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600 transition-colors';
                btn.textContent = 'إتمام الطلب';
                btn.onclick = function () {
                    // إعادة ربط وظيفة إتمام الطلب
                    setupOrderButton();
                    // محاولة فتح المودال
                    try {
                        Flux.modal('confirm-order-modal').show();
                    } catch (e) {
                        alert('يرجى إعادة تحميل الصفحة لاستكمال الطلب');
                    }
                };

                parentContainer.appendChild(btn);
                console.log("✅ تم إنشاء زر إتمام الطلب");
            }
        }
    }

    function cleanupEventListeners() {
        console.log("🧹 تنظيف event listeners...");

        // إزالة listeners القديمة من المنتجات
        document.querySelectorAll('.product-card').forEach(card => {
            const newCard = card.cloneNode(true);
            card.parentNode.replaceChild(newCard, card);
        });

        // إعادة ربط listeners الجديدة
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('click', function () {
                const productId = this.getAttribute('data-product-id');
                if (productId) {
                    // البحث عن المنتج في IndexedDB
                    const tx = db.transaction("products", "readonly");
                    const store = tx.objectStore("products");
                    const request = store.get(parseInt(productId));

                    request.onsuccess = function () {
                        const product = request.result;
                        if (product) {
                            addToCartWithDebug(product);
                        }
                    };
                }
            });
        });

        console.log("✅ تم تنظيف وإعادة ربط event listeners");
    }

    function cleanupCorruptedCartData() {
        console.log("🧹 تنظيف بيانات السلة التالفة...");

        if (!db) {
            console.error("❌ قاعدة البيانات غير متاحة");
            return;
        }

        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const request = store.getAll();

        request.onsuccess = function () {
            const cartItems = request.result;
            console.log("📦 فحص", cartItems.length, "عنصر في السلة");

            let cleanedItems = [];
            let corruptedCount = 0;

            cartItems.forEach(item => {
                // فحص صحة البيانات
                if (item.id && item.name && typeof item.price !== 'undefined' && item.quantity > 0) {
                    // تنظيف البيانات
                    const cleanedItem = {
                        id: parseInt(item.id),
                        name: String(item.name),
                        price: parseFloat(item.price) || 0,
                        quantity: parseInt(item.quantity) || 1,
                        image: item.image || '',
                        type: item.type || 'simple',
                        sku: item.sku || '',
                        added_at: item.added_at || new Date().toISOString()
                    };
                    cleanedItems.push(cleanedItem);
                } else {
                    console.warn("⚠️ عنصر تالف:", item);
                    corruptedCount++;
                    // حذف العنصر التالف
                    store.delete(item.id);
                }
            });

            if (corruptedCount > 0) {
                console.log(`🗑️ تم حذف ${corruptedCount} عنصر تالف`);

                // إعادة حفظ العناصر السليمة
                cleanedItems.forEach(item => {
                    store.put(item);
                });
            }

            console.log("✅ تم تنظيف بيانات السلة");
            renderCartWithDebug();
        };
    }

    function fixCartStyling() {
        console.log("🎨 إصلاح تصميم السلة...");

        const cartContainer = document.getElementById("cartItemsContainer");
        if (cartContainer) {
            // التأكد من وجود الأنماط الصحيحة
            cartContainer.className = "space-y-2 overflow-y-auto max-h-[500px] flex-1";

            // إضافة أنماط مخصصة إذا لزم الأمر
            const style = document.createElement('style');
            style.textContent = `
            #cartItemsContainer {
                min-height: 200px;
                background: #ffffff;
                border-radius: 8px;
                padding: 8px;
            }

            .cart-item {
                transition: all 0.3s ease;
                border: 1px solid #e5e7eb;
                margin-bottom: 8px;
            }

            .cart-item:hover {
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            #cartTotal {
                color: #059669;
                font-weight: bold;
            }
        `;

            if (!document.head.querySelector('#cart-custom-styles')) {
                style.id = 'cart-custom-styles';
                document.head.appendChild(style);
            }

            console.log("✅ تم إصلاح تصميم السلة");
        }
    }

    function emergencyCartFix() {
        console.log("🚨 === بدء الإصلاح الطارئ للسلة ===");

        try {
            // 1. إعادة تهيئة قاعدة البيانات
            console.log("1️⃣ إعادة تهيئة قاعدة البيانات...");
            reinitializeDatabase();

            setTimeout(() => {
                // 2. إعادة إنشاء عناصر HTML
                console.log("2️⃣ إعادة إنشاء عناصر HTML...");
                recreateCartElements();

                // 3. تنظيف البيانات التالفة
                console.log("3️⃣ تنظيف البيانات التالفة...");
                cleanupCorruptedCartData();

                // 4. إصلاح التصميم
                console.log("4️⃣ إصلاح التصميم...");
                fixCartStyling();

                // 5. تنظيف event listeners
                console.log("5️⃣ تنظيف event listeners...");
                cleanupEventListeners();

                setTimeout(() => {
                    // 6. اختبار نهائي
                    console.log("6️⃣ اختبار نهائي...");
                    fullCartDiagnostic();

                    console.log("✅ === انتهى الإصلاح الطارئ ===");
                    alert("تم إصلاح السلة! جرب إضافة منتج الآن.");

                }, 1000);

            }, 1000);

        } catch (error) {
            console.error("❌ فشل في الإصلاح الطارئ:", error);
            alert("فشل في الإصلاح. يرجى إعادة تحميل الصفحة.");
        }
    }

    // أضف هذا
    function addVariationUnderParentToCart(parent, variation) {
        // parent: كائن المنتج الأب (id, name, ...)
        // variation: كائن المتغيّر (id, name, price, sku, attributes, product_id, ...)

        if (!parent || !parent.id || !variation || !variation.id) {
            showNotification("بيانات المتغيّر/الأب غير مكتملة", 'error');
            return;
        }

        const cartTx = db.transaction("cart", "readwrite");
        const cartStore = cartTx.objectStore("cart");
        const getParentCartItem = cartStore.get(parent.id);

        getParentCartItem.onsuccess = function () {
            const existingParent = getParentCartItem.result;

            // نحضّر عنصر السلة للأب بصيغة Grouped
            if (!existingParent) {
                const newParentItem = {
                    id: parent.id,
                    name: parent.name,
                    type: 'variable',         // مهم لعرض السلة بشكل متداخل
                    quantity: 1,              // لن يُستخدم للحساب، لكن نتركه 1
                    price: 0,                 // الإجمالي يُحسب من الأبناء
                    variations: []            // قائمة الأبناء داخل هذا الأب
                };
                // أدخل/حدّث الابن
                newParentItem.variations.push({
                    id: variation.id,
                    name: variation.name || (parent.name + ' - ' + (variation.sku || '')),
                    price: parseFloat(variation.price || 0),
                    quantity: 1,
                    sku: variation.sku || '',
                    attributes: variation.attributes || []
                });
                cartStore.put(newParentItem);
            } else {
                if (!Array.isArray(existingParent.variations)) {
                    existingParent.variations = [];
                }
                const idx = existingParent.variations.findIndex(v => v.id === variation.id);
                if (idx > -1) {
                    existingParent.variations[idx].quantity += 1;
                } else {
                    existingParent.variations.push({
                        id: variation.id,
                        name: variation.name || (parent.name + ' - ' + (variation.sku || '')),
                        price: parseFloat(variation.price || 0),
                        quantity: 1,
                        sku: variation.sku || '',
                        attributes: variation.attributes || []
                    });
                }
                cartStore.put(existingParent);
            }

            renderCart(parent.id); // لإعادة الرسم
            showNotification(`تم إضافة "${variation.name || variation.sku}" تحت "${parent.name}"`, 'success');
        };

        getParentCartItem.onerror = function () {
            showNotification("فشل الوصول إلى السلة", 'error');
        };
    }

    // (اختياري) تعريضها للعالمية:
    window.addVariationUnderParentToCart = addVariationUnderParentToCart;

    console.log("✅ تم تحميل جميع وظائف نظام POS المحسن");
</script>

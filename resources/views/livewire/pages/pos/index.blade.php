<div>
    <!-- Modals -->
    <flux:modal name="variations-modal" style="min-width: 70%">
        <div class="space-y-6">
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                <div id="variationsTableBody"></div>
            </div>
            <div class="flex justify-end">
                <flux:button type="button" variant="primary" onclick="Flux.modal('variations-modal').close()">إغلاق</flux:button>
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

    <flux:modal name="add-customer-modal">
        <div class="space-y-4">
            <h3 class="text-lg font-bold">إضافة زبون جديد</h3>
            <input id="newCustomerName" type="text" placeholder="اسم الزبون"
                   class="w-full border rounded px-3 py-2" />
            <div class="flex justify-end gap-2">
                <flux:button variant="danger" onclick="Flux.modal('add-customer-modal').close()">إلغاء</flux:button>
                <flux:button variant="primary" onclick="addNewCustomer()">حفظ</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Sync Progress Modal -->
    <flux:modal name="sync-progress-modal" style="min-width: 500px">
        <div class="space-y-4">
            <h3 class="text-lg font-bold text-center">مزامنة البيانات</h3>

            <div class="bg-gray-200 rounded-full h-4 overflow-hidden">
                <div id="syncProgressBar" class="bg-blue-500 h-full transition-all duration-300" style="width: 0%"></div>
            </div>

            <div class="text-center">
                <p id="syncProgressText" class="text-sm text-gray-600">جاري بدء المزامنة...</p>
                <p id="syncProgressDetails" class="text-xs text-gray-500 mt-1"></p>
            </div>

            <div class="text-center">
                <flux:button id="cancelSyncBtn" variant="danger" onclick="cancelSync()">إلغاء المزامنة</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Main Content -->
    <div class="grid gap-4 grid-cols-6">
        <!-- Products Section -->
        <div class="col-span-4">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <!-- Search and Controls -->
                <div class="flex items-center gap-2 mb-4">
                    <flux:input id="searchInput" placeholder="البحث في المنتجات..." icon="magnifying-glass" />
                    <flux:button>Scan</flux:button>
                    <flux:button id="syncButton" variant="primary">مزامنة</flux:button>
                    <flux:button id="backgroundSyncBtn" variant="outline">مزامنة خلفية</flux:button>
                </div>

                <!-- Status Bar -->
                <div id="statusBar" class="mb-4 p-2 bg-gray-100 rounded text-sm text-gray-600 hidden">
                    <div class="flex justify-between items-center">
                        <span id="statusText">جاهز</span>
                        <span id="productsCount">المنتجات: 0</span>
                    </div>
                </div>

                <!-- Categories -->
                <div class="mt-4">
                    <div id="categoriesContainer" class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                        <!-- التصنيفات سيتم تحميلها من IndexedDB عبر JS -->
                    </div>
                </div>

                <div class="mt-4">
                    <flux:separator />
                </div>

                <!-- Loading Indicator -->
                <div id="productsLoader" class="text-center py-8 hidden">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <p class="mt-2 text-gray-600">جاري تحميل المنتجات...</p>
                </div>

                <!-- Products Grid -->
                <div class="mt-4 h-full bg-gray-200 p-4 rounded-lg shadow-md">
                    <div id="productsContainer" class="grid grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                        <!-- المنتجات ستعرض من IndexedDB هنا -->
                    </div>

                    <!-- Load More Button -->
                    <div class="text-center mt-4">
                        <flux:button id="loadMoreBtn" variant="outline" class="hidden">تحميل المزيد</flux:button>
                    </div>

                    <!-- Pagination Info -->
                    <div id="paginationInfo" class="text-center mt-2 text-sm text-gray-600">
                        <!-- سيتم ملؤها ديناميكياً -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Cart Section -->
        <div class="col-span-2 h-full">
            <div class="bg-white p-4 rounded-lg shadow-md h-full flex flex-col">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium">إجمالي المبيعات</h2>
                    <flux:button onclick="clearCart()" variant="danger" size="sm">
                        🧹 مسح الكل
                    </flux:button>
                </div>

                <!-- Cart Items -->
                <div id="cartItemsContainer" class="space-y-2 overflow-y-auto max-h-[500px] flex-1">
                    <!-- سيتم ملؤها ديناميكياً -->
                </div>

                <!-- Cart Total -->
                <div class="mt-4 border-t pt-4 text-right">
                    <p class="font-bold text-xl">المجموع: <span id="cartTotal">0 ₪</span></p>
                </div>

                <!-- Complete Order Button -->
                <flux:button type="button" id="completeOrderBtn" class="mt-4 w-full" variant="primary">
                    إتمام الطلب
                </flux:button>
            </div>
        </div>
    </div>
</div>

<script>
    // 📍 المتغيرات العامة
    let db;
    const dbName = "POSProductsDB";
    const dbVersion = 6; // زيادة الإصدار للتحديثات الجديدة
    let selectedCategoryId = null;
    let currentSearchTerm = '';
    let cart = [];
    let isBackgroundSyncing = false;
    let syncCancelled = false;

    // 📍 إعدادات الأداء
    const PRODUCTS_PER_PAGE = 20;
    const CACHE_DURATION = 5 * 60 * 1000; // 5 دقائق
    const DEBOUNCE_DELAY = 500;

    // 📍 تهيئة التطبيق عند التحميل
    window.onload = function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.focus();
        }

        // إظهار شريط الحالة
        const statusBar = document.getElementById('statusBar');
        if (statusBar) {
            statusBar.classList.remove('hidden');
        }
    }

    // 📍 إعداد IndexedDB مع تحسينات
    function initDB() {
        return new Promise((resolve, reject) => {
            const openRequest = indexedDB.open(dbName, dbVersion);

            openRequest.onupgradeneeded = function(event) {
                db = event.target.result;

                // إنشاء المخازن مع الفهارس المحسنة
                const stores = [
                    { name: 'products', keyPath: 'id', indexes: [
                            { name: 'name', field: 'name', unique: false },
                            { name: 'category', field: 'categories', unique: false, multiEntry: true },
                            { name: 'type', field: 'type', unique: false }
                        ]},
                    { name: 'categories', keyPath: 'id' },
                    { name: 'variations', keyPath: 'id', indexes: [
                            { name: 'product_id', field: 'product_id', unique: false }
                        ]},
                    { name: 'cart', keyPath: 'id' },
                    { name: 'customers', keyPath: 'id' },
                    { name: 'shippingMethods', keyPath: 'id' },
                    { name: 'shippingZones', keyPath: 'id' },
                    { name: 'shippingZoneMethods', keyPath: 'id', indexes: [
                            { name: 'zone_id', field: 'zone_id', unique: false }
                        ]},
                    { name: 'pendingOrders', autoIncrement: true },
                    { name: 'syncMetadata', keyPath: 'key' }
                ];

                stores.forEach(storeConfig => {
                    let store;

                    if (!db.objectStoreNames.contains(storeConfig.name)) {
                        if (storeConfig.autoIncrement) {
                            store = db.createObjectStore(storeConfig.name, { autoIncrement: true });
                        } else {
                            store = db.createObjectStore(storeConfig.name, { keyPath: storeConfig.keyPath });
                        }

                        // إضافة الفهارس
                        if (storeConfig.indexes) {
                            storeConfig.indexes.forEach(index => {
                                store.createIndex(index.name, index.field, {
                                    unique: index.unique || false,
                                    multiEntry: index.multiEntry || false
                                });
                            });
                        }
                    }
                });
            };

            openRequest.onsuccess = function(event) {
                db = event.target.result;
                console.log("✅ IndexedDB initialized successfully");
                resolve(db);
            };

            openRequest.onerror = function() {
                console.error("❌ Error opening IndexedDB");
                reject(openRequest.error);
            };
        });
    }

    // 📍 عرض المنتجات مع تحسينات الأداء
    function renderProductsFromIndexedDB(searchTerm = '', categoryId = null, page = 1) {
        if (!db) return;

        showLoader();

        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.getAll();

        request.onsuccess = function() {
            const products = request.result;
            const container = document.getElementById("productsContainer");
            if (!container) return;

            // فلترة المنتجات
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

            // Pagination
            const startIndex = (page - 1) * PRODUCTS_PER_PAGE;
            const endIndex = startIndex + PRODUCTS_PER_PAGE;
            const paginatedProducts = filtered.slice(startIndex, endIndex);

            // مسح المحتوى السابق في الصفحة الأولى فقط
            if (page === 1) {
                container.innerHTML = '';
            }

            if (paginatedProducts.length === 0 && page === 1) {
                container.innerHTML = '<p class="text-center text-gray-500 col-span-4">لا يوجد منتجات مطابقة</p>';
                hideLoader();
                updatePaginationInfo(0, 0, filtered.length);
                return;
            }

            // إنشاء عناصر المنتجات مع تحسينات
            const fragment = document.createDocumentFragment();

            paginatedProducts.forEach(item => {
                const div = document.createElement("div");
                div.classList.add("bg-white", "rounded-lg", "shadow-md", "relative", "cursor-pointer", "hover:shadow-lg", "transition-shadow", "duration-200");
                div.dataset.productId = item.id;

                div.onclick = function() {
                    handleProductClick(item);
                };

                // استخدام lazy loading للصور
                const imageSrc = item.images?.[0]?.src || '/images/placeholder.jpg';

                div.innerHTML = `
                <div class="relative">
                    <span class="absolute top-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs text-center py-1 z-10">
                        ${item.id || ''}
                    </span>
                    <img src="${imageSrc}" alt="${item.name || ''}"
                        class="w-full object-cover rounded-t-lg lazy-load"
                        style="height: 200px;"
                        loading="lazy"
                        onerror="this.src='/images/placeholder.jpg'">
                    <span class="absolute bottom-10 left-1 bg-black bg-opacity-70 text-white text-sm px-2 py-1 rounded z-10">
                        ${item.price || '0'} ₪
                    </span>
                </div>
                <div class="p-2">
                    <div class="bg-gray-200 rounded p-2">
                        <p class="font-bold text-sm text-center truncate" title="${item.name || ''}">
                            ${item.name || ''}
                        </p>
                        ${item.stock_quantity !== undefined ?
                    `<p class="text-xs text-center mt-1 ${item.stock_quantity > 0 ? 'text-green-600' : 'text-red-600'}">
                                المخزون: ${item.stock_quantity}
                            </p>` : ''
                }
                    </div>
                </div>
            `;

                fragment.appendChild(div);
            });

            container.appendChild(fragment);

            // تحديث معلومات التصفح
            const totalPages = Math.ceil(filtered.length / PRODUCTS_PER_PAGE);
            updatePaginationInfo(page, totalPages, filtered.length);

            // إظهار/إخفاء زر التحميل
            updateLoadMoreButton(page, totalPages);

            hideLoader();
            updateStatusBar(`عرض ${paginatedProducts.length} من ${filtered.length} منتج`);
        };

        request.onerror = function() {
            console.error("❌ Failed to fetch products from IndexedDB");
            hideLoader();
        };
    }

    // 📍 معالجة النقر على المنتج
    function handleProductClick(product) {
        if (product.type === 'variable' && Array.isArray(product.variations)) {
            loadAndShowVariations(product);
        } else if (product.type === 'simple') {
            addToCart(product);
        }
    }

    // 📍 تحميل وعرض المتغيرات
    function loadAndShowVariations(product) {
        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");

        const variationProducts = [];
        let fetched = 0;

        if (!product.variations || product.variations.length === 0) {
            showError('لا توجد متغيرات لهذا المنتج');
            return;
        }

        product.variations.forEach(id => {
            const req = store.get(id);
            req.onsuccess = function() {
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

    // 📍 عرض التصنيفات
    function renderCategoriesFromIndexedDB() {
        if (!db) return;

        const tx = db.transaction("categories", "readonly");
        const store = tx.objectStore("categories");
        const request = store.getAll();

        request.onsuccess = function() {
            const categories = request.result;
            const container = document.getElementById("categoriesContainer");
            if (!container) return;

            container.innerHTML = '';

            // زر "الكل"
            const allBtn = createCategoryButton("الكل", null, selectedCategoryId === null);
            container.appendChild(allBtn);

            // أزرار التصنيفات
            categories.forEach(item => {
                const btn = createCategoryButton(item.name, item.id, selectedCategoryId === item.id);
                container.appendChild(btn);
            });
        };

        request.onerror = () => {
            console.error("❌ Failed to load categories");
        };
    }

    // 📍 إنشاء زر تصنيف
    function createCategoryButton(name, id, isActive) {
        const btn = document.createElement("button");
        btn.innerText = name;
        btn.classList.add("px-3", "py-1", "text-sm", "rounded", "whitespace-nowrap", "transition-colors");

        if (isActive) {
            btn.classList.add("bg-blue-500", "text-white");
        } else {
            btn.classList.add("bg-white", "border", "border-gray-300", "hover:bg-gray-50");
        }

        btn.onclick = () => {
            selectedCategoryId = id;
            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId, 1);
            renderCategoriesFromIndexedDB(); // إعادة رسم لتحديث الحالة النشطة
        };

        return btn;
    }

    // 📍 تحديث معلومات التصفح
    function updatePaginationInfo(currentPage, totalPages, totalProducts) {
        const paginationInfo = document.getElementById('paginationInfo');
        if (paginationInfo) {
            if (totalPages > 1) {
                paginationInfo.textContent = `صفحة ${currentPage} من ${totalPages} (${totalProducts} منتج)`;
            } else if (totalProducts > 0) {
                paginationInfo.textContent = `${totalProducts} منتج`;
            } else {
                paginationInfo.textContent = '';
            }
        }
    }

    // 📍 تحديث زر التحميل المزيد
    function updateLoadMoreButton(currentPage, totalPages) {
        const loadMoreBtn = document.getElementById('loadMoreBtn');
        if (loadMoreBtn) {
            if (currentPage < totalPages) {
                loadMoreBtn.classList.remove('hidden');
                loadMoreBtn.onclick = () => {
                    renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId, currentPage + 1);
                };
            } else {
                loadMoreBtn.classList.add('hidden');
            }
        }
    }

    // 📍 عرض/إخفاء اللودر
    function showLoader() {
        const loader = document.getElementById('productsLoader');
        if (loader) loader.classList.remove('hidden');
    }

    function hideLoader() {
        const loader = document.getElementById('productsLoader');
        if (loader) loader.classList.add('hidden');
    }

    // 📍 تحديث شريط الحالة
    function updateStatusBar(message) {
        const statusText = document.getElementById('statusText');
        if (statusText) {
            statusText.textContent = message;
        }
    }

    // 📍 إعداد البحث مع Debouncing
    function setupSearch() {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                currentSearchTerm = this.value;
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId, 1);
            }, DEBOUNCE_DELAY);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performQuickSearch(this.value.trim());
            }
        });
    }

    // 📍 البحث السريع بالباركود
    function performQuickSearch(term) {
        if (!term) return;

        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.getAll();

        request.onsuccess = function() {
            const products = request.result;
            const termLower = term.toLowerCase();

            const matched = products.find(item => {
                const nameMatch = item.name?.toLowerCase().includes(termLower);
                const idMatch = item.id?.toString() === term;
                const skuMatch = item.sku?.toLowerCase() === termLower;
                return nameMatch || idMatch || skuMatch;
            });

            if (!matched) {
                showError("لا يوجد منتج مطابق");
                return;
            }

            handleProductClick(matched);
        };
    }

    // 📍 إدارة السلة
    function addToCart(product) {
        if (!db) return;

        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const getRequest = store.get(product.id);

        getRequest.onsuccess = function() {
            const existing = getRequest.result;

            if (existing) {
                existing.quantity += 1;
                store.put(existing);
            } else {
                store.put({
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price) || 0,
                    image: product.images?.[0]?.src || '',
                    quantity: 1,
                    type: product.type || 'simple'
                });
            }

            console.log("✅ تم إضافة المنتج إلى السلة:", product.name);
            renderCart(product.id);

            // تأثير بصري
            showSuccessMessage(`تم إضافة ${product.name} إلى السلة`);

            // التمرير للسلة
            setTimeout(() => {
                const container = document.getElementById("cartItemsContainer");
                if (container) {
                    container.scrollTo({
                        top: container.scrollHeight,
                        behavior: 'smooth'
                    });
                }
            }, 100);
        };

        getRequest.onerror = function() {
            console.error("❌ فشل في إضافة المنتج إلى السلة");
            showError("فشل في إضافة المنتج إلى السلة");
        };
    }

    // 📍 عرض السلة
    function renderCart(highlightId = null) {
        if (!db) return;

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
                total += (item.price || 0) * (item.quantity || 1);

                const div = document.createElement("div");
                div.id = `cart-item-${item.id}`;
                div.className = "flex justify-between items-center bg-gray-100 p-3 rounded transition duration-300 hover:bg-gray-200";

                div.innerHTML = `
                <div class="flex items-center gap-3">
                    <img src="${item.image || '/images/placeholder.jpg'}"
                         alt="${item.name}"
                         class="w-12 h-12 object-cover rounded border"
                         onerror="this.src='/images/placeholder.jpg'">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate" title="${item.name}">${item.name}</p>
                        <div class="flex items-center gap-2 mt-1">
                            <button onclick="updateQuantity(${item.id}, -1)"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-2 py-1 rounded text-xs">
                                −
                            </button>
                            <span class="bg-white px-2 py-1 rounded text-xs font-medium">${item.quantity}</span>
                            <button onclick="updateQuantity(${item.id}, 1)"
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-2 py-1 rounded text-xs">
                                +
                            </button>
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <p class="font-bold text-gray-800">${((item.price || 0) * (item.quantity || 1)).toFixed(2)} ₪</p>
                    <button onclick="removeFromCart(${item.id})"
                            class="text-red-500 hover:text-red-700 text-xs mt-1">
                        🗑️ حذف
                    </button>
                </div>
            `;

                container.appendChild(div);

                if (highlightId && item.id === highlightId) {
                    highlightElement = div;
                }
            });

            totalElement.textContent = total.toFixed(2) + " ₪";

            // تأثير التسليط الضوء
            if (highlightElement) {
                highlightElement.classList.add("bg-yellow-200");
                setTimeout(() => {
                    highlightElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                    setTimeout(() => {
                        highlightElement.classList.remove("bg-yellow-200");
                    }, 500);
                }, 100);
            }

            // تحديث عدد العناصر في شريط الحالة
            updateStatusBar(`${cartItems.length} عنصر في السلة`);
        };

        request.onerror = function() {
            console.error("❌ فشل في تحميل محتوى السلة");
        };
    }

    // 📍 تحديث كمية المنتج
    function updateQuantity(productId, change) {
        if (!db) return;

        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const getRequest = store.get(productId);

        getRequest.onsuccess = function() {
            const item = getRequest.result;
            if (!item) return;

            item.quantity = Math.max(0, (item.quantity || 0) + change);

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

    // 📍 حذف من السلة
    function removeFromCart(productId) {
        if (!db) return;

        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const request = store.delete(productId);

        request.onsuccess = function() {
            console.log("🗑️ تم حذف المنتج من السلة");
            renderCart();
        };

        request.onerror = function() {
            console.error("❌ فشل في حذف المنتج من السلة");
        };
    }

    // 📍 مسح السلة
    function clearCart() {
        if (!db) return;

        if (!confirm('هل أنت متأكد من مسح جميع المنتجات من السلة؟')) {
            return;
        }

        const tx = db.transaction("cart", "readwrite");
        const store = tx.objectStore("cart");
        const clearRequest = store.clear();

        clearRequest.onsuccess = function() {
            console.log("🧹 تم مسح جميع المنتجات من السلة");
            renderCart();
            showSuccessMessage("تم مسح السلة بنجاح");
        };

        clearRequest.onerror = function() {
            console.error("❌ فشل في مسح السلة");
            showError("فشل في مسح السلة");
        };
    }

    // 📍 المزامنة المحسنة
    function startBackgroundSync() {
        if (isBackgroundSyncing) {
            showError('المزامنة قيد التشغيل بالفعل');
            return;
        }

        isBackgroundSyncing = true;
        syncCancelled = false;

        showSyncProgress();
        updateSyncProgress(0, 'جاري بدء المزامنة...');

        // إرسال طلب بدء المزامنة
        Livewire.dispatch('start-background-sync');
    }

    function showSyncProgress() {
        Flux.modal('sync-progress-modal').show();
    }

    function updateSyncProgress(percentage, text, details = '') {
        const progressBar = document.getElementById('syncProgressBar');
        const progressText = document.getElementById('syncProgressText');
        const progressDetails = document.getElementById('syncProgressDetails');

        if (progressBar) {
            progressBar.style.width = `${Math.min(100, Math.max(0, percentage))}%`;
        }

        if (progressText) {
            progressText.textContent = text;
        }

        if (progressDetails && details) {
            progressDetails.textContent = details;
        }
    }

    function cancelSync() {
        syncCancelled = true;
        isBackgroundSyncing = false;
        Flux.modal('sync-progress-modal').close();
        showError('تم إلغاء المزامنة');
    }

    // 📍 عرض modal المتغيرات
    function showVariationsModal(variations) {
        const modal = Flux.modal('variations-modal');
        const container = document.getElementById("variationsTableBody");
        if (!container) return;

        container.innerHTML = '';

        if (variations.length === 0) {
            const message = document.createElement("div");
            message.className = "text-center text-gray-500 py-8";
            message.innerHTML = `
            <div class="text-4xl mb-4">📦</div>
            <p class="text-lg font-semibold">لا يوجد متغيرات متاحة</p>
        `;
            container.appendChild(message);
            modal.show();
            return;
        }

        const grid = document.createElement("div");
        grid.className = "grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4";

        variations.forEach(item => {
            const card = document.createElement("div");
            card.className = "relative bg-white rounded-lg shadow-md overflow-hidden cursor-pointer hover:shadow-xl transition-all duration-200 border";
            card.onclick = () => addVariationToCart(item.id);

            const attributes = item.attributes?.map(a => a.option).join(', ') || '';
            const stock = item.stock_quantity !== undefined ? item.stock_quantity : '∞';
            const stockColor = (item.stock_quantity || 0) > 0 ? 'text-green-600' : 'text-red-600';

            card.innerHTML = `
            <div class="relative">
                <span class="absolute top-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs text-center py-1 z-10">
                    ${item.id || ''}
                </span>
                <img src="${item.images?.[0]?.src || '/images/placeholder.jpg'}"
                     alt="${item.name || ''}"
                     class="w-full object-cover"
                     style="height: 160px;"
                     loading="lazy"
                     onerror="this.src='/images/placeholder.jpg'">
                <span class="absolute bottom-2 left-2 bg-black bg-opacity-70 text-white text-sm px-2 py-1 rounded z-10">
                    ${item.price || '0'} ₪
                </span>
            </div>
            <div class="p-3">
                <p class="font-bold text-sm text-center truncate mb-1" title="${item.name || ''}">
                    ${item.name || ''}
                </p>
                ${attributes ? `<p class="text-xs text-gray-600 text-center mb-1">${attributes}</p>` : ''}
                <p class="text-xs text-center ${stockColor}">المخزون: ${stock}</p>
            </div>
        `;

            grid.appendChild(card);
        });

        container.appendChild(grid);
        modal.show();
    }

    // 📍 إضافة المتغير إلى السلة
    function addVariationToCart(variationId) {
        const tx = db.transaction("products", "readonly");
        const store = tx.objectStore("products");
        const request = store.get(variationId);

        request.onsuccess = function() {
            const variation = request.result;

            if (!variation || !variation.id) {
                console.error("❌ Variation not found:", variation);
                showError("المتغير غير موجود");
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
                        price: parseFloat(variation.price) || 0,
                        quantity: 1,
                        image: variation.images?.[0]?.src || '/images/placeholder.jpg',
                        type: 'variation'
                    });
                }

                renderCart();
                Flux.modal('variations-modal').close();
                showSuccessMessage(`تم إضافة ${variation.name} إلى السلة`);
            };
        };

        request.onerror = function() {
            console.error("❌ Failed to fetch variation by ID");
            showError("فشل في جلب بيانات المتغير");
        };
    }

    // 📍 دوال المساعدة للرسائل
    function showSuccessMessage(message) {
        // يمكن تحسينها لاحقاً بـ toast notifications
        console.log("✅ " + message);
    }

    function showError(message) {
        console.error("❌ " + message);
        alert("خطأ: " + message);
    }

    // 📍 إعداد أحداث Livewire
    document.addEventListener('livewire:init', () => {
        // تحميل المنتجات
        Livewire.on('products-loaded', (data) => {
            const { products, currentPage, totalPages, append, totalProducts } = data;

            if (products && products.length > 0) {
                storeProductsInDB(products);
            }

            updateStatusBar(`تم تحميل ${products?.length || 0} منتج`);
        });

        // تخزين المنتجات
        Livewire.on('store-products', (data) => {
            if (data.products && data.products.length > 0) {
                storeProductsInDB(data.products);
            }
        });

        // تخزين التصنيفات
        Livewire.on('store-categories', (data) => {
            if (data.categories && data.categories.length > 0) {
                storeCategoriesInDB(data.categories);
            }
        });

        // تخزين المتغيرات
        Livewire.on('store-variations', (payload) => {
            if (payload.variations && payload.variations.length > 0) {
                storeVariationsInDB(payload.variations, payload.product_id);
            }
        });

        // تخزين العملاء
        Livewire.on('store-customers', (payload) => {
            if (payload.customers && payload.customers.length > 0) {
                storeCustomersInDB(payload.customers);
            }
        });

        // أحداث المزامنة
        Livewire.on('sync-started', () => {
            updateSyncProgress(5, 'تم بدء المزامنة...');
        });

        Livewire.on('sync-progress', (data) => {
            const { page, totalPages, hasMore, progress } = data;
            const progressText = `صفحة ${page}${totalPages ? ` من ${totalPages}` : ''}`;
            const details = hasMore ? 'جاري المزامنة...' : 'اكتملت المزامنة';

            updateSyncProgress(progress || 0, progressText, details);

            if (!hasMore) {
                setTimeout(() => {
                    isBackgroundSyncing = false;
                    Flux.modal('sync-progress-modal').close();
                    showSuccessMessage('تمت المزامنة بنجاح');
                    renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId, 1);
                    renderCategoriesFromIndexedDB();
                }, 1000);
            }
        });

        Livewire.on('sync-error', (data) => {
            isBackgroundSyncing = false;
            Flux.modal('sync-progress-modal').close();
            showError('حدث خطأ في المزامنة: ' + data.message);
        });

        // عرض modal المتغيرات
        Livewire.on('show-variations-modal', (data) => {
            showVariationsModal(data.variations || []);
        });

        // نجاح الطلب
        Livewire.on('order-success', () => {
            clearCart();
            Flux.modal('confirm-order-modal').close();
            showSuccessMessage('تم إنشاء الطلب بنجاح');
        });

        // فشل الطلب
        Livewire.on('order-failed', (data) => {
            showError('فشل في إنشاء الطلب: ' + (data.message || ''));
        });

        // أخطاء API
        Livewire.on('api-error', (data) => {
            showError(data.message || 'حدث خطأ غير متوقع');
        });
    });

    // 📍 دوال تخزين البيانات
    function storeProductsInDB(products) {
        if (!db || !products) return;

        const tx = db.transaction("products", "readwrite");
        const store = tx.objectStore("products");

        products.forEach(p => {
            store.put(p);
        });

        tx.oncomplete = () => {
            console.log("✅ تم تخزين العملاء");
            renderCustomersDropdown();
        };
    }

    // 📍 إعداد الأحداث عند التحميل
    document.addEventListener("livewire:navigated", async () => {
        try {
            // تهيئة قاعدة البيانات
            await initDB();

            // إعداد واجهة المستخدم
            setupSearch();
            setupEventListeners();

            // تحميل البيانات الأولية
            await loadInitialData();

            // عرض البيانات
            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId, 1);
            renderCategoriesFromIndexedDB();
            renderCart();

            updateStatusBar('جاهز');

        } catch (error) {
            console.error("❌ Error during initialization:", error);
            showError('حدث خطأ في تهيئة التطبيق');
        }
    });

    // 📍 تحميل البيانات الأولية
    async function loadInitialData() {
        if (!db) return;

        // فحص ما إذا كانت البيانات موجودة
        const checks = await Promise.all([
            checkDataExists('products'),
            checkDataExists('categories'),
            checkDataExists('customers')
        ]);

        const [hasProducts, hasCategories, hasCustomers] = checks;

        // تحميل البيانات المفقودة
        if (!hasProducts) {
            Livewire.dispatch('fetch-products-from-api');
        }

        if (!hasCategories) {
            Livewire.dispatch('fetch-categories-from-api');
        }

        if (!hasCustomers) {
            Livewire.dispatch('fetch-customers-from-api');
        }

        // تحميل بيانات الشحن
        Livewire.dispatch('fetch-shipping-zones-and-methods');
    }

    // 📍 فحص وجود البيانات
    function checkDataExists(storeName) {
        return new Promise((resolve) => {
            const tx = db.transaction(storeName, "readonly");
            const store = tx.objectStore(storeName);
            const countRequest = store.count();

            countRequest.onsuccess = () => {
                resolve(countRequest.result > 0);
            };

            countRequest.onerror = () => {
                resolve(false);
            };
        });
    }

    // 📍 إعداد مستمعي الأحداث
    function setupEventListeners() {
        // زر المزامنة العادية
        const syncButton = document.getElementById('syncButton');
        if (syncButton) {
            syncButton.addEventListener('click', performFullSync);
        }

        // زر المزامنة الخلفية
        const backgroundSyncBtn = document.getElementById('backgroundSyncBtn');
        if (backgroundSyncBtn) {
            backgroundSyncBtn.addEventListener('click', startBackgroundSync);
        }

        // زر إكمال الطلب
        const completeOrderBtn = document.getElementById('completeOrderBtn');
        if (completeOrderBtn) {
            completeOrderBtn.addEventListener('click', prepareOrderModal);
        }

        // زر تأكيد الطلب
        const confirmOrderBtn = document.getElementById('confirmOrderSubmitBtn');
        if (confirmOrderBtn) {
            confirmOrderBtn.addEventListener('click', submitOrder);
        }
    }

    // 📍 المزامنة الكاملة
    function performFullSync() {
        if (isBackgroundSyncing) {
            showError('المزامنة قيد التشغيل بالفعل');
            return;
        }

        if (!confirm('هل تريد مزامنة جميع البيانات؟ قد يستغرق هذا وقتاً طويلاً.')) {
            return;
        }

        const storesToClear = [
            "products", "categories", "variations",
            "customers", "shippingMethods", "shippingZones", "shippingZoneMethods"
        ];

        const tx = db.transaction(storesToClear, "readwrite");

        storesToClear.forEach(storeName => {
            const store = tx.objectStore(storeName);
            store.clear();
        });

        tx.oncomplete = function() {
            console.log("✅ تم مسح كل البيانات من IndexedDB");

            // إعادة جلب البيانات
            Livewire.dispatch('fetch-products-from-api');
            Livewire.dispatch('fetch-categories-from-api');
            Livewire.dispatch('fetch-customers-from-api');
            Livewire.dispatch('fetch-shipping-zones-and-methods');

            showSuccessMessage("تمت المزامنة بنجاح!");
        };

        tx.onerror = function() {
            console.error("❌ فشل في مسح البيانات");
            showError("حدث خطأ أثناء المزامنة");
        };
    }

    // 📍 إعداد modal الطلب
    function prepareOrderModal() {
        // التحقق من وجود عناصر في السلة
        const tx = db.transaction("cart", "readonly");
        const store = tx.objectStore("cart");
        const request = store.getAll();

        request.onsuccess = function() {
            const cartItems = request.result;

            if (cartItems.length === 0) {
                showError('السلة فارغة. أضف منتجات أولاً.');
                return;
            }

            // تحميل بيانات العملاء
            loadCustomersForModal();

            // تحميل طرق الشحن
            loadShippingMethodsForModal();

            // تحديث إجمالي الطلب
            updateOrderTotalInModal();

            // عرض المودال
            Flux.modal('confirm-order-modal').show();
        };
    }

    // 📍 تحميل العملاء للمودال
    function loadCustomersForModal() {
        const dropdown = document.getElementById("customerSelect");
        if (!dropdown) return;

        dropdown.innerHTML = '<option value="">جاري التحميل...</option>';

        const tx = db.transaction("customers", "readonly");
        const store = tx.objectStore("customers");
        const req = store.getAll();

        req.onsuccess = function() {
            dropdown.innerHTML = '<option value="">اختر عميلاً</option>';

            req.result.forEach(customer => {
                const option = document.createElement("option");
                option.value = customer.id;
                option.textContent = customer.name;
                dropdown.appendChild(option);
            });

            // إضافة خيار عميل جديد
            const addOption = document.createElement("option");
            addOption.value = "add_new_customer";
            addOption.textContent = "+ إضافة زبون جديد";
            dropdown.appendChild(addOption);

            // إعداد مستمع التغيير
            dropdown.addEventListener('change', function() {
                if (this.value === "add_new_customer") {
                    this.value = "";
                    Flux.modal('add-customer-modal').show();
                }
            });
        };

        req.onerror = function() {
            console.error("❌ فشل في تحميل العملاء");
            dropdown.innerHTML = '<option value="">خطأ في التحميل</option>';
        };
    }

    // 📍 تحميل طرق الشحن للمودال
    function loadShippingMethodsForModal() {
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
                container.innerHTML = '';

                zones.forEach(zone => {
                    const zoneDiv = document.createElement("div");
                    zoneDiv.classList.add("border", "rounded", "p-4", "shadow-sm");

                    const zoneTitle = document.createElement("h3");
                    zoneTitle.classList.add("font-bold", "mb-2", "text-gray-800");
                    zoneTitle.textContent = `📦 ${zone.name}`;
                    zoneDiv.appendChild(zoneTitle);

                    const zoneMethods = methods.filter(m => m.zone_id === zone.id);

                    if (zoneMethods.length === 0) {
                        const noMethods = document.createElement("p");
                        noMethods.textContent = "لا يوجد طرق شحن لهذه المنطقة.";
                        noMethods.classList.add("text-gray-500", "text-sm");
                        zoneDiv.appendChild(noMethods);
                    } else {
                        zoneMethods.forEach(method => {
                            const wrapper = document.createElement("div");
                            wrapper.classList.add("flex", "items-center", "gap-2", "mb-2");

                            const radio = document.createElement("input");
                            radio.type = "radio";
                            radio.name = "shippingMethod";
                            radio.value = method.id;
                            radio.id = `method-${method.id}`;
                            radio.addEventListener("change", updateOrderTotalInModal);

                            const label = document.createElement("label");
                            label.setAttribute("for", radio.id);
                            label.classList.add("text-sm", "cursor-pointer");
                            label.textContent = `${method.title} - ${method.cost || 0} ₪`;

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

    // 📍 تحديث إجمالي الطلب في المودال
    function updateOrderTotalInModal() {
        const cartTx = db.transaction("cart", "readonly");
        const cartStore = cartTx.objectStore("cart");
        const cartRequest = cartStore.getAll();

        cartRequest.onsuccess = function() {
            const cartItems = cartRequest.result;
            const subTotal = cartItems.reduce((sum, item) => {
                return sum + ((item.price || 0) * (item.quantity || 1));
            }, 0);

            const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');
            let shippingCost = 0;

            if (selectedMethod) {
                const shippingMethodId = parseInt(selectedMethod.value);
                const shippingTx = db.transaction("shippingZoneMethods", "readonly");
                const shippingStore = shippingTx.objectStore("shippingZoneMethods");
                const shippingReq = shippingStore.get(shippingMethodId);

                shippingReq.onsuccess = function() {
                    const method = shippingReq.result;
                    shippingCost = parseFloat(method?.cost || 0);
                    updateTotalDisplays(subTotal, shippingCost);
                };
            } else {
                updateTotalDisplays(subTotal, shippingCost);
            }
        };
    }

    // 📍 تحديث عناصر العرض الإجمالية
    function updateTotalDisplays(subTotal, shippingCost) {
        const subTotalDisplay = document.getElementById("subTotalDisplay");
        const shippingDisplay = document.getElementById("shippingCostDisplay");
        const finalDisplay = document.getElementById("finalTotalDisplay");

        if (subTotalDisplay) {
            subTotalDisplay.textContent = `المجموع قبل التوصيل: ${subTotal.toFixed(2)} ₪`;
        }

        if (shippingDisplay) {
            shippingDisplay.textContent = `قيمة التوصيل: ${shippingCost.toFixed(2)} ₪`;
        }

        if (finalDisplay) {
            finalDisplay.textContent = `${(subTotal + shippingCost).toFixed(2)} ₪`;
        }
    }

    // 📍 إرسال الطلب
    function submitOrder() {
        const customerId = document.getElementById("customerSelect")?.value;
        const notes = document.getElementById("orderNotes")?.value || '';
        const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');

        if (!customerId) {
            showError("يرجى اختيار العميل");
            return;
        }

        if (!selectedMethod) {
            showError("يرجى اختيار طريقة الشحن");
            return;
        }

        const shippingMethodId = selectedMethod.value;

        // جلب بيانات طريقة الشحن
        const txMethods = db.transaction("shippingZoneMethods", "readonly");
        const storeMethods = txMethods.objectStore("shippingZoneMethods");
        const methodRequest = storeMethods.get(parseInt(shippingMethodId));

        methodRequest.onsuccess = function() {
            const method = methodRequest.result;

            // جلب عناصر السلة
            const tx = db.transaction("cart", "readonly");
            const store = tx.objectStore("cart");
            const request = store.getAll();

            request.onsuccess = function() {
                const cartItems = request.result;

                if (cartItems.length === 0) {
                    showError("السلة فارغة");
                    return;
                }

                const orderData = {
                    customer_id: parseInt(customerId),
                    payment_method: 'cod',
                    payment_method_title: 'الدفع عند الاستلام',
                    set_paid: true,
                    customer_note: notes,
                    shipping_lines: [{
                        method_id: method?.id || shippingMethodId,
                        method_title: method?.title || 'شحن',
                        total: method?.cost || 0
                    }],
                    line_items: cartItems.map(item => ({
                        product_id: item.id,
                        quantity: item.quantity || 1
                    }))
                };

                if (navigator.onLine) {
                    // إرسال الطلب
                    Livewire.dispatch('submit-order', { order: orderData });
                } else {
                    // حفظ في الطلبات المعلقة
                    const tx2 = db.transaction("pendingOrders", "readwrite");
                    tx2.objectStore("pendingOrders").add(orderData);
                    showError("🚫 لا يوجد اتصال. تم حفظ الطلب مؤقتاً.");
                }
            };
        };
    }

    // 📍 إضافة عميل جديد
    function addNewCustomer() {
        const nameInput = document.getElementById("newCustomerName");
        const name = nameInput?.value?.trim();

        if (!name) {
            showError("يرجى إدخال اسم الزبون");
            return;
        }

        const tx = db.transaction("customers", "readwrite");
        const store = tx.objectStore("customers");

        const newCustomer = {
            id: Date.now(), // استخدام timestamp كـ ID مؤقت
            name: name,
            email: '',
            phone: ''
        };

        store.add(newCustomer);

        tx.oncomplete = () => {
            // إغلاق المودال
            Flux.modal('add-customer-modal').close();

            // إعادة تحميل القائمة
            loadCustomersForModal();

            // اختيار الزبون الجديد تلقائياً
            setTimeout(() => {
                const dropdown = document.getElementById("customerSelect");
                if (dropdown) {
                    dropdown.value = newCustomer.id;
                }
            }, 300);

            // مسح النموذج
            if (nameInput) {
                nameInput.value = '';
            }

            showSuccessMessage("تم إضافة الزبون بنجاح");
        };

        tx.onerror = () => {
            showError("حدث خطأ أثناء إضافة الزبون");
        };
    }

    // 📍 عرض العملاء في القائمة المنسدلة
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

            // إضافة خيار العميل الجديد
            const addOption = document.createElement("option");
            addOption.value = "add_new_customer";
            addOption.textContent = "+ إضافة زبون جديد";
            dropdown.appendChild(addOption);

            dropdown.addEventListener('change', function() {
                if (this.value === "add_new_customer") {
                    this.value = "";
                    Flux.modal('add-customer-modal').show();
                }
            });
        };
    }

    // 📍 دوال المساعدة الأخرى
    console.log("🚀 POS System Loaded Successfully");
</script>

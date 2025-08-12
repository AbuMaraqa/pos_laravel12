<div>
    <!-- تحسين شريط التقدم -->
    <div id="sync-progress-container" class="fixed top-0 left-0 right-0 bg-blue-600 text-white p-4 z-50" style="display: none;">
        <div class="max-w-4xl mx-auto">
            <div id="sync-message" class="text-center mb-2">جاري المزامنة...</div>
            <div class="bg-blue-400 rounded-full h-3">
                <div id="sync-progress-bar" class="bg-white h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
            </div>
            <div class="flex justify-between text-sm mt-1 opacity-75">
                <span id="sync-details">الصفحة 0 من 0</span>
                <span id="sync-percentage">0%</span>
            </div>
        </div>
    </div>

    <!-- المودالات -->
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
                <p id="finalTotalDisplay" style="font-size: 40px" class="text-lg font-bold text-black">0 ₪</p>
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
            <input id="newCustomerName" type="text" placeholder="اسم الزبون" class="w-full border rounded px-3 py-2" />
            <div class="flex justify-end gap-2">
                <flux:button variant="danger" onclick="Flux.modal('add-customer-modal').close()">إلغاء</flux:button>
                <flux:button variant="primary" onclick="addNewCustomer()">حفظ</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- المحتوى الرئيسي -->
    <div class="grid gap-4 grid-cols-6">
        <div class="col-span-4">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <!-- شريط الأدوات -->
                <div class="flex items-center gap-2 mb-4">
                    <flux:input id="searchInput" placeholder="البحث..." icon="magnifying-glass" />
                    <flux:button onclick="startBarcodeScan()">Scan</flux:button>
                    <flux:button id="syncButton" onclick="startFullSync()">مزامنة كاملة</flux:button>
                    <flux:button onclick="startQuickSync()" variant="filled">مزامنة سريعة</flux:button>
                </div>

                <!-- التصنيفات -->
                <div class="mt-4">
                    <div id="categoriesContainer" class="flex items-center gap-2 overflow-x-auto whitespace-nowrap">
                        <!-- التصنيفات ستُحمل من IndexedDB -->
                    </div>
                </div>

                <div class="mt-4">
                    <flux:separator />
                </div>

                <!-- عرض المنتجات -->
                <div class="mt-4 h-full bg-gray-200 p-4 rounded-lg shadow-md">
                    <div id="productsContainer" class="grid grid-cols-4 gap-4 overflow-y-auto max-h-[600px]">
                        <!-- المنتجات ستُعرض من IndexedDB هنا -->
                        <div class="col-span-4 text-center py-8">
                            <p class="text-gray-500">جاري تحميل المنتجات...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- السلة -->
        <div class="col-span-2 h-full">
            <div class="bg-white p-4 rounded-lg shadow-md h-full flex flex-col">
                <h2 class="text-lg font-medium mb-4">إجمالي المبيعات</h2>
                <button onclick="clearCart()" class="mt-2 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                    🧹 حذف جميع المنتجات
                </button>
                <div id="cartItemsContainer" class="space-y-2 overflow-y-auto max-h-[500px] flex-1">
                    <div class="flex flex-col items-center justify-center text-center text-gray-500 py-8 space-y-2">
                        <p class="text-lg font-semibold">السلة فارغة</p>
                        <p class="text-sm text-gray-400">لم تقم بإضافة أي منتجات بعد</p>
                    </div>
                </div>
                <div class="mt-4 border-t pt-4 text-right">
                    <p class="font-bold text-xl">المجموع: <span id="cartTotal">0 ₪</span></p>
                </div>
                <flux:button type="button" id="completeOrderBtn" class="mt-4 w-full" variant="primary">
                    إتمام الطلب
                </flux:button>
            </div>
        </div>
    </div>

    <div id="syncOverlay" class="hidden fixed inset-0 z-50 bg-black/30 backdrop-blur-sm flex items-center justify-center">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-5">
            <div class="flex items-center gap-3 mb-4">
                <svg class="animate-spin h-6 w-6" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity="0.2"></circle>
                    <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="4"></path>
                </svg>
                <h3 class="text-lg font-semibold">مزامنة البيانات</h3>
            </div>

            <p id="syncMessage" class="text-sm text-gray-600 mb-3">جارِ التحضير…</p>

            <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                <div id="syncBar" class="h-3 bg-indigo-600 transition-all duration-200" style="width:0%"></div>
            </div>

            <div class="flex justify-between text-xs text-gray-500 mt-2">
                <span id="syncStep">الخطوة 0/0</span>
                <span id="syncPercent">0%</span>
            </div>
        </div>
    </div>
</div>

<script>
    // المتغيرات العامة
    let db;
    const dbName = "POSProductsDB";
    let selectedCategoryId = null;
    let currentSearchTerm = '';
    let syncInProgress = false;

    // إعداد قاعدة البيانات
    function initializeDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(dbName, 7);

            request.onupgradeneeded = function(event) {
                db = event.target.result;

                // إنشاء المتاجر
                const stores = [{
                    name: 'products',
                    keyPath: 'id'
                }, {
                    name: 'categories',
                    keyPath: 'id'
                }, {
                    name: 'cart',
                    keyPath: 'id'
                }, {
                    name: 'customers',
                    keyPath: 'id'
                }, {
                    name: 'shippingZones',
                    keyPath: 'id'
                }, {
                    name: 'shippingZoneMethods',
                    keyPath: 'id'
                }];

                stores.forEach(store => {
                    if (!db.objectStoreNames.contains(store.name)) {
                        const objectStore = db.createObjectStore(store.name, {
                            keyPath: store.keyPath
                        });

                        // إضافة فهارس حسب الحاجة
                        if (store.name === 'shippingZoneMethods') {
                            objectStore.createIndex('zone_id', 'zone_id', {
                                unique: false
                            });
                        }
                    }
                });
            };

            request.onsuccess = function(event) {
                db = event.target.result;
                console.log('✅ قاعدة البيانات جاهزة');
                resolve(db);
            };

            request.onerror = function() {
                console.error('❌ فشل في فتح قاعدة البيانات');
                reject(request.error);
            };
        });
    }

    // تحميل الصفحة
    window.onload = async function() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.focus();

        try {
            await initializeDB();
            await checkAndLoadInitialData();
            setupEventListeners();
        } catch (error) {
            console.error('خطأ في تحميل الصفحة:', error);
            showErrorMessage('فشل في تحميل التطبيق');
        }
    };

    // فحص وتحميل البيانات الأولية
    async function checkAndLoadInitialData() {
        const productsCount = await getStoreCount('products');
        const categoriesCount = await getStoreCount('categories');
        const customersCount = await getStoreCount('customers');

        console.log('📊 إحصائيات قاعدة البيانات:', {
            products: productsCount,
            categories: categoriesCount,
            customers: customersCount
        });

        // تحميل البيانات إذا كانت فارغة
        if (categoriesCount === 0) {
            console.log('📂 جلب التصنيفات...');
            Livewire.dispatch('fetch-categories-from-api');
        } else {
            renderCategoriesFromIndexedDB();
        }

        if (customersCount === 0) {
            console.log('👥 جلب العملاء...');
            Livewire.dispatch('fetch-customers-from-api');
        }

        if (productsCount === 0) {
            console.log('🛍️ جلب المنتجات...');
            // ابدأ مزامنة سريعة تلقائيًا
            Livewire.dispatch('quick-sync-products');
        } else {
            console.log('🛍️ عرض المنتجات من قاعدة البيانات...');
            renderProductsFromIndexedDB();
        }

        // تحميل السلة والشحن
        renderCart();
        Livewire.dispatch('fetch-shipping-zones-and-methods');
    }

    // الحصول على عدد العناصر في متجر
    function getStoreCount(storeName) {
        return new Promise((resolve) => {
            if (!db) {
                resolve(0);
                return;
            }

            try {
                const tx = db.transaction(storeName, 'readonly');
                const store = tx.objectStore(storeName);
                const request = store.count();

                request.onsuccess = () => resolve(request.result);
                request.onerror = () => {
                    console.error(`خطأ في عد عناصر ${storeName}:`, request.error);
                    resolve(0);
                };
            } catch (error) {
                console.error(`خطأ في الوصول لمتجر ${storeName}:`, error);
                resolve(0);
            }
        });
    }

    // إعداد مستمعي الأحداث
    function setupEventListeners() {
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                currentSearchTerm = this.value;
                renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchProductByBarcode(this.value.trim());
                }
            });
        }

        // زر إتمام الطلب
        const completeOrderBtn = document.getElementById('completeOrderBtn');
        if (completeOrderBtn) {
            completeOrderBtn.addEventListener('click', openOrderModal);
        }
    }

    // البحث بالباركود
    async function searchProductByBarcode(term) {
        if (!term) return;

        const products = await getAllProductsFromDB();
        const matched = products.find(item => {
            const nameMatch = item.name?.toLowerCase().includes(term.toLowerCase());
            const idMatch = item.id?.toString() === term;
            const skuMatch = item.sku?.toLowerCase() === term.toLowerCase();
            return nameMatch || idMatch || skuMatch;
        });

        if (!matched) {
            showErrorMessage('لا يوجد منتج مطابق');
            return;
        }

        if (matched.type === 'simple') {
            addToCart(matched);
        } else if (matched.type === 'variable') {
            await loadAndShowVariations(matched);
        }
    }

    // جلب جميع المنتجات من قاعدة البيانات
    function getAllProductsFromDB() {
        return new Promise((resolve) => {
            if (!db) {
                resolve([]);
                return;
            }

            try {
                const tx = db.transaction('products', 'readonly');
                const store = tx.objectStore('products');
                const request = store.getAll();

                request.onsuccess = () => {
                    const result = request.result || [];
                    console.log(`📦 تم جلب ${result.length} منتج من قاعدة البيانات`);
                    resolve(result);
                };
                request.onerror = () => {
                    console.error('خطأ في جلب المنتجات:', request.error);
                    resolve([]);
                };
            } catch (error) {
                console.error('خطأ في الوصول لمتجر المنتجات:', error);
                resolve([]);
            }
        });
    }

    // عرض المنتجات من IndexedDB
    async function renderProductsFromIndexedDB(searchTerm = '', categoryId = null) {
        console.log('🎨 عرض المنتجات...', { searchTerm, categoryId });

        const products = await getAllProductsFromDB();
        const container = document.getElementById('productsContainer');

        if (!container) {
            console.error('❌ لا يمكن العثور على حاوي المنتجات');
            return;
        }

        container.innerHTML = '';

        // فلترة المنتجات
        const filtered = products.filter(item => {
            const term = searchTerm.trim().toLowerCase();
            // Show only simple and variable products, not variations
            const isAllowedType = item.type === 'simple' || item.type === 'variable';

            const matchesSearch = !term || (
                (item.name && item.name.toLowerCase().includes(term)) ||
                (item.id && item.id.toString().includes(term)) ||
                (item.sku && item.sku.toLowerCase().includes(term))
            );

            const matchesCategory = !categoryId || (
                item.categories && item.categories.some(cat => cat.id === categoryId)
            );

            return isAllowedType && matchesSearch && matchesCategory;
        });

        console.log(`🔍 تمت فلترة ${filtered.length} منتج من أصل ${products.length}`);

        if (filtered.length === 0) {
            container.innerHTML = '<div class="col-span-4 text-center py-8"><p class="text-gray-500">لا يوجد منتجات مطابقة</p></div>';
            return;
        }

        // عرض المنتجات
        filtered.forEach(item => {
            try {
                const productCard = createProductCard(item);
                container.appendChild(productCard);
            } catch (error) {
                console.error('خطأ في إنشاء بطاقة منتج:', error, item);
            }
        });

        console.log(`✅ تم عرض ${filtered.length} منتج`);
    }

    // إنشاء بطاقة منتج
    function createProductCard(item) {
        const div = document.createElement('div');
        div.className = 'bg-white rounded-lg shadow-md relative cursor-pointer hover:shadow-lg transition-shadow';

        div.onclick = async function() {
            if (item.type === 'variable') {
                await loadAndShowVariations(item);
            } else if (item.type === 'simple') {
                addToCart(item);
            }
        };

        const imageUrl = item.images?.[0]?.src || 'https://via.placeholder.com/200x200?text=No+Image';

        div.innerHTML = `
        <div class="absolute top-0 left-0 right-0 bg-black text-white text-xs text-center py-1 opacity-75 z-10">
            ID: ${item.id}
        </div>

        <img src="${imageUrl}" alt="${item.name || 'منتج'}"
             class="w-full object-cover"
             style="height: 200px;"
             loading="lazy"
             onerror="this.src='https://via.placeholder.com/200x200?text=No+Image'">

        <div class="absolute bottom-12 left-2 bg-black text-white px-2 py-1 rounded text-sm font-bold opacity-80 z-10">
            ${item.price || '0'} ₪
        </div>

        <div class="bg-gray-200 p-3">
            <p class="font-bold text-sm text-center truncate">${item.name || 'بدون اسم'}</p>
            ${item.type === 'variable' ? '<span class="text-xs text-blue-600">منتج متغير</span>' : ''}
        </div>
    `;

        return div;
    }

    // تحميل وعرض المتغيرات
    async function loadAndShowVariations(product) {
        if (!product.variations || product.variations.length === 0) {
            showErrorMessage('لا توجد متغيرات لهذا المنتج');
            return;
        }

        const variations = await getVariationsByParentId(product.id);

        if (variations.length > 0) {
            showVariationsModal(variations, product.name);
        } else {
            showInfoMessage('جاري تحميل متغيرات المنتج...');
            Livewire.dispatch('fetch-variations-on-demand', {
                productId: product.id
            });
        }
    }

    // New function: Get variations by parent product ID
    function getVariationsByParentId(parentId) {
        return new Promise((resolve) => {
            if (!db) {
                resolve([]);
                return;
            }

            try {
                const tx = db.transaction('products', 'readonly');
                const store = tx.objectStore('products');
                const request = store.getAll();

                request.onsuccess = () => {
                    const allItems = request.result || [];
                    const variations = allItems.filter(item => item.type === 'variation' && item.product_id === parentId);
                    console.log(`🔍 تم العثور على ${variations.length} متغير للمنتج ${parentId}`);
                    resolve(variations);
                };
                request.onerror = () => {
                    console.error('خطأ في جلب المتغيرات:', request.error);
                    resolve([]);
                };
            } catch (error) {
                console.error('خطأ في الوصول للمتغيرات:', error);
                resolve([]);
            }
        });
    }

    // الحصول على منتج من قاعدة البيانات
    function getProductFromDB(id) {
        return new Promise((resolve) => {
            if (!db) {
                resolve(null);
                return;
            }

            try {
                const tx = db.transaction('products', 'readonly');
                const store = tx.objectStore('products');
                const request = store.get(id);

                request.onsuccess = () => resolve(request.result);
                request.onerror = () => {
                    console.error('خطأ في جلب المنتج:', request.error);
                    resolve(null);
                };
            } catch (error) {
                console.error('خطأ في الوصول للمنتج:', error);
                resolve(null);
            }
        });
    }

    // عرض مودال المتغيرات
    function showVariationsModal(variations, parentProductName) {
        const modal = Flux.modal('variations-modal');
        const container = document.getElementById('variationsTableBody');

        if (!container) return;

        container.innerHTML = '';

        if (variations.length === 0) {
            container.innerHTML = '<div class="text-center text-gray-500 py-8">لا يوجد متغيرات متاحة</div>';
            modal.show();
            return;
        }

        const grid = document.createElement('div');
        grid.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 p-4';

        variations.forEach(variation => {
            const card = createVariationCard(variation, parentProductName);
            grid.appendChild(card);
        });

        container.appendChild(grid);
        modal.show();
    }

    // إنشاء بطاقة متغير
    function createVariationCard(variation, parentProductName) {
        const card = document.createElement('div');
        card.className = 'bg-white rounded-lg shadow-md overflow-hidden cursor-pointer hover:shadow-xl transition-all';
        card.onclick = () => {
            // Include parent product ID when adding variation to cart
            const cartItem = {
                ...variation,
                parent_product_id: variation.product_id
            };
            addToCart(cartItem);
        };

        const imageUrl = variation.images?.[0]?.src || 'https://via.placeholder.com/200x200?text=No+Image';
        const variationName = `${parentProductName} - ${variation.name}`;

        card.innerHTML = `
        <div class="absolute top-0 left-0 right-0 bg-black text-white text-xs text-center py-1 opacity-75 z-10">
            ID: ${variation.id}
        </div>

        <img src="${imageUrl}" alt="${variation.name}"
             class="w-full object-cover"
             style="height: 150px;"
             onerror="this.src='https://via.placeholder.com/200x200?text=No+Image'">

        <div class="absolute bottom-12 left-2 bg-black text-white px-2 py-1 rounded text-sm font-bold opacity-80 z-10">
            ${variation.price || '0'} ₪
        </div>

        <div class="bg-gray-200 p-2">
            <p class="font-bold text-xs text-center truncate">${variationName}</p>
        </div>
    `;

        return card;
    }

    // عرض التصنيفات
    async function renderCategoriesFromIndexedDB() {
        const categories = await getAllCategoriesFromDB();
        const container = document.getElementById('categoriesContainer');

        if (!container) return;

        container.innerHTML = '';

        // زر "الكل"
        const allBtn = createCategoryButton('الكل', null, selectedCategoryId === null);
        container.appendChild(allBtn);

        // أزرار التصنيفات
        categories.forEach(category => {
            const btn = createCategoryButton(category.name, category.id, selectedCategoryId === category.id);
            container.appendChild(btn);
        });
    }

    // الحصول على جميع التصنيفات
    function getAllCategoriesFromDB() {
        return new Promise((resolve) => {
            if (!db) {
                resolve([]);
                return;
            }

            try {
                const tx = db.transaction('categories', 'readonly');
                const store = tx.objectStore('categories');
                const request = store.getAll();

                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => {
                    console.error('خطأ في جلب التصنيفات:', request.error);
                    resolve([]);
                };
            } catch (error) {
                console.error('خطأ في الوصول للتصنيفات:', error);
                resolve([]);
            }
        });
    }

    // إنشاء زر تصنيف
    function createCategoryButton(name, id, isActive) {
        const btn = document.createElement('button');
        btn.textContent = name;
        btn.className = `px-3 py-1 rounded text-sm whitespace-nowrap ${
            isActive
                ? 'bg-blue-500 text-white'
                : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'
        }`;

        btn.onclick = () => {
            selectedCategoryId = id;
            renderCategoriesFromIndexedDB();
            renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
        };

        return btn;
    }

    // إضافة منتج للسلة
    async function addToCart(product) {
        if (!db || !product) return;

        try {
            const tx = db.transaction('cart', 'readwrite');
            const store = tx.objectStore('cart');

            // فحص المنتج الموجود
            const existingRequest = store.get(product.id);

            existingRequest.onsuccess = function() {
                const existing = existingRequest.result;

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
                        // Add parent product ID for variations
                        parent_product_id: product.product_id ?? null
                    });
                }

                renderCart();
                showSuccessMessage(`تمت إضافة "${product.name}" للسلة`);
            };

            existingRequest.onerror = function() {
                console.error('خطأ في إضافة المنتج للسلة:', existingRequest.error);
                showErrorMessage('فشل في إضافة المنتج للسلة');
            };
        } catch (error) {
            console.error('خطأ في عملية إضافة للسلة:', error);
            showErrorMessage('فشل في إضافة المنتج للسلة');
        }
    }

    // عرض السلة
    async function renderCart() {
        const cartItems = await getAllCartItems();
        const container = document.getElementById('cartItemsContainer');
        const totalElement = document.getElementById('cartTotal');

        if (!container || !totalElement) return;

        if (cartItems.length === 0) {
            container.innerHTML = `
            <div class="flex flex-col items-center justify-center text-center text-gray-500 py-8 space-y-2">
                <p class="text-lg font-semibold">السلة فارغة</p>
                <p class="text-sm text-gray-400">لم تقم بإضافة أي منتجات بعد</p>
            </div>
        `;
            totalElement.textContent = '0.00 ₪';
            return;
        }

        container.innerHTML = '';
        let total = 0;

        cartItems.forEach(item => {
            total += item.price * item.quantity;
            const cartItemElement = createCartItemElement(item);
            container.appendChild(cartItemElement);
        });

        totalElement.textContent = total.toFixed(2) + ' ₪';
    }

    // الحصول على جميع عناصر السلة
    function getAllCartItems() {
        return new Promise((resolve) => {
            if (!db) {
                resolve([]);
                return;
            }

            try {
                const tx = db.transaction('cart', 'readonly');
                const store = tx.objectStore('cart');
                const request = store.getAll();

                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => {
                    console.error('خطأ في جلب عناصر السلة:', request.error);
                    resolve([]);
                };
            } catch (error) {
                console.error('خطأ في الوصول للسلة:', error);
                resolve([]);
            }
        });
    }

    // إنشاء عنصر السلة
    function createCartItemElement(item) {
        const div = document.createElement('div');
        div.className = 'flex justify-between items-center bg-gray-100 p-3 rounded transition duration-300';

        div.innerHTML = `
        <div class="flex items-center gap-3">
            <img src="${item.image || 'https://via.placeholder.com/60x60?text=No+Image'}"
                 alt="${item.name}"
                 class="w-12 h-12 object-cover rounded"
                 onerror="this.src='https://via.placeholder.com/60x60?text=No+Image'">
            <div>
                <p class="font-semibold text-sm">${item.name}</p>
                <div class="flex items-center gap-2 mt-1">
                    <button onclick="updateQuantity(${item.id}, -1)"
                            class="bg-gray-300 px-2 py-1 rounded hover:bg-gray-400 text-sm">−</button>
                    <span class="font-medium">${item.quantity}</span>
                    <button onclick="updateQuantity(${item.id}, 1)"
                            class="bg-gray-300 px-2 py-1 rounded hover:bg-gray-400 text-sm">+</button>
                </div>
            </div>
        </div>
        <div class="text-right">
            <p class="font-bold text-gray-800">${(item.price * item.quantity).toFixed(2)} ₪</p>
            <button onclick="removeFromCart(${item.id})"
                    class="text-red-500 hover:text-red-700 text-sm mt-1">🗑️ حذف</button>
        </div>
    `;

        return div;
    }

    // تحديث الكمية
    async function updateQuantity(productId, change) {
        if (!db) return;

        try {
            const tx = db.transaction('cart', 'readwrite');
            const store = tx.objectStore('cart');
            const request = store.get(productId);

            request.onsuccess = function() {
                const item = request.result;
                if (!item) return;

                item.quantity += change;

                if (item.quantity <= 0) {
                    store.delete(productId);
                } else {
                    store.put(item);
                }

                renderCart();
            };

            request.onerror = function() {
                console.error('خطأ في تحديث الكمية:', request.error);
            };
        } catch (error) {
            console.error('خطأ في عملية تحديث الكمية:', error);
        }
    }

    // حذف من السلة
    async function removeFromCart(productId) {
        if (!db) return;

        try {
            const tx = db.transaction('cart', 'readwrite');
            const store = tx.objectStore('cart');

            store.delete(productId).onsuccess = function() {
                renderCart();
                showSuccessMessage('تم حذف المنتج من السلة');
            };
        } catch (error) {
            console.error('خطأ في حذف المنتج:', error);
            showErrorMessage('فشل في حذف المنتج');
        }
    }

    // مسح السلة
    async function clearCart() {
        if (!db) return;

        if (!confirm('هل أنت متأكد من حذف جميع المنتجات؟')) return;

        try {
            const tx = db.transaction('cart', 'readwrite');
            const store = tx.objectStore('cart');

            store.clear().onsuccess = function() {
                renderCart();
                showSuccessMessage('تم مسح السلة');
            };
        } catch (error) {
            console.error('خطأ في مسح السلة:', error);
            showErrorMessage('فشل في مسح السلة');
        }
    }

    // تخزين دفعة المنتجات
    async function storeProductsBatch(products) {
        if (!db || !products || products.length === 0) {
            console.warn('⚠️ لا توجد منتجات للتخزين');
            return;
        }

        try {
            const tx = db.transaction('products', 'readwrite');
            const store = tx.objectStore('products');

            const promises = products.map(product => {
                return new Promise((resolve) => {
                    const request = store.put(product);
                    request.onsuccess = () => resolve();
                    request.onerror = () => {
                        console.error('خطأ في تخزين منتج:', product.id, request.error);
                        resolve();
                    };
                });
            });

            await Promise.all(promises);
            console.log(`✅ تم تخزين ${products.length} منتج/متغير`);

            // إعادة عرض المنتجات فوراً بعد التخزين
            await renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
        } catch (error) {
            console.error('خطأ في عملية تخزين المنتجات:', error);
        }
    }

    // تخزين دفعة التصنيفات
    async function storeCategoriesBatch(categories) {
        if (!db || !categories) return;

        try {
            const tx = db.transaction('categories', 'readwrite');
            const store = tx.objectStore('categories');

            categories.forEach(category => {
                store.put(category);
            });

            console.log(`✅ تم تخزين ${categories.length} تصنيف`);
        } catch (error) {
            console.error('خطأ في تخزين التصنيفات:', error);
        }
    }

    // تخزين دفعة العملاء
    async function storeCustomersBatch(customers) {
        if (!db || !customers) return;

        try {
            const tx = db.transaction('customers', 'readwrite');
            const store = tx.objectStore('customers');

            customers.forEach(customer => {
                store.put({
                    id: customer.id,
                    name: `${customer.first_name || ''} ${customer.last_name || ''}`.trim() || 'عميل'
                });
            });

            console.log(`✅ تم تخزين ${customers.length} عميل`);
        } catch (error) {
            console.error('خطأ في تخزين العملاء:', error);
        }
    }

    // تخزين مناطق الشحن
    async function storeShippingZonesBatch(zones) {
        if (!db || !zones) return;

        try {
            const tx = db.transaction('shippingZones', 'readwrite');
            const store = tx.objectStore('shippingZones');

            zones.forEach(zone => {
                store.put({
                    id: zone.id,
                    name: zone.name
                });
            });

            console.log(`✅ تم تخزين ${zones.length} منطقة شحن`);
        } catch (error) {
            console.error('خطأ في تخزين مناطق الشحن:', error);
        }
    }

    // تخزين طرق الشحن
    async function storeShippingMethodsBatch(methods) {
        if (!db || !methods) return;

        try {
            const tx = db.transaction('shippingZoneMethods', 'readwrite');
            const store = tx.objectStore('shippingZoneMethods');

            methods.forEach(method => {
                store.put({
                    id: method.id,
                    zone_id: method.zone_id,
                    title: method.title,
                    cost: parseFloat(method.settings?.cost?.value || 0)
                });
            });

            console.log(`✅ تم تخزين ${methods.length} طريقة شحن`);
        } catch (error) {
            console.error('خطأ في تخزين طرق الشحن:', error);
        }
    }

    // بدء المزامنة الكاملة
    function startFullSync() {
        if (syncInProgress) {
            showErrorMessage('المزامنة جارية بالفعل');
            return;
        }

        if (!confirm('هل تريد مزامنة جميع المنتجات؟ قد تستغرق عدة دقائق.')) {
            return;
        }

        console.log('🔄 بدء المزامنة الكاملة...');
        Livewire.dispatch('fetch-products-from-api');
    }

    // بدء المزامنة السريعة
    function startQuickSync() {
        if (syncInProgress) {
            showErrorMessage('المزامنة جارية بالفعل');
            return;
        }

        console.log('⚡ بدء المزامنة السريعة...');
        Livewire.dispatch('quick-sync-products');
    }

    // بدء مسح الباركود
    function startBarcodeScan() {
        showInfoMessage('ميزة مسح الباركود ستكون متاحة قريباً');
    }

    // مستمعي أحداث Livewire
    document.addEventListener('livewire:init', () => {
        console.log('🔗 تم تهيئة Livewire');

        // بداية المزامنة
        Livewire.on('sync-started', (data) => {
            console.log('🔄 بدء المزامنة:', data[0]);
            syncInProgress = true;
            showSyncProgress(data[0]);
        });

        // تحديث التقدم
        Livewire.on('update-progress', (data) => {
            console.log('📊 تحديث التقدم:', data[0]);
            updateProgressBar(data[0]);
        });

        // انتهاء المزامنة
        Livewire.on('sync-completed', async (data) => {
            console.log('✅ انتهت المزامنة:', data[0]);
            syncInProgress = false;
            hideSyncProgress();
            showSuccessMessage(data[0].message);
            await renderProductsFromIndexedDB(currentSearchTerm, selectedCategoryId);
        });

        // خطأ في المزامنة
        Livewire.on('sync-error', (data) => {
            console.error('❌ خطأ في المزامنة:', data[0]);
            syncInProgress = false;
            hideSyncProgress();
            showErrorMessage('خطأ في المزامنة: ' + data[0].error);
        });

        // تخزين دفعات المنتجات
        Livewire.on('store-products-batch', async (data) => {
            console.log('💾 تخزين دفعة المنتجات:', data[0].products?.length || 0);
            await storeProductsBatch(data[0].products);
        });

        // تخزين التصنيفات
        Livewire.on('store-categories', async (data) => {
            console.log('📂 تخزين التصنيفات:', data[0].categories?.length || 0);
            await storeCategoriesBatch(data[0].categories);
            renderCategoriesFromIndexedDB();
        });

        // تخزين العملاء
        Livewire.on('store-customers', async (data) => {
            console.log('👥 تخزين العملاء:', data[0].customers?.length || 0);
            await storeCustomersBatch(data[0].customers);
        });

        // تخزين مناطق الشحن
        Livewire.on('store-shipping-zones', async (data) => {
            await storeShippingZonesBatch(data[0].zones);
        });

        // تخزين طرق الشحن
        Livewire.on('store-shipping-zone-methods', async (data) => {
            await storeShippingMethodsBatch(data[0]);
        });

        // نجاح الطلب
        Livewire.on('order-success', () => {
            Flux.modal('confirm-order-modal').close();
            clearCart();
            showSuccessMessage('تم إرسال الطلب بنجاح!');
        });

        // فشل الطلب
        Livewire.on('order-failed', () => {
            showErrorMessage('فشل في إرسال الطلب');
        });

        // Handle on-demand variations sync completion
        Livewire.on('variations-synced-on-demand', async (data) => {
            const productId = data[0].productId;
            console.log('🔄 تم جلب المتغيرات عند الطلب:', productId);
            showSuccessMessage('تم جلب المتغيرات بنجاح. يتم عرضها الآن.');

            // Re-run the function to show the modal with the new data
            const product = await getProductFromDB(productId);
            if (product) {
                await loadAndShowVariations(product);
            }
        });
    });

    // عرض شريط التقدم
    function showSyncProgress(data) {
        const container = document.getElementById('sync-progress-container');
        if (container) {
            container.style.display = 'block';

            const message = container.querySelector('#sync-message');
            const progressBar = container.querySelector('#sync-progress-bar');

            if (message) message.textContent = `بدء مزامنة ${data.total} منتج...`;
            if (progressBar) progressBar.style.width = '0%';
        }
    }

    // تحديث شريط التقدم
    function updateProgressBar(data) {
        const container = document.getElementById('sync-progress-container');
        if (!container) return;

        const message = container.querySelector('#sync-message');
        const progressBar = container.querySelector('#sync-progress-bar');
        const details = container.querySelector('#sync-details');
        const percentage = container.querySelector('#sync-percentage');

        if (message) message.textContent = data.message;
        if (progressBar) progressBar.style.width = data.progress + '%';
        if (details) details.textContent = `الصفحة ${data.page} من ${data.totalPages}`;
        if (percentage) percentage.textContent = Math.round(data.progress) + '%';
    }

    // إخفاء شريط التقدم
    function hideSyncProgress() {
        const container = document.getElementById('sync-progress-container');
        if (container) {
            setTimeout(() => {
                container.style.display = 'none';
            }, 3000);
        }
    }

    // إنشاء إشعار
    function createNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `fixed top-20 right-4 p-4 rounded-lg shadow-lg z-50 max-w-sm ${
            type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                    'bg-blue-500 text-white'
        }`;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, type === 'error' ? 8000 : 5000);

        return notification;
    }

    // عرض رسالة نجاح
    function showSuccessMessage(message) {
        createNotification(message, 'success');
    }

    // عرض رسالة خطأ
    function showErrorMessage(message) {
        createNotification(message, 'error');
    }

    // عرض رسالة معلومات
    function showInfoMessage(message) {
        createNotification(message, 'info');
    }

    // فتح مودال الطلب
    async function openOrderModal() {
        const cartItems = await getAllCartItems();

        if (cartItems.length === 0) {
            showErrorMessage('السلة فارغة');
            return;
        }

        await setupOrderModal();
        Flux.modal('confirm-order-modal').show();
    }

    // إعداد مودال الطلب
    async function setupOrderModal() {
        await renderCustomersDropdown();
        await renderShippingZonesWithMethods();
        updateOrderTotalInModal();
    }

    // عرض قائمة العملاء
    async function renderCustomersDropdown() {
        const customers = await getAllCustomersFromDB();
        const dropdown = document.getElementById('customerSelect');

        if (!dropdown) return;

        dropdown.innerHTML = '<option value="">اختر عميل</option>';

        customers.forEach(customer => {
            const option = document.createElement('option');
            option.value = customer.id;
            option.textContent = customer.name;
            dropdown.appendChild(option);
        });

        // إضافة خيار العميل الجديد
        const addOption = document.createElement('option');
        addOption.value = 'add_new_customer';
        addOption.textContent = '+ إضافة عميل جديد';
        dropdown.appendChild(addOption);

        // إضافة مستمع الأحداث
        dropdown.onchange = function() {
            if (this.value === 'add_new_customer') {
                this.value = '';
                Flux.modal('add-customer-modal').show();
            }
        };
    }

    // الحصول على جميع العملاء
    function getAllCustomersFromDB() {
        return new Promise((resolve) => {
            if (!db) {
                resolve([]);
                return;
            }

            try {
                const tx = db.transaction('customers', 'readonly');
                const store = tx.objectStore('customers');
                const request = store.getAll();

                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => {
                    console.error('خطأ في جلب العملاء:', request.error);
                    resolve([]);
                };
            } catch (error) {
                console.error('خطأ في الوصول للعملاء:', error);
                resolve([]);
            }
        });
    }

    // عرض مناطق الشحن وطرقها
    async function renderShippingZonesWithMethods() {
        const zones = await getAllShippingZonesFromDB();
        const methods = await getAllShippingMethodsFromDB();
        const container = document.getElementById('shippingZonesContainer');

        if (!container) return;

        container.innerHTML = '';

        zones.forEach(zone => {
            const zoneDiv = document.createElement('div');
            zoneDiv.className = 'border rounded p-4 shadow';

            const zoneTitle = document.createElement('h3');
            zoneTitle.className = 'font-bold mb-2 text-gray-800';
            zoneTitle.textContent = `📦 ${zone.name}`;
            zoneDiv.appendChild(zoneTitle);

            const zoneMethods = methods.filter(m => m.zone_id === zone.id);

            if (zoneMethods.length === 0) {
                const noMethods = document.createElement('p');
                noMethods.textContent = 'لا يوجد طرق شحن لهذه المنطقة.';
                noMethods.className = 'text-gray-500 text-sm';
                zoneDiv.appendChild(noMethods);
            } else {
                zoneMethods.forEach(method => {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'flex items-center gap-2 mb-1';

                    const radio = document.createElement('input');
                    radio.type = 'radio';
                    radio.name = 'shippingMethod';
                    radio.value = method.id;
                    radio.id = `method-${method.id}`;
                    radio.onchange = () => updateOrderTotalInModal();

                    const label = document.createElement('label');
                    label.setAttribute('for', radio.id);
                    label.className = 'text-sm cursor-pointer';
                    label.textContent = `${method.title} - ${method.cost || 0} ₪`;

                    wrapper.appendChild(radio);
                    wrapper.appendChild(label);
                    zoneDiv.appendChild(wrapper);
                });
            }

            container.appendChild(zoneDiv);
        });
    }

    // الحصول على مناطق الشحن
    function getAllShippingZonesFromDB() {
        return new Promise((resolve) => {
            if (!db) {
                resolve([]);
                return;
            }

            try {
                const tx = db.transaction('shippingZones', 'readonly');
                const store = tx.objectStore('shippingZones');
                const request = store.getAll();

                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => {
                    console.error('خطأ في جلب مناطق الشحن:', request.error);
                    resolve([]);
                };
            } catch (error) {
                console.error('خطأ في الوصول لمناطق الشحن:', error);
                resolve([]);
            }
        });
    }

    // الحصول على طرق الشحن
    function getAllShippingMethodsFromDB() {
        return new Promise((resolve) => {
            if (!db) {
                resolve([]);
                return;
            }

            try {
                const tx = db.transaction('shippingZoneMethods', 'readonly');
                const store = tx.objectStore('shippingZoneMethods');
                const request = store.getAll();

                request.onsuccess = () => resolve(request.result || []);
                request.onerror = () => {
                    console.error('خطأ في جلب طرق الشحن:', request.error);
                    resolve([]);
                };
            } catch (error) {
                console.error('خطأ في الوصول لطرق الشحن:', error);
                resolve([]);
            }
        });
    }

    // تحديث إجمالي الطلب في المودال
    async function updateOrderTotalInModal() {
        const cartItems = await getAllCartItems();
        const subTotal = cartItems.reduce((sum, item) => sum + (item.price * item.quantity), 0);

        const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');
        let shippingCost = 0;

        if (selectedMethod) {
            const methodId = parseInt(selectedMethod.value);
            const method = await getShippingMethodFromDB(methodId);
            shippingCost = parseFloat(method?.cost || 0);
        }

        updateTotalDisplays(subTotal, shippingCost);
    }

    // الحصول على طريقة شحن
    function getShippingMethodFromDB(methodId) {
        return new Promise((resolve) => {
            if (!db) {
                resolve(null);
                return;
            }

            try {
                const tx = db.transaction('shippingZoneMethods', 'readonly');
                const store = tx.objectStore('shippingZoneMethods');
                const request = store.get(methodId);

                request.onsuccess = () => resolve(request.result);
                request.onerror = () => {
                    console.error('خطأ في جلب طريقة الشحن:', request.error);
                    resolve(null);
                };
            } catch (error) {
                console.error('خطأ في الوصول لطريقة الشحن:', error);
                resolve(null);
            }
        });
    }

    // تحديث عرض المبالغ
    function updateTotalDisplays(subTotal, shippingCost) {
        const subTotalDisplay = document.getElementById('subTotalDisplay');
        const shippingDisplay = document.getElementById('shippingCostDisplay');
        const finalDisplay = document.getElementById('finalTotalDisplay');

        if (subTotalDisplay) subTotalDisplay.textContent = `المجموع قبل التوصيل: ${subTotal.toFixed(2)} ₪`;
        if (shippingDisplay) shippingDisplay.textContent = `قيمة التوصيل: ${shippingCost.toFixed(2)} ₪`;
        if (finalDisplay) finalDisplay.textContent = `${(subTotal + shippingCost).toFixed(2)} ₪`;
    }

    // إضافة عميل جديد
    async function addNewCustomer() {
        const nameInput = document.getElementById('newCustomerName');
        const name = nameInput.value.trim();

        if (!name) {
            showErrorMessage('يرجى إدخال اسم العميل');
            return;
        }

        if (!db) return;

        try {
            const newCustomer = {
                id: Date.now(),
                name: name
            };

            const tx = db.transaction('customers', 'readwrite');
            const store = tx.objectStore('customers');

            store.add(newCustomer).onsuccess = function() {
                Flux.modal('add-customer-modal').close();
                nameInput.value = '';

                renderCustomersDropdown().then(() => {
                    const dropdown = document.getElementById('customerSelect');
                    if (dropdown) dropdown.value = newCustomer.id;
                });

                showSuccessMessage('تم إضافة العميل بنجاح');
            };
        } catch (error) {
            console.error('خطأ في إضافة العميل:', error);
            showErrorMessage('فشل في إضافة العميل');
        }
    }

    // تأكيد الطلب
    async function confirmOrder() {
        const customerId = document.getElementById('customerSelect').value;
        const notes = document.getElementById('orderNotes').value;
        const selectedMethod = document.querySelector('input[name="shippingMethod"]:checked');

        if (!customerId || !selectedMethod) {
            showErrorMessage('يرجى اختيار العميل وطريقة الشحن');
            return;
        }

        const cartItems = await getAllCartItems();
        if (cartItems.length === 0) {
            showErrorMessage('السلة فارغة');
            return;
        }

        const method = await getShippingMethodFromDB(parseInt(selectedMethod.value));

        const orderData = {
            customer_id: parseInt(customerId),
            payment_method: 'cod',
            payment_method_title: 'الدفع عند الاستلام',
            set_paid: true,
            customer_note: notes,
            shipping_lines: [{
                method_id: method.id,
                method_title: method.title,
                total: method.cost || 0
            }],
            line_items: cartItems.map(item => ({
                // Use parent_product_id if available, otherwise use item.id
                product_id: item.parent_product_id || item.id,
                quantity: item.quantity
            }))
        };

        if (navigator.onLine) {
            Livewire.dispatch('submit-order', {
                order: orderData
            });
        } else {
            showErrorMessage('لا يوجد اتصال بالإنترنت');
        }
    }

    // إعداد زر تأكيد الطلب
    document.addEventListener('DOMContentLoaded', () => {
        const confirmBtn = document.getElementById('confirmOrderSubmitBtn');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', confirmOrder);
        }
    });

    console.log('🚀 تم تحميل نظام نقطة البيع بنجاح مع الإصلاحات');
</script>

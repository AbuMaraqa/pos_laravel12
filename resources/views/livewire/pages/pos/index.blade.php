<div>
    <div class="grid gap-4 grid-cols-6">
        <div class="col-span-4">
            <div class="bg-white p-4 rounded-lg shadow-md">
                <div class="flex items-center gap-2">
                    <flux:input id="searchInput" placeholder="Search" icon="magnifying-glass" />
                    <flux:button>
                        Scan
                    </flux:button>
                    <flux:button id="syncButton">
                        Sync
                    </flux:button>
                </div>

                <div class="mt-4">
                    <h1>{{ __('Categories') }}</h1>
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
                <div class="mt-4 bg-blue-100 p-4 rounded-lg shadow-md">
                    <h1>{{ __('Products') }}</h1>
                    <div id="productsContainer" class="grid grid-cols-3 gap-4">
                        @foreach ($products as $item)
                            <div class="bg-white rounded-lg shadow-md">
                                <img src="{{ $item['images'][0]['src'] ?? '' }}" alt="{{ $item['name'] ?? '' }}"
                                    class="w-full m-0 h-60 object-cover">
                                <div class="p-4">
                                    <p class="py-1 font-bold">{{ $item['name'] ?? '' }}</p>
                                    <p class="py-1 font-bold">{{ $item['price'] ?? '' }}</p>
                                    <p class="py-1 font-bold"><span>ID : </span> <span>{{ $item['id'] ?? '' }}</span>
                                    </p>
                                    <flux:button variant="primary" icon="shopping-cart" class="w-full mt-2">
                                        {{ __('Add To Cart') }}
                                    </flux:button>
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

@push('scripts')
<script>
    document.addEventListener('livewire:initialized', function () {
        // Store the currently selected category ID
        let currentCategoryId = null;

        // Initialize IndexedDB
        initIndexedDB();

        // Add event listener to sync button
        const syncButton = document.getElementById('syncButton');
        if (syncButton) {
            syncButton.addEventListener('click', function() {
                syncToIndexedDB();
            });
        }

        // Store products on page load
        syncToIndexedDB();

        // Setup search input
        const searchInput = document.getElementById('searchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                searchProductsInIndexedDB(e.target.value);
            });
        }
    });

    function initIndexedDB() {
        // Initialize IndexedDB
        const dbPromise = window.indexedDB.open('posDB', 1);

        dbPromise.onupgradeneeded = function(event) {
            const db = event.target.result;

            // Create stores if they don't exist
            if (!db.objectStoreNames.contains('products')) {
                const productsStore = db.createObjectStore('products', { keyPath: 'id' });
                productsStore.createIndex('name', 'name', { unique: false });
            }

            if (!db.objectStoreNames.contains('categories')) {
                db.createObjectStore('categories', { keyPath: 'id' });
            }
        };

        dbPromise.onerror = function(event) {
            console.error('IndexedDB error:', event.target.error);
        };
    }

    function syncToIndexedDB() {
        // Sync both products and categories
        let productsCount = 0;

        // Get products from Livewire component
        @this.syncProductsToIndexedDB().then(products => {
            const dbPromise = window.indexedDB.open('posDB', 1);

            dbPromise.onsuccess = function(event) {
                const db = event.target.result;
                const transaction = db.transaction('products', 'readwrite');
                const productsStore = transaction.objectStore('products');

                // Clear existing products
                productsStore.clear();

                // Add products to store
                products.forEach(product => {
                    productsStore.add(product);
                });

                productsCount = products.length;

                transaction.oncomplete = function() {
                    console.log('Products stored in IndexedDB successfully');

                    // Now save categories after products are saved
                    saveCategoriesToIndexedDB(productsCount);

                    // Refresh the display with products from IndexedDB
                    displayProductsFromIndexedDB();

                    // Refresh categories display
                    displayCategoriesFromIndexedDB();
                };

                transaction.onerror = function(event) {
                    console.error('Transaction error:', event.target.error);
                };
            };
        });
    }

    function saveCategoriesToIndexedDB(productsCount = null) {
        // Get categories from Livewire component
        @this.syncCategoriesToIndexedDB().then(categories => {
            const dbPromise = window.indexedDB.open('posDB', 1);

            dbPromise.onsuccess = function(event) {
                const db = event.target.result;
                const transaction = db.transaction('categories', 'readwrite');
                const categoriesStore = transaction.objectStore('categories');

                // Clear existing categories
                categoriesStore.clear();

                // Add categories to store
                categories.forEach(category => {
                    categoriesStore.add(category);
                });

                transaction.oncomplete = function() {
                    console.log('Categories stored in IndexedDB successfully');

                    // Display categories from IndexedDB
                    displayCategoriesFromIndexedDB();

                    // Show notification only if this is part of the sync process
                    if (productsCount !== null) {
                        alert(`Sync complete: ${categories.length} categories and ${productsCount} products stored in IndexedDB`);
                    }
                };

                transaction.onerror = function(event) {
                    console.error('Categories transaction error:', event.target.error);
                };
            };
        });
    }

    function searchProductsInIndexedDB(searchTerm) {
        if (!searchTerm) {
            // If search is empty, display all products
            displayProductsFromIndexedDB(currentCategoryId);
            return;
        }

        searchTerm = searchTerm.toLowerCase();

        const dbPromise = window.indexedDB.open('posDB', 1);

        dbPromise.onsuccess = function(event) {
            const db = event.target.result;
            const transaction = db.transaction('products', 'readonly');
            const productsStore = transaction.objectStore('products');

            // Get all products
            const getAllRequest = productsStore.getAll();

            getAllRequest.onsuccess = function() {
                let allProducts = getAllRequest.result;

                // Apply category filter if one is selected
                if (currentCategoryId !== null) {
                    allProducts = allProducts.filter(product => {
                        return product.categories &&
                               product.categories.some(cat => cat.id === currentCategoryId);
                    });
                }

                // Filter products based on search term
                const filteredProducts = allProducts.filter(product => {
                    const name = (product.name || '').toLowerCase();
                    const description = (product.description || '').toLowerCase();
                    const sku = (product.sku || '').toLowerCase();

                    return name.includes(searchTerm) ||
                           description.includes(searchTerm) ||
                           sku.includes(searchTerm);
                });

                // Display the filtered products
                renderProducts(filteredProducts);
            };
        };
    }

    function displayCategoriesFromIndexedDB() {
        const dbPromise = window.indexedDB.open('posDB', 1);

        dbPromise.onsuccess = function(event) {
            const db = event.target.result;
            const transaction = db.transaction('categories', 'readonly');
            const categoriesStore = transaction.objectStore('categories');

            // Get all categories
            const getAllRequest = categoriesStore.getAll();

            getAllRequest.onsuccess = function() {
                const categories = getAllRequest.result;
                renderCategories(categories);
            };
        };
    }

    function renderCategories(categories) {
        const container = document.getElementById('categoriesContainer');
        if (!container) return;

        // Clear existing categories
        container.innerHTML = '';

        // Add the "All" button
        const allButton = document.createElement('button');
        allButton.className = currentCategoryId === null ?
            'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2' :
            'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2';
        allButton.innerText = 'All';

        allButton.addEventListener('click', function() {
            currentCategoryId = null;
            displayProductsFromIndexedDB(null);
            renderCategories(categories); // Re-render to update selected state
        });

        container.appendChild(allButton);

        // Sort categories alphabetically
        categories.sort((a, b) => (a.name || '').localeCompare(b.name || ''));

        // Render each category
        categories.forEach(category => {
            const categoryButton = document.createElement('button');

            // Set appropriate styling based on selection state
            categoryButton.className = currentCategoryId === category.id ?
                'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2' :
                'inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2';

            categoryButton.innerText = category.name || '';

            categoryButton.addEventListener('click', function() {
                currentCategoryId = category.id;
                displayProductsFromIndexedDB(category.id);
                renderCategories(categories); // Re-render to update selected state
            });

            container.appendChild(categoryButton);
        });
    }

    function displayProductsFromIndexedDB(categoryId = null) {
        const dbPromise = window.indexedDB.open('posDB', 1);

        dbPromise.onsuccess = function(event) {
            const db = event.target.result;
            const transaction = db.transaction('products', 'readonly');
            const productsStore = transaction.objectStore('products');

            // Get all products
            const getAllRequest = productsStore.getAll();

            getAllRequest.onsuccess = function() {
                let products = getAllRequest.result;

                // Filter by category if provided
                if (categoryId !== null) {
                    products = products.filter(product => {
                        // Check if product belongs to the selected category
                        return product.categories &&
                              product.categories.some(cat => cat.id === categoryId);
                    });
                }

                // Render the products
                renderProducts(products);
            };
        };
    }

    function renderProducts(products) {
        const container = document.getElementById('productsContainer');
        if (!container) return;

        // Clear existing products
        container.innerHTML = '';

        if (products.length === 0) {
            container.innerHTML = '<div class="col-span-3 text-center py-8">No products found</div>';
            return;
        }

        // Render each product
        products.forEach(product => {
            const imageUrl = product.images && product.images.length > 0 ? product.images[0].src : '';

            const productElement = document.createElement('div');
            productElement.className = 'bg-white rounded-lg shadow-md';

            productElement.innerHTML = `
                <img src="${imageUrl}" alt="${product.name || ''}" class="w-full m-0 h-60 object-cover">
                <div class="p-4">
                    <p class="py-1 font-bold">${product.name || ''}</p>
                    <p class="py-1 font-bold">${product.price || ''}</p>
                    <p class="py-1 font-bold"><span>ID : </span> <span>${product.id || ''}</span></p>
                    <button class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 w-full mt-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-2 h-4 w-4"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        Add To Cart
                    </button>
                </div>
            `;

            container.appendChild(productElement);
        });
    }
</script>
@endpush

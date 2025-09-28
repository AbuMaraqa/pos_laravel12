<div>

    <div>
        <style>
            .category-tree {
                position: relative;
                padding: 1rem;
                background-color: #f9fafb;
                border-radius: 0.5rem;
            }

            .category-item {
                position: relative;
                margin-bottom: 0.75rem;
                padding-right: 1rem;
            }

            .category-item:last-child {
                margin-bottom: 0;
            }

            .category-item-content {
                display: flex;
                align-items: center;
                padding: 0.5rem;
                background-color: white;
                border-radius: 0.375rem;
                border: 1px solid #e5e7eb;
                transition: all 0.2s;
            }

            .category-item-content:hover {
                border-color: #3b82f6;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
            }

            .category-children {
                margin-right: 1.5rem;
                margin-top: 0.5rem;
                border-right: 2px solid #e5e7eb;
                padding-right: 1rem;
            }

            .category-checkbox {
                width: 1rem;
                height: 1rem;
                border-radius: 0.25rem;
                border: 2px solid #d1d5db;
                transition: all 0.2s;
            }

            .category-checkbox:checked {
                background-color: #3b82f6;
                border-color: #3b82f6;
            }

            .category-checkbox:focus {
                outline: 2px solid transparent;
                outline-offset: 2px;
                box-shadow: 0 0 0 2px #bfdbfe;
            }

            .category-label {
                margin-right: 0.5rem;
                font-size: 0.875rem;
                color: #374151;
                transition: color 0.2s;
            }

            .category-item-content:hover .category-label {
                color: #3b82f6;
            }

            /* تحسين مظهر الأيقونات */
            .category-icon {
                margin-left: 0.5rem;
                color: #9ca3af;
            }

            /* تحسين مظهر رسالة الخطأ */
            .error-message {
                margin-top: 0.5rem;
                padding: 0.5rem;
                border-radius: 0.375rem;
                background-color: #fee2e2;
                border: 1px solid #fecaca;
                color: #dc2626;
            }
        </style>

        <div class="grid grid-cols-4 gap-4">
            <div class="col-span-3">
                <div class="grid grid-cols-1 gap-4">
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Product Information') }}</flux:heading>
                        </div>
                        <div class="mb-3">
                            <flux:fieldset>
                                <flux:legend>{{ __('Product Type') }}</flux:legend>

                                <flux:radio.group wire:model.live="productType">
                                    <flux:radio
                                        value="simple"
                                        label="{{ __('Simple Product') }}"
                                        description="{{ __('A simple product is a standalone item without variations like size or color. It is sold as-is.') }}"
                                        checked
                                    />
                                    {{-- <flux:radio
                                        value="grouped"
                                        label="{{ __('Grouped Product') }}"
                                        description="{{ __('A grouped product is a collection of related products. It is sold as a group.') }}"
                                    />
                                    <flux:radio
                                        value="external"
                                        label="{{ __('External/Affiliate Product') }}"
                                        description="{{ __('An external or affiliate product is a product that is not sold by your store. It is sold separately from your store.') }}"
                                    /> --}}
                                    <flux:radio
                                        value="variable"
                                        label="{{ __('Variable Product') }}"
                                        description="{{ __('A variable product has multiple options like sizes or colors. Customers can choose the variation they want before purchasing.') }}"
                                    />
                                </flux:radio.group>
                            </flux:fieldset>
                        </div>
                        <div class="mb-3">
                            <flux:input wire:model="productName" label="{{ __('Product Name') }}"/>
                        </div>
                        <div>
                            <flux:textarea label="{{ __('Product Description') }}"/>
                        </div>
                    </div>
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                        <div class="mb-3">

                            <livewire:tabs-component :productType="$productType" :regular-price="$regularPrice" wire:reactive />

                        </div>
                    </div>
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Product Information') }}</flux:heading>
                        </div>
                        <div class="mb-3">
                            <flux:button wire:click="syncBeforeSave" wire:loading.attr="disabled" wire:target="syncBeforeSave" class="btn btn-primary">
                                <span wire:loading.remove wire:target="syncBeforeSave">حفظ المنتج</span>
                                <span wire:loading wire:target="syncBeforeSave" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    جاري الحفظ...
                                </span>
                            </flux:button>

                        </div>
                    </div>
                </div>
            </div>
            <div class="col-span-1">
                <div class="grid grid-cols-1 gap-4">
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Featured Image') }}</flux:heading>
                        </div>
                        <div class="mb-4">
                            {{-- @if($featuredImage)
                                <div class="relative">
                                    <img src="{{ $featuredImage }}" alt="Featured Image" class="w-full h-48 object-cover rounded-lg mb-2">
                                    <button wire:click="removeFeaturedImage" type="button" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            @endif --}}
                            <x-filepond::upload wire:model="file" />
                        </div>
                    </div>
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Gallery Images') }}</flux:heading>
                        </div>
                        <div class="mb-4">
                            {{-- @if(!empty($galleryImages))
                                <div class="grid grid-cols-3 gap-2 mb-2">
                                    @foreach($galleryImages as $index => $image)
                                        <div class="relative">
                                            <img src="{{ $image }}" alt="Gallery Image" class="w-full h-24 object-cover rounded-lg">
                                            <button wire:click="removeGalleryImage({{ $index }})" type="button" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif --}}
                            <x-filepond::upload multiple wire:model="files" />
                        </div>
                    </div>
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Brands') }}</flux:heading>
                        </div>
                        <div class="mb-4">
                            <flux:select wire:model="brandId" placeholder="Choose brand...">
                                <flux:select.option value="">Select brand</flux:select.option>
                                @foreach($this->getBrands() as $brand)
                                    <flux:select.option value="{{ $brand['id'] }}">{{ $brand['name'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                    </div>

                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Categories') }}</flux:heading>
                        </div>
                        <div class="mb-4">
                            <div class="category-tree">
                                @foreach($this->getCategories() as $category)
                                    @include('livewire.pages.product.partials.category-checkbox-item', ['category' => $category])
                                @endforeach
                            </div>
                            @error('selectedCategories')
                                <div class="error-message">
                                    <i class="fas fa-exclamation-circle ml-1"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

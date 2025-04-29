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

        .category-icon {
            margin-left: 0.5rem;
            color: #9ca3af;
        }

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
                        <flux:heading size="xl">{{ __('Edit Product') }}</flux:heading>
                    </div>
                    <div class="mb-3">
                        <flux:fieldset>
                            <flux:legend>{{ __('Product Type') }}</flux:legend>

                            <flux:radio.group wire:model.live="productType">
                                <flux:radio
                                    value="simple"
                                    label="{{ __('Simple Product') }}"
                                    description="{{ __('A simple product is a standalone item without variations like size or color. It is sold as-is.') }}"
                                />
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
                        <flux:textarea wire:model="productDescription" label="{{ __('Product Description') }}"/>
                    </div>
                </div>
                <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="mb-3">
                        <livewire:tabs-component
                            :productId="$productId"
                            :productType="$productType"
                            :regular-price="$regularPrice"
                            :key="'tabs-'.$productId"
                        />
                    </div>
                </div>
                <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="mb-3">
                        <flux:heading size="xl">{{ __('Save Changes') }}</flux:heading>
                    </div>
                    <div class="mb-3">
                        <flux:button wire:click="syncBeforeSave" wire:loading.remove wire:loading.attr="disabled" wire:target="syncBeforeSave" class="btn btn-primary">
                            {{ __('Update Product') }}
                        </flux:button>
                        <span wire:loading wire:target="syncBeforeSave" class="text-sm text-gray-500">
                            {{ __('Updating...') }}
                        </span>
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

    {{-- @if (session()->has('success'))
        <div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="fixed bottom-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
            {{ session('error') }}
        </div>
    @endif --}}
</div>

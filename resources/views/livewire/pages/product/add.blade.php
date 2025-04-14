<div>
    <!-- رسائل التحقق -->
    @if ($errors->any())
        <div class="alert alert-error mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="flex flex-col gap-1">
                <span class="font-bold">يرجى تصحيح الأخطاء التالية:</span>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <!-- رسائل التحقق حسب نوع المنتج -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
        <!-- معلومات المنتج الأساسية -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">معلومات المنتج الأساسية</h2>
                @error('productName')
                    <div class="text-error text-sm">{{ $message }}</div>
                @enderror
                @error('productType')
                    <div class="text-error text-sm">{{ $message }}</div>
                @enderror
                @if($productType === 'simple' || $productType === 'external')
                    @error('regularPrice')
                        <div class="text-error text-sm">{{ $message }}</div>
                    @enderror
                @endif
                @if($productType === 'external')
                    @error('externalUrl')
                        <div class="text-error text-sm">{{ $message }}</div>
                    @enderror
                @endif
            </div>
        </div>

        <!-- التصنيفات والخصائص -->
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                <h2 class="card-title">التصنيفات والخصائص</h2>
                @error('selectedCategories')
                    <div class="text-error text-sm">{{ $message }}</div>
                @enderror
                @if($productType === 'variable')
                    @error('selectedAttributes')
                        <div class="text-error text-sm">{{ $message }}</div>
                    @enderror
                    @error('variations')
                        <div class="text-error text-sm">{{ $message }}</div>
                    @enderror
                @endif
                @if($productType === 'grouped')
                    @error('groupedProducts')
                        <div class="text-error text-sm">{{ $message }}</div>
                    @enderror
                @endif
            </div>
        </div>
    </div>

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

                            <flux:radio.group wire:model="productType">
                                <flux:radio
                                    value="simple"
                                    label="{{ __('Simple Product') }}"
                                    description="{{ __('A simple product is a standalone item without variations like size or color. It is sold as-is.') }}"
                                    checked
                                />
                                <flux:radio
                                    value="grouped"
                                    label="{{ __('Grouped Product') }}"
                                    description="{{ __('A grouped product is a collection of related products. It is sold as a group.') }}"
                                />
                                <flux:radio
                                    value="external"
                                    label="{{ __('External/Affiliate Product') }}"
                                    description="{{ __('An external or affiliate product is a product that is not sold by your store. It is sold separately from your store.') }}"
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
                        <flux:textarea label="{{ __('Product Description') }}"/>
                    </div>
                </div>
                <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="mb-3">
{{--                        <div class="flex">--}}
{{--                            <span>{{ __('Product Data') }}</span> <span>---</span>--}}
{{--                            <flux:select style="display:inline-block;width:250px" wire:model="industry" placeholder="{{ __('Product Type') }}">--}}
{{--                                <flux:select.option checked>{{ __('Simple Product') }}</flux:select.option>--}}
{{--                                <flux:select.option>{{ __('Grouped Product') }}</flux:select.option>--}}
{{--                                <flux:select.option>{{ __('External/Affiliate Product') }}</flux:select.option>--}}
{{--                                <flux:select.option>{{ __('Variable Product') }}</flux:select.option>--}}
{{--                            </flux:select>--}}

{{--                        </div>--}}

                        <livewire:tabs-component :regular-price="$regularPrice" wire:reactive />

                    </div>
                </div>
                <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="mb-3">
                        <flux:heading size="xl">{{ __('Product Information') }}</flux:heading>
                    </div>
                    <div class="mb-3">
                        <flux:button wire:click="syncBeforeSave" wire:loading.attr="disabled" wire:target="syncBeforeSave" class="btn btn-primary">
                            حفظ المنتج
                        </flux:button>

                    </div>
                </div>
            </div>
        </div>
        <form wire:submit.prevent="uploadImage">
            <div class="col-span-1">
                <div class="grid grid-cols-1 gap-4">
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Featured Image') }}</flux:heading>
                        </div>
                        <div class="mb-3">
                            <x-filepond::upload wire:model="file" />
                        </div>
                    </div>
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Galary Images') }}</flux:heading>
                        </div>
                        <div class="mb-3">
                            <x-filepond::upload multiple wire:model="files" />
                        </div>
                    </div>
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Category') }}</flux:heading>
                        </div>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">التصنيفات</label>
                            <div class="space-y-2">
                                @foreach($this->getCategories() as $category)
                                    <div class="flex items-center">
                                        <input type="checkbox"
                                               wire:model="selectedCategories"
                                               value="{{ $category['id'] }}"
                                               id="category_{{ $category['id'] }}"
                                               class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-700">
                                        <label for="category_{{ $category['id'] }}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                            {{ $category['name'] }}
                                        </label>
                                    </div>
                                    @if($category['children'])
                                        <div class="ml-4">
                                            @foreach($category['children'] as $child)
                                                <div class="flex items-center">
                                                    <input type="checkbox"
                                                           wire:model="selectedCategories"
                                                           value="{{ $child['id'] }}"
                                                           id="category_{{ $child['id'] }}"
                                                           class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded dark:border-gray-600 dark:bg-gray-700">
                                                    <label for="category_{{ $child['id'] }}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                                        {{ $child['name'] }}
                                                    </label>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                            @error('selectedCategories')
                                <div class="mt-1 text-sm text-red-600 dark:text-red-400">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div>
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
                    <button type="submit">Button Save</button>
                    <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                        <div class="mb-3">
                            <flux:heading size="xl">{{ __('Category') }}</flux:heading>
                        </div>
                        @php
                            function renderCategoryTree($categories, $level = 0) {
                                foreach ($categories as $category) {
                                    echo '<div class="ml-' . ($level * 4) . '">';
                                    echo '<flux:checkbox label="' . str_repeat('— ', $level) . e($category['name'] ?? '') . '" value="' . e($category['id'] ?? '') . '" />';
                                    if (!empty($category['children'])) {
                                        renderCategoryTree($category['children'], $level + 1);
                                    }
                                    echo '</div>';
                                }
                            }
                        @endphp

                        <div class="mb-3">
                            <flux:checkbox.group wire:model="selectedCategories">
                                @foreach($this->getCategories() as $cat)
                                    <flux:checkbox label="{{ $cat['name'] }}" value="{{ $cat['id'] }}" />

                                    @if (!empty($cat['children']))
                                        @foreach($cat['children'] as $child)
                                            <div class="ml-4">
                                                <flux:checkbox label="— {{ $child['name'] }}" value="{{ $child['id'] }}" />
                                            </div>
                                        @endforeach
                                    @endif
                                @endforeach
                            </flux:checkbox.group>

                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

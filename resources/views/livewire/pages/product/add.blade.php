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

                            <flux:radio.group>
                                <flux:radio
                                    value="simple"
                                    label="{{ __('Simple Product') }}"
                                    description="{{ __('A simple product is a standalone item without variations like size or color. It is sold as-is.') }}"
                                    checked
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
                        <flux:input wire:model="name" label="{{ __('Product Name') }}"/>
                    </div>
                    <div>
                        <flux:textarea label="{{ __('Product Description') }}"/>
                    </div>
                </div>
                <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="mb-3">

                        <livewire:tabs-component />

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
                    <button type="submit">asdasdsad</button>
                </div>
            </div>
        </form>
    </div>
</div>

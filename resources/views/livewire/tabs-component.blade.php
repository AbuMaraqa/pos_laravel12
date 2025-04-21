<div x-data="{
    openTab: @entangle('activeTab'),
    isStockManagementEnabled: @entangle('isStockManagementEnabled'),
    regularPrice: @entangle('localRegularPrice'),
    salePrice: @entangle('localSalePrice'),
    sku: @entangle('localSku'),
    stockQuantity: @entangle('stockQuantity'),
    allowBackorders: @entangle('allowBackorders'),
    stockStatus: @entangle('stockStatus'),
    soldIndividually: @entangle('soldIndividually'),
    productType: @entangle('productType'),
    showAttributesTab: @entangle('showAttributesTab'),
    showStockFields() {
        return this.isStockManagementEnabled;
    },
    showStockStatus() {
        return this.isStockManagementEnabled && this.productType === 'simple';
    }
}">

    <!-- قائمة التبويبات -->
    <div class="flex space-x-4 border-b-2">
        <!-- التبويب الأول -->
        <button @click="openTab = 1" :class="{ 'border-b-2 border-blue-500': openTab === 1 }"
            class="py-2 px-4 text-sm font-semibold focus:outline-none">
            {{ __('General') }}
        </button>

        <!-- التبويب الثاني -->
        <button @click="openTab = 2" :class="{ 'border-b-2 border-blue-500': openTab === 2 }"
            class="py-2 px-4 text-sm font-semibold focus:outline-none">
            {{ __('Inventory') }}
        </button>

        <!-- التبويب الثالث -->
        <button @click="openTab = 3" :class="{ 'border-b-2 border-blue-500': openTab === 3 }"
            class="py-2 px-4 text-sm font-semibold focus:outline-none">
            {{ __('Shipping') }}
        </button>

        <!-- تبويب الصفات (يظهر فقط للمنتج المتعدد) -->
        <button x-show="showAttributesTab" @click="openTab = 4" :class="{ 'border-b-2 border-blue-500': openTab === 4 }"
            class="py-2 px-4 text-sm font-semibold focus:outline-none">
            {{ __('Attributes') }}
        </button>

        <button @click="openTab = 5" :class="{ 'border-b-2 border-blue-500': openTab === 5 }"
            class="py-2 px-4 text-sm font-semibold focus:outline-none">
            {{ __('Linked Products') }}
        </button>

        <button @click="openTab = 6" :class="{ 'border-b-2 border-blue-500': openTab === 6 }"
            class="py-2 px-4 text-sm font-semibold focus:outline-none">
            {{ __('Price') }}
        </button>

        <button @click="openTab = 7" :class="{ 'border-b-2 border-blue-500': openTab === 7 }"
            class="py-2 px-4 text-sm font-semibold focus:outline-none">
            {{ __('Translation') }}
        </button>
    </div>

    <!-- محتوى التبويبات -->
    <div class="mt-4">
        <!-- محتوى التبويب الأول -->
        <div x-show="openTab === 1" x-transition>
            <div class="mb-3">
                <flux:input wire:model.live="localRegularPrice" label="{{ __('Regular price') }}" />
            </div>
            <div class="mb-3">
                <flux:input wire:model.live="localSalePrice" label="{{ __('Sale price') }}" />
            </div>
            <div class="mb-3">
                <flux:input wire:model.live="localSku" label="{{ __('SKU') }}" />
            </div>
        </div>

        <!-- محتوى التبويب الثاني -->
        <div x-show="openTab === 2" x-transition>
            <div class="mb-3">
                <flux:checkbox x-model="isStockManagementEnabled" value="Stock management"
                    label="{{ __('Stock management') }}"
                    description="{{ __('Track stock quantity for this product.') }}" />
            </div>

            <!-- الحقول التي تظهر فقط عند تفعيل Stock Management -->
            <div x-show="showStockFields()" x-transition>
                <div class="mb-3">
                    <flux:input wire:model="stockQuantity" label="{{ __('Stock Quantity') }}" />
                </div>
                <div class="mb-3">
                    <flux:radio.group wire:model="allowBackorders" variant="segmented"
                        label="{{ __('Allow Backorders?') }}">
                        <flux:radio value="0" label="{{ __('Do not allow') }}" />
                        <flux:radio value="1" label="{{ __('Allow, but notify customer') }}" />
                        <flux:radio value="2" label="{{ __('Allow') }}" />
                    </flux:radio.group>
                </div>
                <div class="mb-3">
                    <flux:input wire:model="lowStockThreshold" label="{{ __('Low Stock Threshold') }}" />
                </div>
            </div>

            <div x-show="showStockStatus" class="mb-3">
                <flux:radio.group variant="segmented" label="{{ __('Stock Status') }}">
                    <flux:radio value="in_stock" label="{{ __('In stock') }}" checked />
                    <flux:radio value="out_of_stock" label="{{ __('Out of stock') }}" />
                    <flux:radio value="on_backorder" label="{{ __('On backorder') }}" />
                </flux:radio.group>
            </div>

            <div class="mb-3">
                <flux:separator />
            </div>

            <!-- التفاعل مع الخيارات المدفوعة -->
            <div class="mb-3">
                <flux:checkbox wire:model="terms" label="{{ __('Sold individually') }}" />
            </div>
        </div>

        <!-- محتوى التبويب الثالث -->
        <div x-show="openTab === 3" x-transition>
            <h2 class="text-xl font-bold mb-2">محتوى التبويب 3</h2>
            <p>هذا هو محتوى التبويب الثالث.</p>
        </div>

        <!-- محتوى تبويب الصفات (يظهر فقط للمنتج المتعدد) -->
        <div x-show="openTab === 4 && showAttributesTab" x-transition>
            <livewire:variation-manager
                :productId="$productId"
                :key="'variation-manager-'.$productId"
            />
        </div>

        <div x-show="openTab === 5" x-transition>
            شسي
        </div>

        <div x-show="openTab === 6" x-transition>


            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">
                                {{ __('Group') }}
                            </th>
                            <th>
                                {{ __('RegularPrice') }}
                            </th>
                            <th>
                                {{ __('Sale Price') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->getRoles() as $role)
                            <tr
                                class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                                <td class="px-6 py-4">
                                    {{ $role['name'] }}
                                </td>
                                <td class="px-6 py-4">
                                    <flux:input wire:model.defer="mrbpData.{{ $role['role'] }}.regularPrice" />
                                </td>
                                <td class="px-6 py-4">
                                    <flux:input wire:model.defer="mrbpData.{{ $role['role'] }}.salePrice" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div x-show="openTab === 7" x-transition>
            {{ __('Translation') }}
        </div>
    </div>
</div>

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
        return this.productType === 'simple';
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
            class="py-2 px-4 text-sm font-semibold focus:outline-none relative">
            {{ __('Attributes') }}
            <span x-show="showAttributesTab && openTab !== 4" class="absolute top-0 right-0 -mt-1 -mr-1 px-2 py-1 text-xs font-bold text-white bg-red-500 rounded-full">!</span>
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
                <flux:checkbox
                    x-model="isStockManagementEnabled"
                    wire:model.live="isStockManagementEnabled"
                    value="Stock management"
                    label="{{ __('Stock management') }}"
                    description="{{ __('Track stock quantity for this product.') }}" />
            </div>

            <!-- الحقول التي تظهر فقط عند تفعيل Stock Management -->
            <div x-show="showStockFields()" x-transition>
                <div class="mb-3">
                    <flux:input wire:model.live="stockQuantity" label="{{ __('Stock Quantity') }}" />
                </div>
                <div class="mb-3">
                    <flux:radio.group wire:model.live="allowBackorders" variant="segmented"
                        label="{{ __('Allow Backorders?') }}">
                        <flux:radio value="no" label="{{ __('Do not allow') }}" />
                        <flux:radio value="notify" label="{{ __('Allow, but notify customer') }}" />
                        <flux:radio value="yes" label="{{ __('Allow') }}" />
                    </flux:radio.group>
                    <!-- Debug value -->
                    <p class="text-xs text-gray-500 mt-1">Current value: {{ $allowBackorders }}</p>
                </div>
                <div class="mb-3">
                    <flux:input wire:model.live="lowStockThreshold" type="number" label="عدد المنتجات المتبقية لوضع حالة مخزون المنتج كـ منخفض المخزون" />
                    <!-- Debug value -->
                    <p class="text-xs text-gray-500 mt-1">Current value: {{ $lowStockThreshold }}</p>
                </div>
            </div>

            <div x-show="showStockStatus" class="mb-3">
                <flux:radio.group wire:model.live="stockStatus" variant="segmented" label="{{ __('Stock Status') }}">
                    <flux:radio value="instock" label="{{ __('In stock') }}" />
                    <flux:radio value="outofstock" label="{{ __('Out of stock') }}" />
                    <flux:radio value="onbackorder" label="{{ __('On backorder') }}" />
                </flux:radio.group>
                <!-- Debug value -->
                <p class="text-xs text-gray-500 mt-1">Current value: {{ $stockStatus }}</p>
            </div>

            <div class="mb-3">
                <flux:separator />
            </div>

            <!-- التفاعل مع الخيارات المدفوعة -->
            <div class="mb-3">
                <flux:checkbox wire:model.live="soldIndividually" label="{{ __('Sold individually') }}" />
            </div>
        </div>

        <!-- محتوى التبويب الثالث -->
        <div x-show="openTab === 3" x-transition>
            <h2 class="text-xl font-bold mb-2">محتوى التبويب 3</h2>
            <p>هذا هو محتوى التبويب الثالث.</p>
        </div>

        <!-- محتوى تبويب الصفات (يظهر فقط للمنتج المتعدد) -->
        <div x-show="openTab === 4 && showAttributesTab" x-transition>
            {{-- <div class="p-4 mb-4 text-sm text-blue-800 rounded-lg bg-blue-50 dark:bg-gray-800 dark:text-blue-400" role="alert">
              <span class="font-medium">تعليمات الاستخدام:</span>
              <ol class="list-decimal list-inside mt-1">
                <li>حدد الخصائص (مثل اللون، المقاس) من القائمة أدناه</li>
                <li>اختر القيم المتاحة لكل خاصية (مثل: أحمر، أسود، 40، 42، الخ)</li>
                <li>انقر على زر "توليد المتغيرات" لإنشاء جميع المتغيرات المحتملة</li>
                <li>أدخل سعر وكمية لكل متغير في الجدول الذي سيظهر</li>
              </ol>
            </div> --}}
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

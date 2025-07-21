<div class="p-6 bg-gray-50 min-h-screen"
     wire:ignore.self
     x-data="{
    selectedVariations: @entangle('selectedVariationIds'),
    variationIds: @js(collect($this->getVariationProduct)->where('id', '!=', '')->pluck('id')->toArray()),
    get allSelected() {
        return this.selectedVariations.length === this.variationIds.length;
    },
    get someSelected() {
        return this.selectedVariations.length > 0 && this.selectedVariations.length < this.variationIds.length;
    },
    toggleAll() {
        if (this.allSelected) {
            this.selectedVariations = [];
        } else {
            this.selectedVariations = [...this.variationIds];
        }
    },
    isSelected(variationId) {
        return this.selectedVariations.includes(variationId);
    },
    processSelected() {
        if (this.selectedVariations.length > 0) {
            $wire.processSelectedVariations();
        } else {
            alert('لم يتم تحديد أي عنصر');
        }
    }
}">
    <h2 class="text-2xl font-bold mb-6 text-gray-700">صور المنتج</h2>

    {{-- عرض صورة المنتج الرئيسية وصور الجاليري --}}
    <div class="flex flex-col md:flex-row items-start gap-8 mb-8">
        {{-- صورة المنتج الرئيسية --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="col-span-1">
                <h3 class="font-semibold text-gray-600 mb-2">الصورة الرئيسية</h3>
                @if(!empty($mainImage))
                    <img src="{{ $mainImage }}" alt="الصورة الرئيسية" class="w-48 h-48 object-contain rounded-lg border bg-gray-100 shadow-sm">
                @else
                    <div class="w-48 h-48 flex items-center justify-center bg-gray-100 rounded-lg border text-gray-400">
                        لا توجد صورة
                    </div>
                @endif
                <x-filepond::upload wire:model.live="mainImageUpload" />
            </div>
            <div class="col-span-2">
                <h3 class="font-semibold text-gray-600 mb-2">معرض الصور</h3>
                <div class="flex flex-wrap gap-2">
                    @forelse($galleryImages as $gallery)
                        <img src="{{ $gallery }}" alt="صورة جاليري" class="w-24 h-24 object-contain rounded border bg-gray-50">
                    @empty
                        <div class="w-48 h-48 flex items-center justify-center bg-gray-100 rounded-lg border text-gray-400">
                            <span class="text-gray-400">لا توجد صور في المعرض</span>
                        </div>
                    @endforelse
                </div>
                <x-filepond::upload wire:model.live="galleryUploads" multiple/>
            </div>
        </div>
    </div>

    {{-- جدول المتغيرات --}}
    <div class="overflow-x-auto bg-white rounded-xl shadow">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
                <tr>
                    <th class="px-4 py-3 text-right">
                        <label class="flex items-center justify-end">
                            <input type="checkbox"
                                   x-bind:checked="allSelected"
                                   x-bind:indeterminate="someSelected"
                                   @change="toggleAll()"
                                   class="ml-2 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-xs font-medium text-gray-700">تحديد الكل</span>
                        </label>
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700">اسم المتغير</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700">الصورة الحالية</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700">رفع صورة جديدة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($this->getVariationProduct as $variation)
                    @if (!empty($variation['id']))
                        <tr x-bind:class="isSelected('{{ $variation['id'] }}') ? 'bg-blue-50' : ''">
                            <td class="px-4 py-3 text-center">
                                <input type="checkbox"
                                       x-model="selectedVariations"
                                       value="{{ $variation['id'] }}"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            </td>
                            <td class="px-4 py-3 font-semibold text-gray-800">{{ $variation['name'] }}</td>
                            <td class="px-4 py-3">
                                @if(!empty($variation['image']['src']))
                                    <img src="{{ $variation['image']['src'] }}" alt="صورة المتغير"
                                         class="w-20 h-20 object-contain rounded border bg-gray-50">
                                @else
                                    <span class="text-gray-400">لا توجد صورة</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <x-filepond::upload wire:model.live="variationsImage.{{ $variation['id'] }}" />
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- شريط العمليات للعناصر المحددة --}}
    <div x-show="selectedVariations.length > 0"
         x-transition
         class="mt-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
        <div class="flex items-center justify-between">
            <span class="text-sm text-blue-800">
                تم تحديد <span x-text="selectedVariations.length"></span> عنصر
            </span>
            <div class="flex gap-2">
                <button type="button"
                        @click="processSelected()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm">
                    تنفيذ على المحدد
                </button>
                <button type="button"
                        @click="selectedVariations = []"
                        class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors text-sm">
                    إلغاء التحديد
                </button>
            </div>
        </div>
    </div>

    {{-- معلومات إضافية للتطوير --}}
    <div x-show="selectedVariations.length > 0" class="mt-2">
        <details class="text-xs text-gray-500">
            <summary class="cursor-pointer hover:text-gray-700">عرض العناصر المحددة (للتطوير)</summary>
            <pre x-text="JSON.stringify(selectedVariations, null, 2)" class="mt-2 p-2 bg-gray-100 rounded text-xs overflow-x-auto"></pre>
        </details>
    </div>
</div>

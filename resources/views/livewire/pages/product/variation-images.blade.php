<div class="p-6 bg-gray-50 min-h-screen">
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
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700">اسم المتغير</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700">الصورة الحالية</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-700">رفع صورة جديدة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($this->getVariationProduct as $variation)
                    @if (!empty($variation['id']))
                        <tr>
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
</div>

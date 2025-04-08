<div>
    <h2 class="text-lg font-bold mb-4">الخصائص</h2>

    @foreach ($attribute as $index => $attr)
        <div class="mb-4 border p-3 rounded bg-gray-100 relative">
            <!-- اسم الخاصية -->
            <input type="text" wire:model="attribute.{{ $index }}.name" placeholder="اسم الخاصية" class="input input-bordered w-full mb-2" />

            <!-- الخيارات -->
            @foreach ($attr['options'] as $optIndex => $option)
                <div class="flex items-center mb-1">
                    <input type="text" wire:model="attribute.{{ $index }}.options.{{ $optIndex }}" placeholder="الخيار" class="input input-bordered w-full" />
                    <!-- زر الحذف للخيار -->
                    <button wire:click="removeOption({{ $index }}, {{ $optIndex }})" type="button" class="btn btn-sm btn-error ml-2">×</button>
                </div>
            @endforeach

            <!-- إضافة خيار -->
            <button wire:click="addOption({{ $index }})" type="button" class="btn btn-sm mt-2">+ خيار</button>

            <!-- إزالة الخاصية -->
            <button wire:click="removeAttribute({{ $index }})" type="button" class="btn btn-sm btn-error absolute top-0 left-0 m-2">×</button>
        </div>
    @endforeach

    <flux:button type="button" wire:click="addAttribute" class="btn btn-primary">+ إضافة خاصية</flux:button>

    <div class="mt-4">
        <button type="button" wire:click="generateVariations" class="btn btn-success">توليد المتغيرات</button>
    </div>

    <div class="mt-6">
        <h3 class="text-md font-semibold">المتغيرات:</h3>
        <ul class="list-disc pl-4">
            @foreach ($variations as $variation)
                <li>
                    @foreach ($variation['options'] as $option)
                        <span>{{ $option }}</span>
                        @if (!$loop->last)
                            <span> - </span> <!-- فاصل بين الخيارات -->
                        @endif
                    @endforeach
                </li>
            @endforeach
        </ul>
    </div>
</div>

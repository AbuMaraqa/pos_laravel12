<div class="category-item">
    <div class="category-item-content">
        <input type="checkbox"
               wire:model="selectedCategories"
               value="{{ $category['id'] }}"
               id="category_{{ $category['id'] }}"
               class="category-checkbox">
        <label for="category_{{ $category['id'] }}" class="category-label">
            {{ $category['name'] }}
        </label>
    </div>
    @if(!empty($category['children']))
        <div class="category-children">
            @foreach($category['children'] as $child)
                @include('livewire.pages.product.partials.category-checkbox-item', ['category' => $child])
            @endforeach
        </div>
    @endif
</div>

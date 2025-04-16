<div class="category-item">
    <div class="category-item-content">
        <label for="category_{{ $category['id'] }}" class="category-label">
            {{ $category['name'] }}
        </label>
    </div>
    @if(!empty($category['children']))
        <div class="category-children">
            @foreach($category['children'] as $child)
                @include('livewire.pages.category.partials.category-item', ['category' => $child])
            @endforeach
        </div>
    @endif
</div>

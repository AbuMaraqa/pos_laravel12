<div>
    <style>
        .category-tree {
            position: relative;
            padding: 1rem;
            background-color: #f9fafb;
            border-radius: 0.5rem;
        }

        .category-item {
            position: relative;
            margin-bottom: 0.75rem;
            padding-right: 1rem;
        }

        .category-item:last-child {
            margin-bottom: 0;
        }

        .category-item-content {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            background-color: white;
            border-radius: 0.375rem;
            border: 1px solid #e5e7eb;
            transition: all 0.2s;
        }

        .category-item-content:hover {
            border-color: #3b82f6;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
        }

        .category-children {
            margin-right: 1.5rem;
            margin-top: 0.5rem;
            border-right: 2px solid #e5e7eb;
            padding-right: 1rem;
        }

        .category-checkbox {
            width: 1rem;
            height: 1rem;
            border-radius: 0.25rem;
            border: 2px solid #d1d5db;
            transition: all 0.2s;
        }

        .category-checkbox:checked {
            background-color: #3b82f6;
            border-color: #3b82f6;
        }

        .category-checkbox:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
            box-shadow: 0 0 0 2px #bfdbfe;
        }

        .category-label {
            margin-right: 0.5rem;
            font-size: 0.875rem;
            color: #374151;
            transition: color 0.2s;
        }

        .category-item-content:hover .category-label {
            color: #3b82f6;
        }

        /* تحسين مظهر الأيقونات */
        .category-icon {
            margin-left: 0.5rem;
            color: #9ca3af;
        }

        /* تحسين مظهر رسالة الخطأ */
        .error-message {
            margin-top: 0.5rem;
            padding: 0.5rem;
            border-radius: 0.375rem;
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
    </style>

    <div class="grid grid-cols-4 gap-4">
        <div class="col-span-3">
            <div class="grid grid-cols-1 gap-4">
                <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-900 dark:border-gray-700">
                    <div class="mb-3">
                        <flux:heading size="xl">{{ __('Add Category') }}</flux:heading>
                    </div>
                    <form class="grid grid-cols-2 gap-4" wire:submit.prevent="addCategory">
                        <div class="col-span-1">
                            <flux:input label="{{ __('Name') }}" wire:model="name" />
                        </div>
                        <div class="col-span-1">
                            <flux:select label="{{ __('Parent') }}" wire:model="parentId">
                            @foreach($this->listCategories() as $category)
                                <flux:select.option value="{{ $category['id'] }}">{{ $category['name'] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <div class="col-span-1">
                            <flux:button type="submit" class="w-full">{{ __('Add Category') }}</flux:button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-span-1">
            <div class="grid grid-cols-1 gap-4">
                <div class="col-span-1 max-w p-6 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-800 dark:border-gray-700">
                    <div class="mb-3">
                        <flux:heading size="xl">{{ __('Categories') }}</flux:heading>
                    </div>
                    <div class="mb-4">
                        <div class="category-tree">
                            @foreach($this->getCategories() as $category)
                                @include('livewire.pages.category.partials.category-item', ['category' => $category])
                            @endforeach
                        </div>
                        @error('selectedCategories')
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle ml-1"></i>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

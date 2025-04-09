<div>

    <flux:modal name="edit-profile" variant="flyout">
        <form wire:submit.prevent="{{ $isEditAttribute ? 'updateAttribute' : 'saveAttribute' }}" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Attribute') }}</flux:heading>
            </div>

            <flux:field>
                <flux:label>{{ __('Attribute Name') }}</flux:label>
                <flux:input wire:model="data.name" wire:keyup.debounce.500ms='generateSlug' type="text" />
            </flux:field>
            <flux:field>
                <flux:label>{{ __('Slug') }}</flux:label>
                <flux:input wire:model="data.slug" type="text" />
            </flux:field>

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    <flux:modal name="edit-term" class="md:w-576">
        <form wire:submit.prevent="saveTerm" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Configure terms') }}</flux:heading>
            </div>

            <flux:field>
                <flux:label>{{ __('Name') }}</flux:label>
                <flux:input wire:model="terms.name" wire:keyup.debounce.500ms='generateSlug' type="text" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Slug') }}</flux:label>
                <flux:input wire:model="terms.slug" type="text" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea />
            </flux:field>

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">{{ __('Save changes') }}</flux:button>
            </div>
            <flux:separator />
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y-2 divide-gray-200">
                    <thead class="ltr:text-left rtl:text-right">
                    <tr class="*:font-medium *:text-gray-900">
                        <th class="px-3 py-2 whitespace-nowrap">{{ __('Name') }}</th>
                        <th class="px-3 py-2 whitespace-nowrap">{{ __('Description') }}</th>
                        <th class="px-3 py-2 whitespace-nowrap">{{ __('Slug') }}</th>
                        <th class="px-3 py-2 whitespace-nowrap">{{ __('Count') }}</th>
                        <th></th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200">
                    <tbody class="divide-y divide-gray-200">
                    @forelse($this->loadTerms() as $term)
                        <tr class="*:text-gray-900 *:first:font-medium">
                            <td class="px-3 py-2 whitespace-nowrap">{{ $term['name'] ?? '-' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $term['description'] ?? '-' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $term['slug'] ?? '-' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $term['count'] ?? 0 }}</td>
                            <td>
                                <flux:button variant="subtle" size="xs" wire:click="deleteTerm({{ $term['id'] }})" icon="trash"></flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-500">
                                {{ __('No terms found for this attribute.') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                    </tbody>
                </table>
            </div>
        </form>
    </flux:modal>

    <div class="mb-3">
        <flux:modal.trigger name="edit-profile">
            <flux:button variant="primary" icon="plus">{{ __('Add Attribute') }}</flux:button>
        </flux:modal.trigger>
    </div>
{{--    <flux:input class="mt-3" wire:model.live.debounce.500ms="search" placeholder="{{ __('Search') }}"/>--}}

{{--    <flux:button wire:click="resetCategory" class="mt-2">--}}
{{--        {{ __('All') }}--}}
{{--    </flux:button>--}}

    <div class="relative overflow-x-auto sm:rounded-lg">
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">
                    {{ __('Attribute name') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Slug') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Term') }}
                </th>
                <th scope="col" class="px-6 py-3">
                    {{ __('Actions') }}
                </th>
            </tr>
            </thead>
            <tbody>
            @foreach($attribute as $attr)
                <tr class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                    <td scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                        {{ $attr['name'] }}
                    </td>
                    <td>
                        {{ $attr['slug'] }}
                    </td>
                    <td>
                        <flux:button variant="subtle" size="xs" wire:click="editTerm({{ $attr['id'] }})" icon="plus">{{ __('Configure terms') }}</flux:button>

                    @foreach($attr['terms'] as $term)
                            <flux:badge color="indigo">
                                {{ $term['name'] }}
                            </flux:badge>
                        @endforeach
                    </td>
                    <td>
                        <flux:dropdown>
                            <flux:button icon:trailing="chevron-down">{{ __('Options') }}</flux:button>

                            <flux:menu>
                                <flux:menu.item icon="eye">{{ __('View') }}</flux:menu.item>
                                <flux:menu.item icon="pencil-square" wire:click="editAttribute({{ $attr['id'] }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item variant="danger" wire:click="delete({{ $attr['id'] }})" icon="trash">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

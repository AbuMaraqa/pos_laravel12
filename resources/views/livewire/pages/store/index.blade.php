<div>
    <flux:modal name="add-store" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Store') }}</flux:heading>
            </div>

            <flux:input wire:model='data.name' label="{{ __('Store Name') }}" placeholder="Your name" />
            <flux:textarea wire:model='data.address' label="{{ __('Address') }}" placeholder="Your address" />
            <flux:input wire:model='data.notes' label="{{ __('Notes') }}" placeholder="Your notes" />
            <div class="flex">
                <flux:spacer />

                <flux:button wire:click='save' variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="edit-store" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Update Store') }}</flux:heading>
            </div>

            <flux:input wire:model='data.name' label="{{ __('Store Name') }}" placeholder="Your name" />
            <flux:textarea wire:model='data.address' label="{{ __('Address') }}" placeholder="Your address" />
            <flux:input wire:model='data.notes' label="{{ __('Notes') }}" placeholder="Your notes" />
            <div class="flex">
                <flux:spacer />

                <flux:button wire:click='update' variant="primary">{{ __('Update') }}</flux:button>
            </div>
        </div>
    </flux:modal>
    <div>
        <flux:modal.trigger name="add-store">
            <flux:button variant="primary">{{ __('Add Store') }}</flux:button>
        </flux:modal.trigger>
    </div>
    <div class="relative overflow-x-auto shadow-lg sm:rounded-lg my-6 border border-gray-200">
        <table
            class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400 divide-y divide-gray-200">
            <thead
                class="text-xs font-medium uppercase bg-gradient-to-r from-indigo-50 to-blue-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-4 rounded-tl-lg text-center">
                        {{ __('Store Name') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Address') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center">
                        {{ __('Notes') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold text-center rounded-tr-lg">
                        {{ __('Actions') }}
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($stores as $store)
                    <tr class="bg-white hover:bg-gray-50 dark:bg-gray-800 transition-colors duration-200 ease-in-out">
                        <td class="p-4 text-center">
                            {{ $store->name }}
                        </td>
                        <td class="p-4 text-center">
                            {{ $store->address }}
                        </td>
                        <td class="p-4 text-center">
                            {{ $store->notes }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            <flux:dropdown>
                                <flux:button icon:trailing="chevron-down" class="bg-indigo-500 hover:bg-indigo-600">
                                    {{ __('Options') }}
                                </flux:button>

                                <flux:menu>
                                    <flux:menu.item wire:click='edit({{ $store->id }})' wire:navigate icon="pencil-square">{{ __('Edit') }}
                                    </flux:menu.item>
                                    <flux:menu.item variant="danger"
                                        wire:confirm="Are you sure you want to delete this product?" icon="trash">
                                        {{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center">
                            <span>{{ __('No data to display') }}</span>
                        </td>
                    </tr>
                @endforelse
                <tr class="bg-white hover:bg-gray-50 dark:bg-gray-800 transition-colors duration-200 ease-in-out">
                    <td class="p-4 text-center">

                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

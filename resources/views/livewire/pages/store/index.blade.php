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
                    <th scope="col" class="px-6 py-4 rounded-tl-lg">
                        {{ __('Store Name') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold">
                        {{ __('Address') }}
                    </th>
                    <th scope="col" class="px-6 py-4 font-semibold">
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
                        <td class="p-4 text-center">
                            <flux:button size="sm" wire:click='edit({{ $store->id }})' variant="primary">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" wire:click='delete({{ $store->id }})' variant="danger">{{ __('Delete') }}</flux:button>
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

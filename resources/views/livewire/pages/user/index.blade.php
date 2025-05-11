<div>


    <flux:modal name="edit-profile" class="md:w-96">
        <div class="space-y-6">
            <flux:input label="First Name" placeholder="First Name" wire:model="name" />

            <flux:input label="Last Name" placeholder="Last Name" wire:model="last_name" />

            <flux:input label="Email" type="email" wire:model="email" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary" wire:click="createCustomer">{{ __('Save') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <div class="grid grid-cols-5 gap-4 mb-4">
        <flux:input wire:model.live.debounce.500ms="filters.first_name" placeholder="الاسم الاول" />
        <flux:input wire:model.live.debounce.500ms="filters.last_name" placeholder="الاسم الاخير" />
        <flux:input wire:model.live.debounce.500ms="filters.email" placeholder="البريد الالكتروني" />
    </div>
    <div>
        <flux:modal.trigger name="edit-profile">
            <flux:button variant="primary">{{ __('Add Client') }}</flux:button>
        </flux:modal.trigger>
    </div>
    <div class="relative overflow-x-auto sm:rounded-lg">
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                <tr>
                    <th scope="col" class="px-6 py-3">
                        Order Number
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Name
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Email
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Role
                    </th>
                    <th scope="col" class="px-6 py-3">
                        Actions
                    </th>
                </tr>
            </thead>
            <tbody>
                @foreach ($this->customers() as $customer)
                    <tr
                        class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                        <td scope="row"
                            class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                            {{ $customer['id'] }}
                        </td>
                        <td class="px-6 py-4">{{ $customer['first_name'] ?? '' }} {{ $customer['last_name'] ?? '' }}
                        </td>
                        <td class="px-6 py-4">{{ $customer['email'] ?? '—' }}</td>
                        <td class="px-6 py-4">
                            <select wire:change="updateCustomerRole({{ $customer['id'] }}, $event.target.value)"
                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                                @foreach ($this->getRoles() as $role)
                                    <option {{ $customer['roles'][0] == $role['role'] ? 'selected' : '' }}
                                        value="{{ $role['role'] }}">{{ $role['name'] }}</option>
                                @endforeach
                            </select>
                            {{-- <div class="text-xs text-gray-500 mb-1">
            الأدوار الحالة: {{ implode(', ', $customer['roles'] ?? []) }}
        </div>

        <select multiple wire:model="roles.{{ $customer['id'] }}" class="w-full">
            @foreach ($this->getRoles() as $role)
                <option value="{{ $role['role'] }}">{{ $role['name'] }}</option>
            @endforeach
        </select> --}}
                        </td>
                        <td>
                            <flux:dropdown>
                                {{-- <flux:button wire:click="updateCustomerRole({{ $customer['id'] }})" size="sm">حفظ</flux:button> --}}
                            </flux:dropdown>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

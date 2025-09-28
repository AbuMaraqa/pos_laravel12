<div>


    <flux:modal name="edit-profile" class="md:w-96">
        <div class="space-y-6">
            <flux:input label="First Name" placeholder="First Name" wire:model="name" />

            <flux:input label="Last Name" placeholder="Last Name" wire:model="last_name" />

            <flux:input label="Username" type="text" wire:model="username" />

            <flux:input label="Password" type="text" wire:model="password" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" wire:target="createCustomer" variant="primary" wire:click="createCustomer">
                    {{ __('Save') }}
                    <span wire:loading>Loading...</span>
                </flux:button>
            </div>
        </div>
    </flux:modal>

    <div>
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <flux:input wire:model.live.debounce.500ms="filters.name" placeholder="اسم الزبون"
                        icon="magnifying-glass" type="search" />
                </div>

                <div>
                    <flux:input wire:model.live.debounce.500ms="filters.username" placeholder="البريد الإلكتروني"
                        icon="envelope" type="text" />
                </div>

                <div>
                    <flux:select wire:model.live.debounce.300ms="filters.role">
                        <option value="">كل الأدوار</option>
                        @foreach ($this->getRoles() as $role)
                            <option value="{{ $role['role'] }}">{{ $role['name'] }}</option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:select wire:model.live.debounce.300ms="filters.status">
                        <option value="">كل الحالات</option>
                        <option value="active">نشط</option>
                        <option value="inactive">غير نشط</option>
                    </flux:select>
                </div>

                <div>
                    <flux:button wire:click="resetFilters" variant="primary" icon="arrow-path">
                        إعادة تعيين
                    </flux:button>
                </div>
            </div>

            @if (!empty(array_filter($filters)))
                <div class="mt-4 flex flex-wrap gap-2">
                    @if (!empty($filters['name']))
                        <flux:badge color="blue" wire:click="$set('filters.name', '')">
                            الاسم: {{ $filters['name'] }}
                            <flux:icon.x-mark class="w-4 h-4 ml-1" />
                        </flux:badge>
                    @endif

                    @if (!empty($filters['username']))
                        <flux:badge color="green" wire:click="$set('filters.username', '')">
                            البريد: {{ $filters['username'] }}
                            <flux:icon.x-mark class="w-4 h-4 ml-1" />
                        </flux:badge>
                    @endif

                    @if (!empty($filters['role']))
                        <flux:badge color="purple" wire:click="$set('filters.role', '')">
                            الدور: {{ collect($this->getRoles())->firstWhere('role', $filters['role'])['name'] }}
                            <flux:icon.x-mark class="w-4 h-4 ml-1" />
                        </flux:badge>
                    @endif

                    @if (!empty($filters['status']))
                        <flux:badge color="orange" wire:click="$set('filters.status', '')">
                            الحالة: {{ $filters['status'] === 'active' ? 'نشط' : 'غير نشط' }}
                            <flux:icon.x-mark class="w-4 h-4 ml-1" />
                        </flux:badge>
                    @endif
                </div>
            @endif
        </div>

        <div class="mb-4">
            <flux:modal.trigger name="edit-profile">
                <flux:button variant="primary" icon="plus">
                    {{ __('Add Client') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                    <tr>
                        <th scope="col" class="px-6 py-3">#</th>
                        <th scope="col" class="px-6 py-3">الاسم</th>
                        <th scope="col" class="px-6 py-3">البريد الإلكتروني</th>
                        <th scope="col" class="px-6 py-3">الدور</th>
                        <th scope="col" class="px-6 py-3">الحالة</th>
                        <th scope="col" class="px-6 py-3">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->customers() as $customer)
                        <tr
                            class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700">
                            <td class="px-6 py-4">{{ $customer['id'] }}</td>
                            <td class="px-6 py-4 font-medium text-gray-900">
                                <a wire:navigate href="{{ route('client.profile', $customer['id']) }}">{{ $customer['first_name'] ?? '' }} {{ $customer['last_name'] ?? '' }}</a>
                            </td>
                            <td class="px-6 py-4">{{ $customer['username'] ?? '—' }}</td>
                            <td class="px-6 py-4">
                                @if(!empty($customer['roles']))
                                    <div class="mb-1 text-xs text-gray-500">
                                        {{ implode(', ', $customer['roles']) }}
                                    </div>
                                @endif
                                <select wire:change="updateCustomerRole({{ $customer['id'] }}, $event.target.value)"
                                    class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                                    @foreach ($this->getRoles() as $role)
                                        <option
                                            @if($customer['role'] == $role['role']) selected @endif
                                            value="{{ $role['role'] }}">
                                            {{ $role['name'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="px-2 py-1 rounded-full text-xs font-medium {{ ($customer['status'] ?? '') === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ ($customer['status'] ?? '') === 'active' ? 'نشط' : 'غير نشط' }}
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <flux:dropdown>
                                    <flux:button icon:trailing="chevron-down" class="bg-indigo-500 hover:bg-indigo-600">
                                        {{ __('Options') }}
                                    </flux:button>

                                    <flux:menu>
                                        <flux:menu.item href="{{ route('client.edit', $customer['id']) }}"
                                            wire:navigate
                                            icon="pencil-square">
                                            {{ __('Edit') }}
                                        </flux:menu.item>
                                        <flux:menu.item wire:navigate href="{{ route('client.profile', $customer['id']) }}"
                                            icon="eye">
                                            {{ __('View') }}
                                        </flux:menu.item>
                                        <flux:menu.item variant="danger"
                                            wire:click="removeCustomer({{ $customer['id'] }})" icon="trash">
                                            {{ __('Delete') }}
                                        </flux:menu.item>
                                    </flux:menu>
                                </flux:dropdown>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

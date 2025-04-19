<div>
    <div class="relative overflow-x-auto sm:rounded-lg">
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">
                    Order Number
                </th>
                <th>
                    Name
                </th>
                <th>
                    Email
                </th>
                <th>
                    Role
                </th>
                <th>
                    Actions
                </th>
            </tr>
            </thead>
            <tbody>
                @foreach($this->customers as $customer)
<tr>
    <td>{{ $customer['id'] }}</td>
    <td>{{ $customer['name'] }}</td>
    <td>{{ $customer['email'] ?? '—' }}</td>
    <td>
        <div class="text-xs text-gray-500 mb-1">
            الأدوار الحالية: {{ implode(', ', $customer['roles'] ?? []) }}
        </div>

        <select multiple wire:model="roles.{{ $customer['id'] }}" class="w-full">
            @foreach($this->getRoles() as $role)
                <option value="{{ $role['role'] }}">{{ $role['name'] }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <flux:dropdown>
            <flux:button wire:click="updateCustomerRole({{ $customer['id'] }})" size="sm">حفظ</flux:button>
        </flux:dropdown>
    </td>
</tr>
@endforeach
            </tbody>
        </table>
    </div>
</div>

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
                    Actions
                </th>
            </tr>
            </thead>
            <tbody>
            @foreach($this->customers as $customer)
                <tr class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                    <td scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                        {{ $customer['id'] }}
                    </td>
                    <td>
                        {{ $customer['first_name'] }} {{ $customer['last_name'] }}
                    </td>
                    <td>
                        {{ $customer['email'] }}
                    </td>
                    <td>
                        <flux:dropdown>
                            <flux:button wire:navigate icon="eye" size="sm"></flux:button>
                        </flux:dropdown>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

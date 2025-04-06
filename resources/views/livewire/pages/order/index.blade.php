<div>
    <div class="relative overflow-x-auto sm:rounded-lg">
        <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">
                    Order Number
                </th>
                <th scope="col" class="px-6 py-3">
                    Client Name
                </th>
                <th scope="col" class="px-6 py-3">
                    Created Date
                </th>
                <th scope="col" class="px-6 py-3">
                    Status
                </th>
                <th>

                </th>
            </tr>
            </thead>
            <tbody>
            @foreach($orders as $order)
                <tr class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700 border-gray-200">
                    <td scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                        {{ $order['id'] }}
                    </td>
                    <td class="px-6 py-4">
                        {{ $order['billing']['first_name'] . ' ' . $order['billing']['last_name'] }}
                    </td>
                    <td class="px-6 py-4">
                        {{ $order['date_created'] }}
                    </td>
                    <td class="px-6 py-4">
                        @if($order['status'] == 'completed')
                            <flux:badge color="green">
                                {{ $order['status'] }}
                            </flux:badge>
                        @elseif($order['status'] == 'pending')
                            <flux:badge color="yellow">
                                {{ $order['status'] }}
                            </flux:badge>
                        @else
                            <flux:badge color="red">
                                {{ $order['status'] }}
                            </flux:badge>
                        @endif
                    </td>
                    <td>
                        <flux:dropdown>
                            <flux:button wire:navigate href="{{ route('order.details',['order' => $order['id']]) }}" icon="eye">{{ __('View order') }}</flux:button>
                        </flux:dropdown>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

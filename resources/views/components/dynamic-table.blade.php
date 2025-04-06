<div class="relative overflow-x-auto mt-3">
    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
        <!-- Header Row: Dynamically generated based on columns -->
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
        <tr>
            @foreach($columns as $column)
                <th scope="col" class="px-6 py-3">{{ $column['label'] }}</th>
            @endforeach
            @if(count($actions) > 0)
                <th scope="col" class="px-6 py-3">Actions</th>
            @endif
        </tr>
        </thead>
        <tbody>
        @foreach($data as $item)
            <tr>
                @foreach($columns as $column)
                    <td class="px-6 py-3">
                        @if(isset($column['key']) && isset($item[$column['key']]))
                            {{ $item[$column['key']] }}
                        @else
                            N/A
                        @endif
                    </td>
                @endforeach

                @if(count($actions) > 0)
                    <td class="px-6 py-3">
                        @foreach($actions as $action)
                            <a href="{{ $action['url']($item) }}" class="text-blue-600 hover:text-blue-900 mr-2">
                                <i class="{{ $action['icon'] }}"></i> {{ $action['label'] }}
                            </a>
                        @endforeach
                    </td>
                @endif
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

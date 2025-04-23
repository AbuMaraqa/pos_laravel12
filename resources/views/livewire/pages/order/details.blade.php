<div>
    <flux:modal name="add-product-to-order" class="md:w-300">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add Product To Order') }}</flux:heading>
            </div>

            <flux:input wire:model.live.debounce.500ms="search" label="{{ __('Product') }}"
                placeholder="{{ __('Search Product') }}" />

            <div class="flex">
                <flux:spacer />
            </div>

            <div>
                @foreach ($products as $product)
                    <div class="flex justify-between items-center w-full">
                        <div class="flex justify-start items-center space-x-4">
                            <img class="w-10 h-10" src="{{ $product['images'][0]['src'] }}" alt="dress" />
                            <p class="text-base dark:text-white font-semibold leading-4 text-gray-800">
                                {{ $product['name'] }}</p>
                        </div>
                        <flux:button wire:click="addProductToOrder({{ $product['id'] }})" variant="primary"
                            size="xs" icon="plus">{{ __('Add') }}</flux:button>
                    </div>
                @endforeach
            </div>
        </div>
    </flux:modal>

    <div class="py-14 px-4 md:px-6 2xl:px-20 2xl:container 2xl:mx-auto">
        <div class="flex justify-start item-start space-y-2 flex-col">
            <h1 class="text-3xl dark:text-white lg:text-4xl font-semibold leading-7 lg:leading-9 text-gray-800">
                {{ __('Order') }}
                #{{ $order['id'] }}</h1>
            <p class="text-base dark:text-gray-300 font-medium leading-6 text-gray-600">{{ $order['date_created'] }}</p>
        </div>
        <div class="mt-3">
            <flux:modal.trigger name="add-product-to-order">
                <flux:button variant="primary" icon="plus">{{ __('Add Product To Order') }}</flux:button>
            </flux:modal.trigger>

        </div>
        <div
            class="mt-5 flex flex-col xl:flex-row jusitfy-center items-stretch w-full xl:space-x-8 space-y-4 md:space-y-6 xl:space-y-0">
            <div class="flex flex-col justify-start items-start w-full space-y-4 md:space-y-6 xl:space-y-8">
                <div
                    class="flex flex-col justify-start items-start dark:bg-gray-800 bg-gray-50 px-4 py-4 md:py-6 md:p-6 xl:p-8 w-full">
                    <p class="text-lg md:text-xl dark:text-white font-semibold leading-6 xl:leading-5 text-gray-800">
                        {{ __('Customer’s Cart') }}</p>
                    @foreach ($order['line_items'] as $item)
                        <div
                            class="mt-4 md:mt-6 flex flex-col md:flex-row justify-start items-start md:items-center md:space-x-6 xl:space-x-8 w-full">
                            <div class="pb-4 md:pb-8 w-full md:w-40">
                                <img class="w-full hidden md:block" src="{{ $item['image']['src'] }}" alt="dress" />
                                <img class="w-full md:hidden" src="{{ $item['image']['src'] }}" alt="dress" />
                            </div>

                            <div
                                class="border-b border-gray-200 md:flex-row flex-col flex justify-between items-start w-full pb-8 space-y-4 md:space-y-0">
                                <div class="w-full flex flex-col justify-start items-start space-y-8">
                                    <h3
                                        class="text-xl dark:text-white xl:text-2xl font-semibold leading-6 text-gray-800">
                                        {{ $item['name'] }}</h3>
                                    <div class="flex justify-start items-start flex-col space-y-2">
                                        <p class="text-sm dark:text-white leading-none text-gray-800"><span
                                                class="dark:text-gray-400 text-gray-300">Style: </span> Italic Minimal
                                            Design</p>
                                        <p class="text-sm dark:text-white leading-none text-gray-800"><span
                                                class="dark:text-gray-400 text-gray-300">Size: </span> Small</p>
                                        <p class="text-sm dark:text-white leading-none text-gray-800"><span
                                                class="dark:text-gray-400 text-gray-300">Color: </span> Light Blue</p>
                                    </div>
                                </div>
                                <div class="flex justify-between space-x-8 items-start w-full">
                                    <p class="text-base dark:text-white xl:text-lg leading-6">
                                        {{ $order['currency_symbol'] }}{{ number_format($item['price'], 2) }}
                                        {{--                                        <span class="text-gray-300 dark:text-gray-400"> $45.00</span> --}}
                                        {{--                                        <span class="text-red-300 line-through"> $45.00</span> --}}
                                    </p>
                                    <p class="text-base dark:text-white xl:text-lg leading-6 text-gray-800">
                                        <flux:input wire:model="quantities.{{ $item['product_id'] }}"
                                            style="width:120px; text-align:center" wire:target="changeQty"
                                            wire:change="changeQty({{ $item['product_id'] }})" class="text-center"
                                            value="{{ $item['quantity'] }}" placeholder="{{ __('Quantity') }}" />
                                    </p>
                                    <p
                                        class="text-base dark:text-white xl:text-lg font-semibold leading-6 text-gray-800">
                                        {{ $order['currency_symbol'] }}{{ $item['subtotal'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach

                </div>
                <div
                    class="flex justify-center flex-col md:flex-row flex-col items-stretch w-full space-y-4 md:space-y-0 md:space-x-6 xl:space-x-8">
                    <div class="flex flex-col px-4 py-6 md:p-6 xl:p-8 w-full bg-gray-50 dark:bg-gray-800 space-y-6">
                        <h3 class="text-xl dark:text-white font-semibold leading-5 text-gray-800">{{ __('Summary') }}
                        </h3>
                        <div
                            class="flex justify-center items-center w-full space-y-4 flex-col border-gray-200 border-b pb-4">
                            <div class="flex justify-between w-full">
                                <p class="text-base dark:text-white leading-4 text-gray-800">{{ __('Subtotal') }}</p>
                                <p class="text-base dark:text-gray-300 leading-4 text-gray-600">{{ number_format($totalAmount, 2) }}</p>
                            </div>
                            <div class="flex justify-between items-center w-full">
                                <p class="text-base dark:text-white leading-4 text-gray-800">{{ __('Discount') }}</p>
                                <p class="text-base dark:text-gray-300 leading-4 text-gray-600">
                                    {{ number_format($order['discount_total'], 2) }}</p>
                            </div>
                            <div class="flex justify-between items-center w-full">
                                <p class="text-base dark:text-white leading-4 text-gray-800">{{ __('Shipping') }}</p>
                                <p class="text-base dark:text-gray-300 leading-4 text-gray-600">
                                    {{ number_format($order['shipping_total'], 2) }}</p>
                            </div>
                        </div>
                        <div class="flex justify-between items-center w-full">
                            <p class="text-base dark:text-white font-semibold leading-4 text-gray-800">
                                {{ __('Total') }}</p>
                            <p class="text-base dark:text-gray-300 font-semibold leading-4 text-gray-600">
                                {{ number_format($totalAmountAfterDiscount, 2) }}</p>
                        </div>
                    </div>
                    <div
                        class="flex flex-col justify-center px-4 py-6 md:p-6 xl:p-8 w-full bg-gray-50 dark:bg-gray-800 space-y-6">
                        <h3 class="text-xl dark:text-white font-semibold leading-5 text-gray-800">{{ __('Shipping') }}
                        </h3>
                        <div class="flex justify-between items-start w-full">
                            <div class="flex justify-center items-center space-x-4">
                                <div class="w-8 h-8">
                                    <img class="w-full h-full" alt="logo"
                                        src="https://i.ibb.co/L8KSdNQ/image-3.png" />
                                </div>
                                {{-- <div class="flex flex-col justify-start items-center">
                                    <p class="text-lg leading-6 dark:text-white font-semibold text-gray-800">DPD Delivery<br /><span class="font-normal">Delivery with 24 Hours</span></p>
                                </div> --}}
                                <div>

                                    @foreach ($this->shippingZones as $zone)
                                        @foreach ($this->shippingZoneMethods($zone['id']) as $method)
                                            <label wire:click="updateOrder({{ $method['id'] }}, {{ $zone['id'] }})"
                                                class="block cursor-pointer">
                                                {{ $method['title'] }} - {{ $method['settings']['cost']['value'] }}
                                                شيكل
                                            </label>
                                        @endforeach
                                    @endforeach
                                </div>

                            </div>
                            {{-- <p class="text-lg font-semibold leading-6 dark:text-white text-gray-800">$8.00</p> --}}
                        </div>
                        {{-- <div class="w-full flex justify-center items-center">
                            <button
                                class="hover:bg-black dark:bg-white dark:text-gray-800 dark:hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-800 py-5 w-96 md:w-full bg-gray-800 text-base font-medium leading-4 text-white">View
                                Carrier Details</button>
                        </div> --}}
                    </div>
                </div>
            </div>
            <div
                class="bg-gray-50 dark:bg-gray-800 w-full xl:w-96 flex justify-between items-center md:items-start px-4 py-6 md:p-6 xl:p-8 flex-col">
                <div class="w-full mb-3">
                    <select style="background-color: #FACA15
;"
                        wire:change="updateOrderStatus({{ $order['id'] }}, $event.target.value)"
                        class="appearance-none dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-white text-sm rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 block w-full px-4 py-2 pr-10">
                        <option value="completed" {{ $order['status'] == 'completed' ? 'selected' : '' }}>
                            {{ __('Completed') }}</option>
                        <option value="processing" {{ $order['status'] == 'processing' ? 'selected' : '' }}>
                            {{ __('Processing') }}</option>
                        <option value="pending" {{ $order['status'] == 'pending' ? 'selected' : '' }}>
                            {{ __('Pending') }}</option>
                        <option value="failed" {{ $order['status'] == 'failed' ? 'selected' : '' }}>
                            {{ __('Failed') }}</option>
                    </select>
                </div>
                <h3 class="text-xl dark:text-white font-semibold leading-5 text-gray-800">{{ __('Customer') }}</h3>
                <div
                    class="flex flex-col md:flex-row xl:flex-col justify-start items-stretch h-full w-full md:space-x-6 lg:space-x-8 xl:space-x-0">
                    <div class="flex flex-col justify-start items-start flex-shrink-0">
                        <div
                            class="flex justify-center w-full md:justify-start items-center space-x-4 py-8 border-b border-gray-200">
                            <flux:avatar icon="user" />

                            <div class="flex justify-start items-start flex-col space-y-2">
                                <p class="text-base dark:text-white font-semibold leading-4 text-left text-gray-800">
                                    {{ $order['billing']['first_name'] . ' ' . $order['billing']['last_name'] }}</p>
                                {{--                                <p class="text-sm dark:text-gray-300 leading-5 text-gray-600">10 Previous Orders</p> --}}
                            </div>
                        </div>

                        <div
                            class="flex justify-center text-gray-800 dark:text-white md:justify-start items-center space-x-4 py-4 border-b border-gray-200 w-full">
                            <img class="dark:hidden"
                                src="https://tuk-cdn.s3.amazonaws.com/can-uploader/order-summary-3-svg1.svg"
                                alt="email">
                            <img class="hidden dark:block"
                                src="https://tuk-cdn.s3.amazonaws.com/can-uploader/order-summary-3-svg1dark.svg"
                                alt="email">
                            <p class="cursor-pointer text-sm leading-5 ">{{ $order['billing']['email'] }}</p>
                        </div>
                    </div>
                    <div class="flex justify-between xl:h-full items-stretch w-full flex-col mt-6 md:mt-0">
                        @if (!empty($order['shipping']) || !empty($order['billing']))
                            <div
                                class="flex justify-center md:justify-start xl:flex-col flex-col md:space-x-6 lg:space-x-8 xl:space-x-0 space-y-4 xl:space-y-12 md:space-y-0 md:flex-row items-center md:items-start">

                                @if (!empty($order['shipping']))
                                    <div
                                        class="flex justify-center md:justify-start items-center md:items-start flex-col space-y-4 xl:mt-8">
                                        <p
                                            class="text-base dark:text-white font-semibold leading-4 text-center md:text-left text-gray-800">
                                            {{ __('Shipping Address') }}</p>
                                        @if (!empty($order['shipping']['first_name']) || !empty($order['shipping']['last_name']))
                                            <p class="...">
                                                {{ $order['shipping']['first_name'] . ' ' . $order['shipping']['last_name'] }}
                                            </p>
                                        @endif
                                        @if (!empty($order['shipping']['company']))
                                            <p class="...">{{ $order['shipping']['company'] }}</p>
                                        @endif
                                        @if (!empty($order['shipping']['address_1']))
                                            <p class="...">{{ $order['shipping']['address_1'] }}</p>
                                        @endif
                                        @if (!empty($order['shipping']['address_2']))
                                            <p class="...">{{ $order['shipping']['address_2'] }}</p>
                                        @endif
                                        @if (!empty($order['shipping']['city']))
                                            <p class="...">{{ $order['shipping']['city'] }}</p>
                                        @endif
                                        @if (!empty($order['shipping']['state']))
                                            <p class="...">{{ $order['shipping']['state'] }}</p>
                                        @endif
                                        @if (!empty($order['shipping']['postcode']))
                                            <p class="...">{{ $order['shipping']['postcode'] }}</p>
                                        @endif
                                        @if (!empty($order['shipping']['country']))
                                            <p class="...">{{ $order['shipping']['country'] }}</p>
                                        @endif
                                        @if (!empty($order['shipping']['phone']))
                                            <p class="...">{{ $order['shipping']['phone'] }}</p>
                                        @endif
                                    </div>
                                @endif

                                @if (!empty($order['billing']))
                                    <div
                                        class="flex justify-center md:justify-start items-center md:items-start flex-col space-y-4">
                                        <p
                                            class="text-base dark:text-white font-semibold leading-4 text-center md:text-left text-gray-800">
                                            {{ __('Billing Address') }}</p>
                                        @if (!empty($order['billing']['first_name']) || !empty($order['billing']['last_name']))
                                            <p class="...">
                                                {{ $order['billing']['first_name'] . ' ' . $order['billing']['last_name'] }}
                                            </p>
                                        @endif
                                        @if (!empty($order['billing']['company']))
                                            <p class="...">{{ $order['billing']['company'] }}</p>
                                        @endif
                                        @if (!empty($order['billing']['address_1']))
                                            <p class="...">{{ $order['billing']['address_1'] }}</p>
                                        @endif
                                        @if (!empty($order['billing']['address_2']))
                                            <p class="...">{{ $order['billing']['address_2'] }}</p>
                                        @endif
                                        @if (!empty($order['billing']['city']))
                                            <p class="...">{{ $order['billing']['city'] }}</p>
                                        @endif
                                        @if (!empty($order['billing']['state']))
                                            <p class="...">{{ $order['billing']['state'] }}</p>
                                        @endif
                                        @if (!empty($order['billing']['postcode']))
                                            <p class="...">{{ $order['billing']['postcode'] }}</p>
                                        @endif
                                        @if (!empty($order['billing']['country']))
                                            <p class="...">{{ $order['billing']['country'] }}</p>
                                        @endif
                                    </div>
                                @endif

                            </div>
                        @endif

                        {{-- <div class="flex w-full justify-center items-center md:justify-start md:items-start">
                            <button
                                class="mt-6 md:mt-0 dark:border-white dark:hover:bg-gray-900 dark:bg-transparent dark:text-white py-5 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-800 border border-gray-800 font-medium w-96 2xl:w-full text-base font-medium leading-4 text-gray-800">{{ __('Edit Details') }}</button>
                        </div> --}}
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

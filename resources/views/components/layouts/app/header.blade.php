<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
    <head>
        @include('partials.head')
    </head>
    <body @if(app()->getLocale() == 'ar') dir="rtl"  @else dir="ltr" @endif class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <a href="{{ route('dashboard') }}" class="ms-2 me-5 flex items-center space-x-2 rtl:space-x-reverse lg:ms-0" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navbar class="-mb-px max-lg:hidden">
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Home') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('product.index')" :current="request()->routeIs('product.index')" wire:navigate>
                    {{ __('Products') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('product.index')" :current="request()->routeIs('product.index')" wire:navigate>
                    {{ __('Categories') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('product.attributes.add')" :current="request()->routeIs('product.attributes.add')" wire:navigate>
                    {{ __('Attributes') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('order.index')" :current="request()->routeIs('order.index')" wire:navigate>
                    {{ __('Orders') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Inventories') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Users') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Reports') }}
                </flux:navbar.item>
                <flux:navbar.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Settings') }}
                </flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

{{--            <flux:navbar class="me-1.5 space-x-0.5 rtl:space-x-reverse py-0!">--}}
{{--                <flux:tooltip :content="__('Search')" position="bottom">--}}
{{--                    <flux:navbar.item class="!h-10 [&>div>svg]:size-5" icon="magnifying-glass" href="#" :label="__('Search')" />--}}
{{--                </flux:tooltip>--}}
{{--                <flux:tooltip :content="__('Repository')" position="bottom">--}}
{{--                    <flux:navbar.item--}}
{{--                        class="h-10 max-lg:hidden [&>div>svg]:size-5"--}}
{{--                        icon="folder-git-2"--}}
{{--                        href="https://github.com/laravel/livewire-starter-kit"--}}
{{--                        target="_blank"--}}
{{--                        :label="__('Repository')"--}}
{{--                    />--}}
{{--                </flux:tooltip>--}}
{{--                <flux:tooltip :content="__('Documentation')" position="bottom">--}}
{{--                    <flux:navbar.item--}}
{{--                        class="h-10 max-lg:hidden [&>div>svg]:size-5"--}}
{{--                        icon="book-open-text"--}}
{{--                        href="https://laravel.com/docs/starter-kits"--}}
{{--                        target="_blank"--}}
{{--                        label="Documentation"--}}
{{--                    />--}}
{{--                </flux:tooltip>--}}
{{--            </flux:navbar>--}}

            <flux:dropdown>
                <flux:button icon:trailing="chevron-down">Lang</flux:button>

                <flux:menu>
                        @foreach(LaravelLocalization::getSupportedLocales() as $localeCode => $properties)
                            <flux:menu.item wire:navigate href="{{ LaravelLocalization::getLocalizedURL($localeCode, null, [], true) }}">{{ $properties['native'] }}</flux:menu.item>

                        @endforeach
                </flux:menu>
            </flux:dropdown>

            <!-- Desktop User Menu -->
            <flux:dropdown position="top" align="end">
                <flux:profile
                    class="cursor-pointer"
                    :initials="auth()->user()->initials()"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->username }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->phone }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item variant="danger" as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        <!-- Mobile Menu -->
        <flux:sidebar stashable sticky class="lg:hidden border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="ms-1 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="__('Platform')">
                    <flux:navlist.item icon="layout-grid" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                    </flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="folder-git-2" href="https://github.com/laravel/livewire-starter-kit" target="_blank">
                {{ __('Repository') }}
                </flux:navlist.item>

                <flux:navlist.item icon="book-open-text" href="https://laravel.com/docs/starter-kits" target="_blank">
                {{ __('Documentation') }}
                </flux:navlist.item>
            </flux:navlist>
        </flux:sidebar>

        {{ $slot }}

        @fluxScripts

        @filepondScripts


        <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
    </body>
</html>

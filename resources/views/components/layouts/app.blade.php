<x-layouts.app.header :title="$title ?? null">
    <flux:main>
        {{ $slot }}

        <x-toaster-hub />

    </flux:main>
</x-layouts.app.header>

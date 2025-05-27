@php
    $settings = app(\App\Settings\GeneralSettings::class);
@endphp

<div class="flex items-center group">
    <div class="flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 shadow-sm transition-all duration-200 group-hover:shadow-md group-hover:scale-105">
        <img
            src="{{ $settings->getLogoUrl() }}"
            alt="Logo"
            class="size-5 object-contain drop-shadow-sm transition-transform duration-200 group-hover:scale-110"
        />
    </div>
    <div class="ms-1 grid flex-1 text-start text-sm">
        <span class="mb-0.5 truncate leading-none font-semibold transition-colors duration-200 group-hover:text-blue-600">
            {{ $settings->site_name }}
        </span>
    </div>
</div>
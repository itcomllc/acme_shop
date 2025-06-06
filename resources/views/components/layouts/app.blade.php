@props(['title'])

<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="bg-gray-50 dark:bg-gray-900 transition-colors duration-200">
        {{ $slot }}
    </flux:main>
</x-layouts.app.sidebar>
@props([
    'label',
    'icon' => 'edit',
    'variant' => 'slate',
    'tag' => 'button',
    'href' => null,
    'type' => 'button',
    'size' => 'h-4 w-4',
])

@php
    $tag = in_array($tag, ['a', 'button', 'summary'], true) ? $tag : 'button';
    $variantClasses = [
        'green' => 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600',
        'purple' => 'bg-purple-600 text-white hover:bg-purple-700 focus:ring-purple-500 dark:bg-purple-500 dark:hover:bg-purple-600',
        'blue' => 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 dark:bg-blue-500 dark:hover:bg-blue-600',
        'amber' => 'bg-amber-600 text-white hover:bg-amber-700 focus:ring-amber-500 dark:bg-amber-500 dark:hover:bg-amber-600',
        'red' => 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600',
        'emerald' => 'bg-emerald-600 text-white hover:bg-emerald-700 focus:ring-emerald-500 dark:bg-emerald-500 dark:hover:bg-emerald-600',
        'slate' => 'bg-gray-900 text-white hover:bg-black focus:ring-gray-500 dark:bg-gray-600 dark:hover:bg-gray-500',
    ][$variant] ?? 'bg-gray-900 text-white hover:bg-black focus:ring-gray-500 dark:bg-gray-600 dark:hover:bg-gray-500';

    $iconPaths = [
        'eye' => 'M2.458 12C3.732 7.943 7.523 5 12 5s8.268 2.943 9.542 7c-1.274 4.057-5.065 7-9.542 7s-8.268-2.943-9.542-7Z M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z',
        'history' => 'M12 8v4l3 2M3.05 11a9 9 0 1 1 .5 4M3 4v5h5',
        'check' => 'm5 12 4 4L19 6',
        'edit' => 'M16.862 3.487 20.513 7.138M4 20h4l11.5-11.5a2.121 2.121 0 0 0-3-3L5 17v3Z',
        'trash' => 'M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3',
        'monitor' => 'M3 5h18v12H3zM8 21h8M12 17v4',
        'issue' => 'M5 12h14m-6-6 6 6-6 6',
        'link' => 'M10 13a5 5 0 0 0 7.071 0l2-2a5 5 0 0 0-7.071-7.071l-1.15 1.15M14 11a5 5 0 0 0-7.071 0l-2 2A5 5 0 0 0 12 20.071l1.15-1.15',
        'recycle' => 'M4 7h16M7 7v12h10V7M9 4h6l1 3H8l1-3Zm1 7h4',
        'calendar' => 'M7 3v4M17 3v4M4 9h16M5 5h14v16H5z',
        'clipboard' => 'M9 5h6v3H9zM7 6H5v15h14V6h-2M9 13l2 2 4-4',
    ];
    $iconPath = $iconPaths[$icon] ?? $iconPaths['edit'];
@endphp

<span class="group relative inline-flex">
    <{{ $tag }}
        @if($tag === 'a') href="{{ $href }}" @elseif($tag === 'button') type="{{ $type }}" @endif
        title="{{ $label }}"
        aria-label="{{ $label }}"
        {{ $attributes->merge(['class' => 'action-icon-button inline-flex h-9 w-9 min-h-0 min-w-0 shrink-0 items-center justify-center rounded-lg p-0 shadow-sm transition hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-gray-900 ' . $variantClasses . ($tag === 'summary' ? ' list-none [&::-webkit-details-marker]:hidden' : '')]) }}
    >
        <svg class="{{ $size }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="{{ $iconPath }}"></path>
        </svg>
        <span class="sr-only">{{ $label }}</span>
    </{{ $tag }}>
    <span class="pointer-events-none absolute bottom-full left-1/2 z-[70] mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100 dark:bg-gray-100 dark:text-gray-900" role="tooltip">
        {{ $label }}
    </span>
</span>

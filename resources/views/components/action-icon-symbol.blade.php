@props([
    'icon' => 'edit',
    'size' => 'h-4 w-4',
])

@php
    $paths = [
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
        'qr' => 'M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2 M7 8h1M11 8h1M16 8h1M7 12h1M11 12h5M7 16h5M16 16h1',
    ];
@endphp

<svg class="{{ $size }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="{{ $paths[$icon] ?? $paths['edit'] }}"></path>
</svg>

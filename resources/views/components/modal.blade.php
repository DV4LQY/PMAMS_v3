@props([
    'show' => 'open',
    'title' => 'Modal Title',
    'maxWidth' => 'max-w-lg',
])

<div
    {{ $attributes->merge(['class' => 'fixed inset-0 z-50 overflow-y-auto']) }}
    x-show="{{ $show }}"
    x-cloak
    x-transition.opacity
    aria-modal="true"
    role="dialog"
>
    <div
        class="fixed inset-0 bg-black/40"
        data-native-modal-close="{{ $attributes->get('id') }}"
        @click="$dispatch('pmams-modal-close', { id: '{{ $attributes->get('id') }}' }); {{ $show }} = false"
    ></div>

    <div class="flex min-h-full items-start justify-center p-3 sm:items-center sm:p-6">
        <div
            x-transition
            class="relative mt-6 w-full {{ $maxWidth }} rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 sm:mt-0 max-h-[92vh] flex flex-col"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 sm:px-5">
                <h2 class="text-lg font-semibold text-gray-900">{{ $title }}</h2>

                <button
                    type="button"
                    class="rounded-lg px-2 py-1 text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                    data-native-modal-close="{{ $attributes->get('id') }}"
                    @click="$dispatch('pmams-modal-close', { id: '{{ $attributes->get('id') }}' }); {{ $show }} = false"
                >
                    ✕
                </button>
            </div>

            <div class="overflow-y-auto p-4 sm:p-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>

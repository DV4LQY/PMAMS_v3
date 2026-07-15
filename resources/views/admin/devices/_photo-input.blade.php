@php
    $photoInputId = $photoInputId ?? 'equipment_photo';
    $existingPhotoPath = $existingPhotoPath ?? null;
@endphp

<div class="{{ $photoInputWrapperClass ?? 'md:col-span-2' }}">
    <label class="text-sm font-medium dark:text-gray-300">Equipment Photo</label>
    <input
        id="{{ $photoInputId }}"
        type="file"
        name="equipment_photo"
        accept="image/*,.heic,.heif"
        capture="environment"
        class="sr-only"
    >
    <button
        type="button"
        onclick="document.getElementById('{{ $photoInputId }}').click()"
        class="mt-2 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-offset-gray-800"
    >
        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5A2.5 2.5 0 0 1 5.5 6H7l1.2-1.8A2 2 0 0 1 9.9 3.3h4.2a2 2 0 0 1 1.7.9L17 6h1.5A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-8Z" />
            <circle cx="12" cy="12.5" r="3.5" />
        </svg>
        Take Photo
    </button>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        Optional. Uses the rear camera on mobile devices. Maximum 10 MB (JPG, PNG, WEBP, HEIC, HEIF).
    </p>
    @if($existingPhotoPath)
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Uploading a new photo will replace the current equipment photo.
        </p>
    @endif
    @error('equipment_photo')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>

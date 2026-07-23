@extends('admin.layouts.app')

@section('title', 'Maintenance Photo Gallery')
@section('page_title', 'Maintenance Photo Gallery')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600">Dashboard</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Maintenance Photo Gallery</span>
@endsection

@section('content')
    <div
        x-data="{
            slideshowOpen: false,
            activeSlide: 0,
            slides: @js($slides->values()->all()),
            viewMode: 'grid',
            selectedPhotoIds: [],
            pagePhotoIds: @js($photos->pluck('id')->values()->all()),
            timer: null,
            allPageSelected() {
                return this.pagePhotoIds.length > 0 && this.pagePhotoIds.every((id) => this.selectedPhotoIds.includes(id));
            },
            togglePageSelection(checked) {
                this.selectedPhotoIds = checked ? [...this.pagePhotoIds] : [];
            },
            startSlideshow(index = 0) {
                if (!this.slides.length) return;
                this.activeSlide = index;
                this.slideshowOpen = true;
                clearInterval(this.timer);
                this.timer = setInterval(() => {
                    this.activeSlide = (this.activeSlide + 1) % this.slides.length;
                }, 3500);
            },
            stopSlideshow() {
                this.slideshowOpen = false;
                clearInterval(this.timer);
                this.timer = null;
            },
            previousSlide() {
                this.activeSlide = (this.activeSlide - 1 + this.slides.length) % this.slides.length;
            },
            nextSlide() {
                this.activeSlide = (this.activeSlide + 1) % this.slides.length;
            }
        }"
        x-on:keydown.escape.window="stopSlideshow()"
        class="space-y-6"
    >
        @if($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/30 dark:text-red-300">
                <div class="font-semibold">Please check the photo details.</div>
                <ul class="mt-1 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Preventive maintenance photos</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review equipment photos by capture date, property number, or equipment type.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" x-on:click="viewMode = 'grid'" x-bind:class="viewMode === 'grid' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'" class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold hover:bg-blue-500 hover:text-white">Grid</button>
                        <button type="button" x-on:click="viewMode = 'list'" x-bind:class="viewMode === 'list' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200'" class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold hover:bg-blue-500 hover:text-white">List</button>
                        <button type="button" x-on:click="startSlideshow(0)" x-bind:disabled="!slides.length" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.752 11.168 10.5 8.5v5.336l4.252-2.668Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                            Slideshow
                        </button>
                    </div>
                </div>

                <form method="GET" action="{{ route('admin.maintenance-gallery.index') }}" class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_10rem_auto]">
                    <label class="sr-only" for="gallery-search">Search gallery</label>
                    <input id="gallery-search" name="q" value="{{ $search }}" type="search" placeholder="Search property, serial, type, or caption" class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400">
                    <label class="sr-only" for="date-from">Date from</label>
                    <input id="date-from" name="date_from" value="{{ $dateFrom }}" type="date" class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <label class="sr-only" for="date-to">Date to</label>
                    <input id="date-to" name="date_to" value="{{ $dateTo }}" type="date" class="min-h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    <button type="submit" class="min-h-11 rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 dark:bg-gray-600 dark:hover:bg-gray-500">Filter</button>
                </form>

                @if($photos->count() > 0)
                    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
                        <label class="inline-flex min-h-10 cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                            <input type="checkbox" x-bind:checked="allPageSelected()" x-on:change="togglePageSelection($event.target.checked)" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700">
                            Select all on this page
                        </label>
                        <span class="text-xs text-gray-500 dark:text-gray-400" x-text="selectedPhotoIds.length + ' selected'">0 selected</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Only photos uploaded by your account can be deleted.</span>
                        <div class="flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.maintenance-gallery.bulkDownload') }}">
                                @csrf
                                <template x-for="id in selectedPhotoIds" x-bind:key="'download-' + id">
                                    <input type="hidden" name="photo_ids[]" x-bind:value="id">
                                </template>
                                <button type="submit" x-bind:disabled="selectedPhotoIds.length === 0" class="min-h-10 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Download ZIP</button>
                            </form>
                            <form method="POST" action="{{ route('admin.maintenance-gallery.bulkDestroy') }}" onsubmit="return window.confirm('Delete all selected maintenance photos?')">
                                @csrf
                                <template x-for="id in selectedPhotoIds" x-bind:key="'delete-' + id">
                                    <input type="hidden" name="photo_ids[]" x-bind:value="id">
                                </template>
                                <button type="submit" x-bind:disabled="selectedPhotoIds.length === 0" class="min-h-10 rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50">Delete selected</button>
                            </form>
                        </div>
                    </div>

                    <div x-bind:class="viewMode === 'list' ? 'mt-6 space-y-3' : 'mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3'">
                        @foreach($photos as $index => $photo)
                            <article x-bind:class="viewMode === 'list' ? 'flex flex-col overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900 sm:flex-row' : 'relative overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-900'">
                                <input type="checkbox" value="{{ $photo->id }}" x-model.number="selectedPhotoIds" aria-label="Select photo {{ $photo->id }}" class="absolute left-3 top-3 z-10 h-5 w-5 rounded border-gray-300 bg-white/90 text-blue-600 shadow focus:ring-blue-500 dark:border-gray-600">
                                <button type="button" x-bind:class="viewMode === 'list' ? 'group flex min-w-0 flex-1 text-left sm:flex-row' : 'group block w-full text-left'" x-on:click="startSlideshow({{ $index }})">
                                    <div x-bind:class="viewMode === 'list' ? 'h-48 w-full shrink-0 overflow-hidden bg-gray-200 dark:bg-gray-700 sm:h-auto sm:w-56' : 'aspect-[4/3] overflow-hidden bg-gray-200 dark:bg-gray-700'">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->photo_path) }}" alt="{{ $photo->caption ?: 'Preventive maintenance photo' }}" loading="lazy" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                    </div>
                                    <div class="space-y-1 p-3">
                                        <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $photo->device?->property_number ?: 'Equipment' }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $photo->device?->type?->name ?: 'Equipment photo' }} · {{ $photo->captured_at?->format('M j, Y g:i A') }}</div>
                                        @if($photo->caption)
                                            <div class="truncate text-xs text-gray-600 dark:text-gray-300">{{ $photo->caption }}</div>
                                        @endif
                                    </div>
                                </button>
                                <div class="flex items-center justify-between border-t border-gray-200 px-3 py-2 dark:border-gray-700 sm:min-w-56">
                                    <div class="flex min-w-0 items-center gap-3">
                                        @if($photo->maintenanceRecord)
                                            <span class="truncate text-[11px] text-gray-500 dark:text-gray-400">Checklist {{ $photo->maintenanceRecord->maintenance_date?->format('M j, Y') }}</span>
                                        @else
                                            <span class="truncate text-[11px] text-gray-500 dark:text-gray-400">No checklist linked</span>
                                        @endif
                                        <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($photo->photo_path) }}" download="maintenance-photo-{{ $photo->id }}" class="shrink-0 text-xs font-semibold text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">Download</a>
                                    </div>
                                    @if((int) $photo->uploaded_by === (int) auth()->id())
                                        <form method="POST" action="{{ route('admin.maintenance-gallery.destroy', $photo) }}" onsubmit="return window.confirm('Delete this maintenance photo?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                        </form>
                                    @else
                                        <span class="text-[11px] text-gray-400 dark:text-gray-500">View only</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>

                    <div class="mt-6">{{ $photos->links() }}</div>
                @else
                    <div class="mt-6 rounded-xl border border-dashed border-gray-300 px-5 py-12 text-center dark:border-gray-600">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 16 5-5 4 4 3-3 6 6M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>
                        </svg>
                        <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">No maintenance photos found.</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Upload or take the first photo using the form below this gallery.</p>
                    </div>
                @endif
            </section>

            <section class="h-fit rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Add maintenance photo</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Equipment is optional for general maintenance photos. On mobile, use Take Photo to open the rear camera. Photos are limited to 10 MB.</p>

                <form method="POST" action="{{ route('admin.maintenance-gallery.store') }}" enctype="multipart/form-data" class="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    @csrf
                    <div class="flex min-h-64 items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 p-3 dark:border-gray-600 dark:bg-gray-900/50">
                        <div id="gallery-photo-frame" class="relative flex aspect-[4/3] w-full items-center justify-center overflow-hidden rounded-lg bg-gray-200 dark:bg-gray-700">
                            <div id="gallery-photo-placeholder" class="px-5 text-center text-sm text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m3 16 5-5 4 4 3-3 6 6M5 20h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z"/>
                                </svg>
                                <div class="mt-2">Photo preview appears here</div>
                            </div>
                            <img id="gallery-photo-preview" alt="Selected photo preview" class="hidden h-full w-full object-contain">
                            <video id="gallery-camera-preview" class="hidden h-full w-full object-cover" autoplay playsinline muted></video>
                            <canvas id="gallery-camera-canvas" class="hidden"></canvas>
                            <div id="gallery-camera-controls" class="absolute bottom-3 left-1/2 hidden -translate-x-1/2 items-center gap-2 rounded-xl bg-black/70 p-2">
                                <button id="gallery-camera-capture" type="button" class="min-h-10 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Capture</button>
                                <button id="gallery-camera-cancel" type="button" class="min-h-10 rounded-lg bg-gray-700 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-600">Stop</button>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="gallery-device" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Equipment <span class="font-normal text-gray-500">(optional)</span></label>
                            <select id="gallery-device" name="device_id"  class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">No equipment link</option>
                                @foreach($devices as $device)
                                    <option value="{{ $device->id }}" @selected(old('device_id') == $device->id)>
                                        {{ $device->property_number ?: 'Unnumbered' }}{{ $device->type?->name ? ' · ' . $device->type->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="gallery-record" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Checklist record <span class="font-normal text-gray-500">(optional)</span></label>
                            <select id="gallery-record" name="maintenance_record_id" class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">Not linked to a checklist</option>
                                @foreach($maintenanceRecords as $record)
                                    <option value="{{ $record->id }}" @selected(old('maintenance_record_id') == $record->id)>
                                        {{ $record->device?->property_number ?: 'Equipment' }} · {{ $record->maintenance_date?->format('M j, Y') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="gallery-captured-at" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Photo date and time</label>
                            <input id="gallery-captured-at" name="captured_at" type="datetime-local" value="{{ old('captured_at', now()->format('Y-m-d\\TH:i')) }}" class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>

                        <div>
                            <label for="gallery-caption" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Caption <span class="font-normal text-gray-500">(optional)</span></label>
                            <input id="gallery-caption" name="caption" value="{{ old('caption') }}" maxlength="255" placeholder="e.g. System unit after cleaning" class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 placeholder-gray-400 focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </div>
                    </div>

                    <input id="gallery-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                    <input id="gallery-camera" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" class="sr-only">
                    <div class="flex flex-wrap gap-2">
                        <label for="gallery-photo" title="Select an existing photo from this device" class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 16V4m0 0L8 8m4-4 4 4M5 13v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/>
                            </svg>
                            Upload photo
                        </label>
                        <button id="gallery-take-photo" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h3l1.5-2h7L17 7h3a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1Z"/><circle cx="12" cy="13" r="3.2"/></svg>
                            Take photo
                        </button>
                        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Save photo</button>
                    </div>
                    <div id="gallery-photo-name" class="text-xs text-gray-500 dark:text-gray-400">No photo selected.</div>
                    </div>
                </form>
            </section>
        </div>

        <div x-show="slideshowOpen" x-cloak x-transition class="fixed inset-0 z-[70] flex items-center justify-center bg-black/80 p-4" role="dialog" aria-modal="true" aria-label="Maintenance photo slideshow">
            <div x-on:click.outside="stopSlideshow()" class="relative flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-2xl bg-gray-950 shadow-2xl">
                <button type="button" x-on:click="stopSlideshow()" class="absolute right-3 top-3 z-10 inline-flex h-10 w-10 items-center justify-center rounded-full bg-black/60 text-2xl text-white hover:bg-black/80" aria-label="Close slideshow">&times;</button>
                <div class="flex min-h-[55vh] items-center justify-center bg-black">
                    <img x-bind:src="slides[activeSlide]?.url" x-bind:alt="slides[activeSlide]?.caption" class="max-h-[72vh] max-w-full object-contain">
                </div>
                <div class="flex items-center justify-between gap-3 bg-gray-900 px-4 py-3 text-sm text-white">
                    <div class="min-w-0">
                        <div class="truncate font-semibold" x-text="slides[activeSlide]?.property_number || 'Equipment'"></div>
                        <div class="truncate text-xs text-gray-300" x-text="slides[activeSlide]?.caption + ' · ' + slides[activeSlide]?.captured_at"></div>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" x-on:click="previousSlide()" class="rounded-lg bg-gray-700 px-3 py-2 font-semibold hover:bg-gray-600" aria-label="Previous photo">&larr;</button>
                        <button type="button" x-on:click="nextSlide()" class="rounded-lg bg-gray-700 px-3 py-2 font-semibold hover:bg-gray-600" aria-label="Next photo">&rarr;</button>
                        <a x-bind:href="slides[activeSlide]?.url" x-bind:download="'maintenance-photo-' + (activeSlide + 1)" class="rounded-lg bg-blue-600 px-3 py-2 font-semibold hover:bg-blue-500">Download</a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
        function initMaintenanceGalleryCamera() {
            const mainInput = document.getElementById('gallery-photo');
            const cameraInput = document.getElementById('gallery-camera');
            const name = document.getElementById('gallery-photo-name');
            const preview = document.getElementById('gallery-photo-preview');
            const placeholder = document.getElementById('gallery-photo-placeholder');
            const takePhotoButton = document.getElementById('gallery-take-photo');
            const cameraPreview = document.getElementById('gallery-camera-preview');
            const cameraCanvas = document.getElementById('gallery-camera-canvas');
            const cameraControls = document.getElementById('gallery-camera-controls');
            const form = mainInput?.form;
            let cameraStream = window.__maintenanceGalleryCameraStream || null;
            let cameraRequest = 0;
            if (!mainInput || !form || form.dataset.cameraReady === '1') return;
            form.dataset.cameraReady = '1';

            function setMainPhoto(file, sourceInput = null) {
                if (!file) return;

                try {
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    mainInput.files = transfer.files;
                } catch (error) {
                    if (sourceInput && sourceInput !== mainInput) {
                        mainInput.removeAttribute('name');
                        sourceInput.setAttribute('name', 'photo');
                    }
                }

                name.textContent = `${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
                preview.src = URL.createObjectURL(file);
                preview.classList.remove('hidden');
                placeholder?.classList.add('hidden');
            }

            function showSelectedPhoto(input) {
                setMainPhoto(input?.files?.[0], input);
            }

            function closeCamera() {
                cameraRequest += 1;
                if (cameraStream) {
                    cameraStream.getTracks().forEach((track) => track.stop());
                    cameraStream = null;
                    window.__maintenanceGalleryCameraStream = null;
                }
                cameraPreview.pause?.();
                cameraPreview.srcObject = null;
                cameraPreview.classList.add('hidden');
                cameraControls.classList.add('hidden');
                cameraControls.classList.remove('flex');
                if (preview.classList.contains('hidden')) {
                    placeholder?.classList.remove('hidden');
                }
            }

            async function openCamera() {
                const request = ++cameraRequest;
                if (!navigator.mediaDevices?.getUserMedia) {
                    cameraInput.click();
                    return;
                }

                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: { ideal: 'environment' } },
                        audio: false,
                    });
                    if (request !== cameraRequest) {
                        stream.getTracks().forEach((track) => track.stop());
                        return;
                    }
                    cameraStream = stream;
                    cameraPreview.srcObject = cameraStream;
                    window.__maintenanceGalleryCameraStream = cameraStream;
                    placeholder?.classList.add('hidden');
                    preview.classList.add('hidden');
                    cameraPreview.classList.remove('hidden');
                    cameraControls.classList.remove('hidden');
                    cameraControls.classList.add('flex');
                } catch (error) {
                    cameraInput.click();
                }
            }

            function capturePhoto() {
                if (!cameraPreview.videoWidth || !cameraPreview.videoHeight) return;
                cameraCanvas.width = cameraPreview.videoWidth;
                cameraCanvas.height = cameraPreview.videoHeight;
                cameraCanvas.getContext('2d').drawImage(cameraPreview, 0, 0, cameraCanvas.width, cameraCanvas.height);
                cameraCanvas.toBlob((blob) => {
                    if (!blob) return;
                    setMainPhoto(new File([blob], `maintenance-photo-${Date.now()}.jpg`, { type: 'image/jpeg' }));
                    closeCamera();
                }, 'image/jpeg', 0.9);
            }

            mainInput?.addEventListener('change', () => showSelectedPhoto(mainInput));
            cameraInput?.addEventListener('change', () => showSelectedPhoto(cameraInput));
            takePhotoButton?.addEventListener('click', openCamera);
            document.getElementById('gallery-camera-capture')?.addEventListener('click', capturePhoto);
            document.getElementById('gallery-camera-cancel')?.addEventListener('click', closeCamera);
        }
        document.addEventListener('DOMContentLoaded', initMaintenanceGalleryCamera, { once: true });
        document.addEventListener('livewire:navigated', initMaintenanceGalleryCamera);
        document.addEventListener('livewire:navigating', function () {
            const stream = window.__maintenanceGalleryCameraStream;
            if (stream) stream.getTracks().forEach((track) => track.stop());
            window.__maintenanceGalleryCameraStream = null;
        });
        initMaintenanceGalleryCamera();

        if (!window.__maintenanceGallerySpaReady) {
            window.__maintenanceGallerySpaReady = true;
            document.addEventListener('submit', async function (event) {
                const form = event.target;
                if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'POST' || form.dataset.noSpa === 'true') return;
                const action = new URL(form.action || window.location.href, window.location.href);
                if (!action.pathname.includes('/admin/maintenance-gallery') || action.pathname.includes('bulk-download')) return;
                event.preventDefault();
                const submitter = event.submitter;
                if (submitter) submitter.disabled = true;
                try {
                    const response = await fetch(action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' } });
                    if (!response.ok) throw new Error('Request failed');
                    const next = new URL(response.url, window.location.href);
                    const path = (window.adminLocalNavigatePath ? window.adminLocalNavigatePath(next) : next.pathname) + next.search;
                    if (window.Livewire?.navigate) window.Livewire.navigate(path); else window.location.href = path;
                } catch (error) {
                    window.location.reload();
                } finally {
                    if (submitter) submitter.disabled = false;
                }
            });
        }
    </script>
@endsection

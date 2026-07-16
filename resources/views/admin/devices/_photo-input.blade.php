@php
    $photoInputId = $photoInputId ?? 'equipment_photo';
    $existingPhotoPath = $existingPhotoPath ?? null;
    $photoStatusId = $photoInputId . '_status';
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
        tabindex="-1"
        aria-hidden="true"
    >
    <button
        type="button"
        onclick="openEquipmentPhotoCamera(@js($photoInputId), @js($photoStatusId))"
        class="mt-2 inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-offset-gray-800"
    >
        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5A2.5 2.5 0 0 1 5.5 6H7l1.2-1.8A2 2 0 0 1 9.9 3.3h4.2a2 2 0 0 1 1.7.9L17 6h1.5A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-8Z" />
            <circle cx="12" cy="12.5" r="3.5" />
        </svg>
        Take Photo
    </button>
    <p id="{{ $photoStatusId }}" class="mt-2 text-xs text-gray-500 dark:text-gray-400" aria-live="polite">
        Optional. Opens the device camera when supported. Maximum 10 MB.
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

@once
    @push('scripts')
        <script>
            (function () {
                if (window.openEquipmentPhotoCamera) return;

                var activePhotoInput = null;
                var activePhotoStatus = null;
                var sharedCameraStream = null;

                function cameraPanel() {
                    var existing = document.getElementById('equipment-photo-camera-panel');
                    if (existing) return existing;

                    var panel = document.createElement('div');
                    panel.id = 'equipment-photo-camera-panel';
                    panel.className = 'fixed inset-0 z-50 hidden items-center justify-center bg-gray-950/90 px-4 py-6';
                    panel.setAttribute('role', 'dialog');
                    panel.setAttribute('aria-modal', 'true');
                    panel.setAttribute('aria-label', 'Equipment camera');
                    panel.innerHTML = [
                        '<div class="w-full max-w-md overflow-hidden rounded-xl border border-gray-700 bg-gray-900 shadow-2xl">',
                        '<div class="flex items-center justify-between border-b border-gray-700 px-4 py-3">',
                        '<h2 class="text-base font-semibold text-white">Take Equipment Photo</h2>',
                        '<button type="button" class="rounded-lg p-2 text-gray-300 hover:bg-gray-800 hover:text-white focus:outline-none focus:ring-2 focus:ring-blue-500" aria-label="Close camera" data-equipment-photo-close>',
                        '<svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6 6 18" /></svg>',
                        '</button>',
                        '</div>',
                        '<div class="aspect-square bg-black">',
                        '<video id="equipment-photo-camera-video" class="h-full w-full object-cover" autoplay playsinline muted></video>',
                        '<canvas id="equipment-photo-camera-canvas" class="hidden"></canvas>',
                        '</div>',
                        '<div class="flex gap-3 px-4 py-4">',
                        '<button type="button" class="inline-flex flex-1 items-center justify-center rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-gray-100 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500" data-equipment-photo-close>Cancel</button>',
                        '<button type="button" id="equipment-photo-camera-capture" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">',
                        '<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3.5" /><path stroke-linecap="round" stroke-linejoin="round" d="M4 8.5A2.5 2.5 0 0 1 6.5 6H8l1.1-1.6a2 2 0 0 1 1.7-.9h2.4a2 2 0 0 1 1.7.9L16 6h1.5A2.5 2.5 0 0 1 20 8.5v7A2.5 2.5 0 0 1 17.5 18h-11A2.5 2.5 0 0 1 4 15.5v-7Z" /></svg>',
                        'Capture Photo',
                        '</button>',
                        '</div>',
                        '</div>'
                    ].join('');

                    panel.querySelectorAll('[data-equipment-photo-close]').forEach(function (button) {
                        button.addEventListener('click', closeEquipmentPhotoCamera);
                    });

                    panel.querySelector('#equipment-photo-camera-capture').addEventListener('click', captureEquipmentPhotoCamera);
                    document.body.appendChild(panel);

                    return panel;
                }

                function setPhotoStatus(message) {
                    if (activePhotoStatus) {
                        activePhotoStatus.textContent = message;
                    }
                }

                function stopCameraStream() {
                    if (sharedCameraStream) {
                        sharedCameraStream.getTracks().forEach(function (track) {
                            track.stop();
                        });
                        sharedCameraStream = null;
                    }
                }

                function closeEquipmentPhotoCamera() {
                    var panel = document.getElementById('equipment-photo-camera-panel');
                    var video = document.getElementById('equipment-photo-camera-video');

                    stopCameraStream();

                    if (video) {
                        video.srcObject = null;
                    }

                    if (panel) {
                        panel.classList.add('hidden');
                        panel.classList.remove('flex');
                    }
                }

                async function captureEquipmentPhotoCamera() {
                    var video = document.getElementById('equipment-photo-camera-video');
                    var canvas = document.getElementById('equipment-photo-camera-canvas');
                    var captureButton = document.getElementById('equipment-photo-camera-capture');

                    if (!activePhotoInput || !sharedCameraStream || !video.videoWidth || !video.videoHeight) {
                        setPhotoStatus('Camera is not ready yet.');
                        return;
                    }

                    captureButton.disabled = true;
                    captureButton.classList.add('opacity-60', 'cursor-wait');
                    setPhotoStatus('Capturing photo...');

                    try {
                        var size = Math.min(video.videoWidth, video.videoHeight);
                        var sourceX = (video.videoWidth - size) / 2;
                        var sourceY = (video.videoHeight - size) / 2;

                        canvas.width = 1280;
                        canvas.height = 1280;
                        canvas.getContext('2d').drawImage(video, sourceX, sourceY, size, size, 0, 0, canvas.width, canvas.height);

                        var blob = await new Promise(function (resolve) {
                            canvas.toBlob(resolve, 'image/jpeg', 0.9);
                        });

                        if (!blob || blob.size > 10 * 1024 * 1024) {
                            throw new Error('The captured photo is larger than 10 MB.');
                        }

                        var file = new File([blob], 'equipment-photo.jpg', { type: 'image/jpeg' });
                        var transfer = new DataTransfer();
                        transfer.items.add(file);
                        activePhotoInput.files = transfer.files;
                        activePhotoInput.dispatchEvent(new Event('change', { bubbles: true }));

                        setPhotoStatus('Photo captured. Save the form to upload it.');
                        closeEquipmentPhotoCamera();
                    } catch (error) {
                        setPhotoStatus(error.message || 'Unable to capture photo. Please try again.');
                    } finally {
                        captureButton.disabled = false;
                        captureButton.classList.remove('opacity-60', 'cursor-wait');
                    }
                }

                window.openEquipmentPhotoCamera = async function (inputId, statusId) {
                    var panel = cameraPanel();
                    var video = document.getElementById('equipment-photo-camera-video');

                    activePhotoInput = document.getElementById(inputId);
                    activePhotoStatus = document.getElementById(statusId);

                    if (!activePhotoInput) {
                        return;
                    }

                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        setPhotoStatus('Camera access is not available in this browser.');
                        return;
                    }

                    setPhotoStatus('Opening camera...');

                    try {
                        stopCameraStream();
                        sharedCameraStream = await navigator.mediaDevices.getUserMedia({
                            video: {
                                facingMode: { ideal: 'environment' },
                                width: { ideal: 1280 },
                                height: { ideal: 1280 }
                            },
                            audio: false
                        });

                        video.srcObject = sharedCameraStream;
                        await video.play();

                        panel.classList.remove('hidden');
                        panel.classList.add('flex');
                        setPhotoStatus('Camera ready.');
                    } catch (error) {
                        setPhotoStatus(window.isSecureContext
                            ? 'Camera permission was blocked or no camera was found.'
                            : 'Camera requires HTTPS or localhost.');
                        closeEquipmentPhotoCamera();
                    }
                };
            })();
        </script>
    @endpush
@endonce

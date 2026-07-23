import './bootstrap';

// NOTE: Do NOT manually import/start Alpine.js here.
// Livewire (v3/v4) already bundles and auto-starts its own Alpine instance
// via the @livewireScripts directive in resources/views/admin/layouts/app.blade.php.
// Starting a second Alpine instance on top of it causes two independent
// reactive runtimes to fight over the same DOM: click handlers fire against
// one instance while x-bind/:action bindings are read from the other, which
// is exactly what produced the "bulk toggle does nothing" and
// "delete confirmation not submitting" bugs on the Colleges page.

// Livewire's navigate API gives the admin area SPA-style page transitions while
// keeping the browser history and server-rendered Blade responses intact.  This
// delegated fallback also covers existing links/forms that do not yet have an
// explicit `wire:navigate` attribute.
(function setupAdminSpaNavigation() {
    if (window.__adminSpaNavigationReady) return;
    window.__adminSpaNavigationReady = true;

    const isLocalHost = (host) => ['localhost', '127.0.0.1', '::1'].includes(host);
    const canNavigate = () => window.Livewire && typeof window.Livewire.navigate === 'function';
    const currentBasePath = () => {
        const index = window.location.pathname.indexOf('/admin');
        return index >= 0 ? window.location.pathname.slice(0, index) : '';
    };
    const localNavigatePath = (url) => {
        const adminIndex = url.pathname.indexOf('/admin');
        const pathname = adminIndex >= 0
            ? `${currentBasePath()}${url.pathname.slice(adminIndex)}`
            : url.pathname;

        return `${pathname}${url.search}${url.hash}`;
    };
    window.adminLocalNavigatePath = localNavigatePath;

    document.addEventListener('click', (event) => {
        if (!canNavigate() || event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || link.target === '_blank' ||
            link.hasAttribute('wire:navigate') || link.dataset.noSpa === 'true') return;

        const url = new URL(link.href, window.location.href);
        const sameOrigin = url.origin === window.location.origin ||
            (isLocalHost(url.hostname) && isLocalHost(window.location.hostname));
        const path = localNavigatePath(url);
        if (!sameOrigin || url.protocol !== window.location.protocol ||
            (path === `${window.location.pathname}${window.location.search}` && !url.hash)) return;

        event.preventDefault();
        window.Livewire.navigate(path);
    });

    document.addEventListener('submit', (event) => {
        if (!canNavigate() || event.defaultPrevented) return;
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.method.toUpperCase() !== 'GET' ||
            form.hasAttribute('wire:navigate') || form.dataset.noSpa === 'true') return;

        const action = new URL(form.action || window.location.href, window.location.href);
        const sameOrigin = action.origin === window.location.origin ||
            (isLocalHost(action.hostname) && isLocalHost(window.location.hostname));
        if (!sameOrigin) return;

        event.preventDefault();
        const params = new URLSearchParams(new FormData(form));
        const query = params.toString();
        const path = localNavigatePath(action).split('#')[0].split('?')[0];
        window.Livewire.navigate(`${path}${query ? `?${query}` : ''}`);
    });
})();

// Maintenance checklist camera: initialize on every SPA navigation so the
// camera controls work immediately without requiring a full page reload.
(function setupChecklistCamera() {
    const stopCamera = () => {
        const stream = window.__checklistCameraStream;
        if (stream) stream.getTracks().forEach((track) => track.stop());
        window.__checklistCameraStream = null;

        const video = document.getElementById('checklist-camera-preview');
        const capture = document.getElementById('checklist-camera-capture');
        const stop = document.getElementById('checklist-camera-stop');
        const start = document.getElementById('checklist-camera-start');
        if (video) {
            video.srcObject = null;
            video.classList.add('hidden');
        }
        capture?.classList.add('hidden');
        stop?.classList.add('hidden');
        start?.classList.remove('hidden');
    };

    const init = () => {
        const input = document.getElementById('maintenance-photo');
        const form = input?.form;
        const video = document.getElementById('checklist-camera-preview');
        const canvas = document.getElementById('checklist-camera-canvas');
        const image = document.getElementById('checklist-photo-preview');
        const placeholder = document.getElementById('checklist-photo-placeholder');
        const start = document.getElementById('checklist-camera-start');
        const capture = document.getElementById('checklist-camera-capture');
        const stop = document.getElementById('checklist-camera-stop');
        const name = document.getElementById('checklist-photo-name');
        if (!form || !input || !video || !canvas || !start || !capture || !stop || form.dataset.cameraReady === '1') return;
        form.dataset.cameraReady = '1';

        const showFile = (file) => {
            if (!file) return;
            image.src = URL.createObjectURL(file);
            image.classList.remove('hidden');
            placeholder?.classList.add('hidden');
            if (name) name.textContent = file.name || 'Captured photo';
        };

        input.addEventListener('change', () => showFile(input.files?.[0]));
        start.addEventListener('click', async () => {
            if (!navigator.mediaDevices?.getUserMedia) {
                input.click();
                return;
            }
            try {
                stopCamera();
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: { ideal: 'environment' } },
                    audio: false,
                });
                window.__checklistCameraStream = stream;
                video.srcObject = stream;
                await video.play();
                video.classList.remove('hidden');
                image?.classList.add('hidden');
                placeholder?.classList.add('hidden');
                start.classList.add('hidden');
                capture.classList.remove('hidden');
                stop.classList.remove('hidden');
            } catch (error) {
                input.click();
            }
        });
        capture.addEventListener('click', () => {
            const stream = window.__checklistCameraStream;
            if (!stream || !video.videoWidth || !video.videoHeight) return;
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = 1280;
            canvas.height = 1280;
            canvas.getContext('2d').drawImage(video, (video.videoWidth - size) / 2, (video.videoHeight - size) / 2, size, size, 0, 0, 1280, 1280);
            canvas.toBlob((blob) => {
                if (!blob || blob.size > 10 * 1024 * 1024) return;
                const file = new File([blob], `maintenance-photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
                try {
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    input.files = transfer.files;
                } catch (error) {
                    return;
                }
                showFile(file);
                stopCamera();
            }, 'image/jpeg', 0.9);
        });
        stop.addEventListener('click', stopCamera);
    };

    document.addEventListener('DOMContentLoaded', init, { once: true });
    document.addEventListener('livewire:navigated', init);
    document.addEventListener('livewire:navigating', stopCamera);
    init();
})();

// Equipment details camera fallback. Blade supplies the same handlers on a
// full render; these globals keep them available when the page is reached via
// SPA navigation where inline scripts may not be evaluated again.
(function setupEquipmentDetailsCamera() {
    let stream = null;
    let requestId = 0;

    const setBusy = (busy) => {
        ['device-take-photo-button', 'device-capture-photo-button', 'device-clear-photo-button']
            .map((id) => document.getElementById(id))
            .filter(Boolean)
            .forEach((button) => { button.disabled = busy; });
    };

    const close = () => {
        requestId += 1;
        (stream || window.__deviceDetailsCameraStream)?.getTracks().forEach((track) => track.stop());
        stream = null;
        window.__deviceDetailsCameraStream = null;
        const video = document.getElementById('device-camera-video');
        const controls = document.getElementById('device-camera-controls');
        if (video) {
            video.pause?.();
            video.srcObject = null;
            video.classList.add('hidden');
        }
        controls?.classList.add('hidden');
        controls?.classList.remove('flex');
    };

    const open = async () => {
        const video = document.getElementById('device-camera-video');
        const controls = document.getElementById('device-camera-controls');
        const status = document.getElementById('device-photo-status');
        if (!video || !controls || !status) return;
        if (!navigator.mediaDevices?.getUserMedia) {
            status.textContent = 'Camera access is not available in this browser.';
            return;
        }

        close();
        const currentRequest = ++requestId;
        status.textContent = 'Opening camera...';
        setBusy(true);
        try {
            const nextStream = await navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            });
            if (currentRequest !== requestId) {
                nextStream.getTracks().forEach((track) => track.stop());
                return;
            }
            stream = nextStream;
            window.__deviceDetailsCameraStream = stream;
            video.srcObject = stream;
            await video.play();
            video.classList.remove('hidden');
            controls.classList.remove('hidden');
            controls.classList.add('flex');
            status.textContent = 'Camera ready.';
        } catch (error) {
            status.textContent = 'Camera permission was blocked or no camera was found.';
        } finally {
            setBusy(false);
        }
    };

    const capture = async () => {
        const form = document.getElementById('device-photo-form');
        const video = document.getElementById('device-camera-video');
        const canvas = document.getElementById('device-camera-canvas');
        const image = document.getElementById('device-photo-image');
        const empty = document.getElementById('device-photo-empty');
        const status = document.getElementById('device-photo-status');
        if (!form || !video || !canvas || !image || !status || !stream || !video.videoWidth) return;
        setBusy(true);
        status.textContent = 'Saving photo...';
        try {
            const size = Math.min(video.videoWidth, video.videoHeight);
            canvas.width = 1280;
            canvas.height = 1280;
            canvas.getContext('2d').drawImage(video, (video.videoWidth - size) / 2, (video.videoHeight - size) / 2, size, size, 0, 0, 1280, 1280);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
            if (!blob || blob.size > 10 * 1024 * 1024) throw new Error('The captured photo is larger than 10 MB.');
            const data = new FormData(form);
            data.append('equipment_photo', blob, 'equipment-photo.jpg');
            const response = await fetch(form.action, { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            if (!response.ok) throw new Error('Photo upload failed.');
            const result = await response.json();
            image.src = `${result.photo_url}?v=${Date.now()}`;
            image.classList.remove('hidden');
            empty?.classList.add('hidden');
            empty?.classList.remove('flex');
            document.getElementById('device-clear-photo-button')?.classList.remove('hidden');
            status.textContent = result.message || 'Photo saved.';
            close();
        } catch (error) {
            status.textContent = error.message || 'Photo upload failed. Please try again.';
        } finally {
            setBusy(false);
        }
    };

    const clearPhoto = async () => {
        if (!window.confirm('Delete this equipment photo?')) return;
        const form = document.getElementById('device-photo-delete-form');
        const image = document.getElementById('device-photo-image');
        const empty = document.getElementById('device-photo-empty');
        const status = document.getElementById('device-photo-status');
        if (!form || !status) return;
        setBusy(true);
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
            if (!response.ok) throw new Error('Photo delete failed.');
            const result = await response.json();
            image?.classList.add('hidden');
            image?.removeAttribute('src');
            empty?.classList.remove('hidden');
            empty?.classList.add('flex');
            document.getElementById('device-clear-photo-button')?.classList.add('hidden');
            status.textContent = result.message || 'Photo cleared.';
        } catch (error) {
            status.textContent = error.message || 'Photo delete failed. Please try again.';
        } finally {
            setBusy(false);
        }
    };

    if (!window.openDeviceCamera) window.openDeviceCamera = open;
    if (!window.closeDeviceCamera) window.closeDeviceCamera = close;
    if (!window.captureDevicePhoto) window.captureDevicePhoto = capture;
    if (!window.clearDevicePhoto) window.clearDevicePhoto = clearPhoto;
    document.addEventListener('livewire:navigating', close);
})();

(function setupAdminNavigationState() {
    if (window.__adminNavigationStateReady) return;
    window.__adminNavigationStateReady = true;

    const linkActiveClasses = ['bg-blue-50', 'text-blue-700', 'dark:bg-blue-900/30', 'dark:text-blue-400'];
    const linkInactiveClasses = ['text-gray-700', 'hover:bg-gray-100', 'dark:text-gray-300', 'dark:hover:bg-gray-700'];
    const iconActiveClasses = ['text-blue-600', 'dark:text-blue-400'];
    const iconInactiveClasses = ['text-gray-500', 'group-hover:text-gray-700', 'dark:text-gray-400', 'dark:group-hover:text-gray-200'];

    const adminPath = (path) => {
        const index = path.indexOf('/admin');
        return (index >= 0 ? path.slice(index) : path).replace(/\/+$/, '') || '/';
    };

    const routeGroup = (path) => {
        if (path === '/admin' || path === '/admin/dashboard') return 'dashboard';
        if (path.startsWith('/admin/locations') ||
            path.startsWith('/admin/colleges') ||
            path.startsWith('/admin/offices') ||
            path.startsWith('/admin/staff') ||
            path.startsWith('/admin/org-browser')) return 'locations';
        if (path.startsWith('/admin/devices')) return 'devices';
        if (path.startsWith('/admin/issuance')) return 'issuance';
        if (path.startsWith('/admin/reports') || path.startsWith('/admin/maintenance-cleanup')) return 'reports';
        if (path.startsWith('/admin/database')) return 'database';
        if (path.startsWith('/admin/maintenance-gallery')) return 'gallery';
        if (path.startsWith('/admin/scanner')) return 'scanner';
        if (path.startsWith('/admin/support')) return 'support';
        if (path.startsWith('/admin/users')) return 'users';
        if (path.startsWith('/admin/logs')) return 'logs';
        return null;
    };

    const currentPageGroup = () => {
        const marker = document.querySelector('main[data-current-nav-group]') ||
            document.querySelector('body[data-current-nav-group]');
        return marker && marker.dataset.currentNavGroup
            ? marker.dataset.currentNavGroup
            : routeGroup(adminPath(window.location.pathname));
    };

    const setClasses = (element, activeClasses, inactiveClasses, isActive) => {
        element.classList.remove(...activeClasses, ...inactiveClasses);
        element.classList.add(...(isActive ? activeClasses : inactiveClasses));
    };

    const syncSidebarChrome = (open) => {
        document.querySelectorAll('[data-admin-sidebar]').forEach((sidebar) => {
            sidebar.dataset.open = open ? 'true' : 'false';
            sidebar.setAttribute('aria-hidden', (!open && window.innerWidth < 1024).toString());
        });

        document.querySelectorAll('[data-sidebar-open]').forEach((button) => {
            button.setAttribute('aria-expanded', open.toString());
        });

        document.documentElement.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
        document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
    };

    const setSidebarOpen = (open) => {
        document.querySelectorAll('[x-data]').forEach((element) => {
            const data = element._x_dataStack && element._x_dataStack[0];
            if (!data) return;

            if ('sidebarOpen' in data) data.sidebarOpen = open;
            if (open && 'profileOpen' in data) data.profileOpen = false;
            if (open && 'themeOpen' in data) data.themeOpen = false;
        });

        syncSidebarChrome(open);
    };

    const closeOpenMenus = () => {
        document.querySelectorAll('[x-data]').forEach((element) => {
            const data = element._x_dataStack && element._x_dataStack[0];
            if (!data) return;

            if ('sidebarOpen' in data) data.sidebarOpen = false;
            if ('profileOpen' in data) data.profileOpen = false;
            if ('themeOpen' in data) data.themeOpen = false;
        });

        syncSidebarChrome(false);
    };

    const refreshTheme = () => {
        if (typeof window.getSavedTheme !== 'function' || typeof window.setTheme !== 'function') return;

        const theme = window.getSavedTheme();
        window.setTheme(theme);

        document.querySelectorAll('[x-data]').forEach((element) => {
            const data = element._x_dataStack && element._x_dataStack[0];
            if (data && 'theme' in data) data.theme = theme;
        });
    };

    const refreshSidebarActiveState = (group = null) => {
        const currentGroup = group || currentPageGroup();

        document.querySelectorAll('aside nav a[data-nav-group][href]').forEach((link) => {
            const linkGroup = link.dataset.navGroup ||
                routeGroup(adminPath(new URL(link.href, window.location.href).pathname));
            const isActive = Boolean(currentGroup && linkGroup === currentGroup);

            link.dataset.active = isActive ? 'true' : 'false';
            if (isActive) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }

            setClasses(link, linkActiveClasses, linkInactiveClasses, isActive);

            const icon = link.querySelector('svg');
            if (icon) setClasses(icon, iconActiveClasses, iconInactiveClasses, isActive);
        });
    };

    const refreshNavigationState = () => {
        refreshTheme();
        refreshSidebarActiveState();
        closeOpenMenus();
    };

    document.addEventListener('click', (event) => {
        const openButton = event.target.closest('[data-sidebar-open]');
        if (openButton) {
            setSidebarOpen(true);
            return;
        }

        const closeButton = event.target.closest('[data-sidebar-close], [data-sidebar-overlay]');
        if (closeButton) {
            setSidebarOpen(false);
            return;
        }

        const link = event.target.closest('aside nav a[data-nav-group][href]');
        if (!link || event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        refreshSidebarActiveState(link.dataset.navGroup);
        setSidebarOpen(false);
    }, true);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeOpenMenus();
    });

    window.addEventListener('resize', () => {
        const root = document.querySelector('[x-data]');
        const data = root && root._x_dataStack && root._x_dataStack[0];
        syncSidebarChrome(Boolean(data && data.sidebarOpen));
    });

    document.addEventListener('livewire:navigating', closeOpenMenus);
    document.addEventListener('livewire:navigated', refreshNavigationState);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refreshNavigationState, { once: true });
    } else {
        refreshNavigationState();
    }
})();

(function setupAdminDashboardCharts() {
    if (window.__adminDashboardChartsReady) return;
    window.__adminDashboardChartsReady = true;

    const state = {
        loading: null,
        instances: [],
    };

    const loadChartLibrary = () => {
        if (window.Chart) return Promise.resolve(window.Chart);
        if (state.loading) return state.loading;

        state.loading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
            script.async = true;
            script.onload = () => resolve(window.Chart);
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return state.loading;
    };

    const destroyCharts = () => {
        state.instances.forEach((chart) => {
            try { chart.destroy(); } catch (error) { /* detached chart */ }
        });
        state.instances = [];

        if (window.Chart) {
            document.querySelectorAll('[data-admin-dashboard-charts] canvas').forEach((canvas) => {
                const chart = window.Chart.getChart?.(canvas);
                if (chart) chart.destroy();
            });
        }
    };

    const renderCharts = () => {
        const root = document.querySelector('[data-admin-dashboard-charts]');
        if (!root) {
            destroyCharts();
            return;
        }

        let data;
        try {
            data = JSON.parse(root.dataset.chartData || '{}');
        } catch (error) {
            return;
        }

        loadChartLibrary().then((Chart) => {
            if (!Chart || !root.isConnected) return;

            destroyCharts();

            const create = (id, type, chartData, options) => {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                const chart = new Chart(canvas, {
                    type,
                    data: chartData,
                    options,
                });
                state.instances.push(chart);
            };

            const common = {
                responsive: true,
                maintainAspectRatio: false,
            };

            create('statusChart', 'bar', {
                labels: data.condition?.labels || [],
                datasets: [{
                    label: 'Equipment',
                    data: data.condition?.values || [],
                    backgroundColor: ['#22c55e', '#ef4444', '#6b7280'],
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            create('totalEquipmentChart', 'pie', {
                labels: data.status?.labels || data.availability?.labels || [],
                datasets: [{
                    data: data.status?.values || data.availability?.values || [],
                    backgroundColor: ['#10b981', '#6366f1', '#f59e0b', '#64748b'],
                    borderWidth: 2,
                }],
            }, {
                ...common,
                plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } } },
            });

            create('typeChart', 'doughnut', {
                labels: data.type?.labels || [],
                datasets: [{
                    data: data.type?.values || [],
                    backgroundColor: ['#3b82f6', '#6366f1', '#22c55e', '#f59e0b', '#ef4444', '#14b8a6', '#ec4899', '#8b5cf6'],
                    borderWidth: 2,
                }],
            }, {
                ...common,
                plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12 } } },
            });

            create('officeChart', 'bar', {
                labels: data.office?.labels || [],
                datasets: [{
                    label: 'Issued Equipment',
                    data: data.office?.values || [],
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            create('endUsersLocationChart', 'bar', {
                labels: data.end_users?.labels || [],
                datasets: [{
                    label: 'Active End Users',
                    data: data.end_users?.values || [],
                    backgroundColor: '#0ea5e9',
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });

            create('maintenanceChart', 'bar', {
                labels: data.maintenance?.labels || [],
                datasets: [{
                    label: 'Maintained Equipment',
                    data: data.maintenance?.values || [],
                    backgroundColor: '#0ea5e9',
                    borderRadius: 6,
                    borderSkipped: false,
                }],
            }, {
                ...common,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            });
        }).catch(() => {
            // The dashboard remains usable if the optional chart CDN is offline.
        });
    };

    const scheduleRender = () => window.setTimeout(renderCharts, 40);
    document.addEventListener('livewire:navigated', scheduleRender);
    document.addEventListener('livewire:navigating', destroyCharts);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleRender, { once: true });
    } else {
        scheduleRender();
    }
})();

(function setupAdminQrScanner() {
    if (window.__adminQrScannerReady) return;
    window.__adminQrScannerReady = true;

    const libraryUrl = 'https://unpkg.com/html5-qrcode';
    const state = window.__adminQrScannerState = window.__adminQrScannerState || {
        scanner: null,
        isRunning: false,
        detectionHandled: false,
        audioContext: null,
    };

    const unlockAudio = () => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            state.audioContext = state.audioContext || new AudioContext();
            if (state.audioContext.state === 'suspended') {
                state.audioContext.resume().catch(() => {});
            }
        } catch (error) {
            // Audio is optional; scanning must continue when it is unavailable.
        }
    };

    const playDetectionBeep = () => {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            state.audioContext = state.audioContext || new AudioContext();
            const context = state.audioContext;
            const play = () => {
                const now = context.currentTime;
                const oscillator = context.createOscillator();
                const gain = context.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, now);
                gain.gain.setValueAtTime(0.0001, now);
                gain.gain.exponentialRampToValueAtTime(0.18, now + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.16);

                oscillator.connect(gain);
                gain.connect(context.destination);
                oscillator.start(now);
                oscillator.stop(now + 0.16);
            };

            if (context.state === 'suspended') {
                context.resume().then(play).catch(() => {});
            } else {
                play();
            }
        } catch (error) {
            // Audio is optional; scanning must continue when it is unavailable.
        }
    };

    const setText = (element, text) => {
        if (element) element.textContent = text;
    };

    const loadQrLibrary = () => {
        if (window.Html5Qrcode) return Promise.resolve();
        if (window.__adminQrScannerLibraryLoading) return window.__adminQrScannerLibraryLoading;

        window.__adminQrScannerLibraryLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = libraryUrl;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return window.__adminQrScannerLibraryLoading;
    };

    const stopScanner = () => {
        if (!state.scanner || !state.isRunning) return Promise.resolve();

        return state.scanner.stop()
            .catch(() => {})
            .finally(() => {
                state.isRunning = false;
            });
    };

    const initQrScanner = () => {
        const reader = document.getElementById('reader');
        const startBtn = document.getElementById('start-scanner');
        const stopBtn = document.getElementById('stop-scanner');
        const statusEl = document.getElementById('scan-status');
        const resultEl = document.getElementById('scan-result');

        if (!reader || !startBtn || !stopBtn || startBtn.dataset.scannerReady === 'true') return;

        startBtn.dataset.scannerReady = 'true';

        const setStatus = (text) => setText(statusEl, text);
        const setResult = (text) => setText(resultEl, text || '-');

        const updateButtons = (isRunning) => {
            startBtn.disabled = isRunning;
            stopBtn.disabled = !isRunning;
        };

        const redirectFromQr = (decodedText) => {
            try {
                const url = new URL(decodedText, window.location.origin);
                const isLocal = url.origin === window.location.origin ||
                    (['localhost', '127.0.0.1', '::1'].includes(url.hostname) &&
                        ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname));

                if (!isLocal) {
                    setStatus('Blocked external QR URL');
                    return;
                }

                const navigatePath = typeof window.adminLocalNavigatePath === 'function'
                    ? window.adminLocalNavigatePath(url)
                    : `${url.pathname}${url.search}${url.hash}`;

                if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                    window.Livewire.navigate(navigatePath);
                    return;
                }

                window.location.href = navigatePath;
            } catch (e) {
                setStatus('Invalid QR content');
            }
        };

        const handleScanSuccess = (decodedText) => {
            if (state.detectionHandled) return;
            state.detectionHandled = true;
            playDetectionBeep();
            setStatus('QR code detected');
            setResult(decodedText);

            stopScanner().finally(() => {
                updateButtons(false);
                redirectFromQr(decodedText);
            });
        };

        const startScanner = () => {
            if (state.isRunning) return;

            state.detectionHandled = false;
            unlockAudio();
            setStatus('Loading scanner...');
            setResult('-');

            loadQrLibrary().then(() => {
                state.scanner = new window.Html5Qrcode(reader.id);

                return window.Html5Qrcode.getCameras();
            }).then((devices) => {
                if (!devices || devices.length === 0) {
                    setStatus('No camera found');
                    updateButtons(false);
                    return;
                }

                const backCamera = devices.find((device) =>
                    device.label && device.label.toLowerCase().includes('back')
                );
                const cameraId = backCamera ? backCamera.id : devices[0].id;

                return state.scanner.start(
                    cameraId,
                    { fps: 10, qrbox: { width: 280, height: 280 } },
                    handleScanSuccess,
                    () => {}
                ).then(() => {
                    state.isRunning = true;
                    setStatus('Scanning...');
                    updateButtons(true);
                });
            }).catch((error) => {
                setStatus(window.Html5Qrcode ? 'Unable to start scanner' : 'Unable to load scanner');
                setResult(error && error.message ? error.message : String(error));
                updateButtons(false);
            });
        };

        startBtn.addEventListener('click', startScanner);

        stopBtn.addEventListener('click', () => {
            stopScanner().then(() => {
                setStatus('Scanner stopped');
                updateButtons(false);
            });
        });

        const shouldAutoStart = new URLSearchParams(window.location.search).get('start') === '1';
        if (shouldAutoStart) {
            setTimeout(startScanner, 250);
        }
    };

    document.addEventListener('livewire:navigating', () => {
        stopScanner();
    });
    document.addEventListener('livewire:navigated', initQrScanner);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initQrScanner, { once: true });
    } else {
        initQrScanner();
    }
})();

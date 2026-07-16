<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin')</title>

    {{-- Apply saved theme BEFORE paint, to avoid a flash of the wrong colors --}}
    <script>
        (function () {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

            function savedTheme() {
                return localStorage.getItem('theme') || 'system';
            }

            function shouldUseDark(mode) {
                return mode === 'dark' || (mode === 'system' && mediaQuery.matches);
            }

            window.getSavedTheme = function () {
                return savedTheme();
            };

            window.setTheme = function (mode) {
                localStorage.setItem('theme', mode);
                document.documentElement.classList.toggle('dark', shouldUseDark(mode));
                window.dispatchEvent(new CustomEvent('theme-changed', { detail: { theme: mode } }));
            };

            window.setTheme(savedTheme());

            if (mediaQuery.addEventListener) {
                mediaQuery.addEventListener('change', function () {
                    if (savedTheme() === 'system') {
                        window.setTheme('system');
                    }
                });
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        [x-cloak] { display: none !important; }

        /*
        |--------------------------------------------------------------------------
        | Global dark mode safety layer
        |--------------------------------------------------------------------------
        | This makes the dark-mode toggle affect pages that still use light-only
        | Tailwind classes after the GitHub merge. It is intentionally global so
        | every admin page becomes readable in dark mode without editing each Blade.
        */
        html.dark {
            color-scheme: dark;
        }

        html.dark body,
        html.dark main {
            background-color: #0f172a !important;
            color: #e5e7eb !important;
        }

        html.dark .bg-white {
            background-color: #1f2937 !important;
        }

        html.dark .bg-gray-50 {
            background-color: #111827 !important;
        }

        html.dark .bg-gray-100 {
            background-color: #374151 !important;
        }

        html.dark .bg-gray-200 {
            background-color: #4b5563 !important;
        }

        html.dark .border-gray-100,
        html.dark .border-gray-200,
        html.dark .border-gray-300 {
            border-color: #374151 !important;
        }

        html.dark .divide-gray-200 > :not([hidden]) ~ :not([hidden]) {
            border-color: #374151 !important;
        }

        html.dark .text-gray-400 {
            color: #9ca3af !important;
        }

        html.dark .text-gray-500,
        html.dark .text-gray-600 {
            color: #d1d5db !important;
        }

        html.dark .text-gray-700,
        html.dark .text-gray-800,
        html.dark .text-gray-900 {
            color: #f9fafb !important;
        }

        html.dark input,
        html.dark select,
        html.dark textarea {
            background-color: #374151 !important;
            border-color: #4b5563 !important;
            color: #f9fafb !important;
        }

        html.dark input::placeholder,
        html.dark textarea::placeholder {
            color: #9ca3af !important;
        }

        html.dark select option {
            background-color: #1f2937 !important;
            color: #f9fafb !important;
        }

        html.dark table thead,
        html.dark thead.bg-gray-50 {
            background-color: #111827 !important;
            color: #d1d5db !important;
        }

        html.dark tbody tr:hover,
        html.dark .hover\:bg-gray-50:hover {
            background-color: rgba(55, 65, 81, 0.55) !important;
        }

        html.dark .shadow-sm,
        html.dark .shadow,
        html.dark .shadow-md,
        html.dark .shadow-lg,
        html.dark .shadow-xl {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.25) !important;
        }

        html.dark .hover\:bg-gray-100:hover,
        html.dark .hover\:bg-gray-200:hover {
            background-color: #4b5563 !important;
        }

        html.dark .hover\:text-blue-600:hover,
        html.dark .text-blue-600,
        html.dark .text-blue-700 {
            color: #60a5fa !important;
        }

        html.dark .rounded-2xl.bg-white,
        html.dark .rounded-xl.bg-white,
        html.dark .rounded-lg.bg-white {
            background-color: #1f2937 !important;
        }

        html.dark .modal,
        html.dark [role="dialog"] {
            background-color: #1f2937 !important;
            color: #f9fafb !important;
        }

        @media (max-width: 1023px) {
            [data-admin-sidebar] {
                transform: translateX(-100%);
            }

            [data-admin-sidebar][data-open="true"] {
                transform: translateX(0);
            }
        }

        @media (min-width: 1024px) {
            [data-admin-sidebar] {
                transform: translateX(0);
            }
        }

        aside nav a[data-active="true"] {
            background-color: #eff6ff !important;
            color: #1d4ed8 !important;
        }

        aside nav a[data-active="true"] svg {
            color: #2563eb !important;
        }

        html.dark aside nav a[data-active="true"] {
            background-color: rgba(30, 58, 138, 0.3) !important;
            color: #60a5fa !important;
        }

        html.dark aside nav a[data-active="true"] svg {
            color: #60a5fa !important;
        }

    </style>
</head>

@php
    $currentNavGroup = match (true) {
        request()->routeIs('admin.dashboard') => 'dashboard',
        request()->routeIs('admin.locations.*'),
        request()->routeIs('admin.colleges.*'),
        request()->routeIs('admin.offices.*'),
        request()->routeIs('admin.staff.*'),
        request()->routeIs('admin.org-browser') => 'locations',
        request()->routeIs('admin.devices.*') => 'devices',
        request()->routeIs('admin.issuance.*') => 'issuance',
        request()->routeIs('admin.reports.*') => 'reports',
        request()->routeIs('admin.scanner') => 'scanner',
        request()->routeIs('admin.support') => 'support',
        request()->routeIs('admin.users.*') => 'users',
        request()->routeIs('admin.logs.*') => 'logs',
        default => '',
    };
@endphp

<body
    data-current-nav-group="{{ $currentNavGroup }}"
    class="bg-gray-50 text-gray-900 antialiased overflow-x-hidden dark:bg-gray-900 dark:text-gray-100"
>
<div
    x-data="{
        sidebarOpen: false,
        profileOpen: false,
        themeOpen: false,
        theme: window.getSavedTheme ? window.getSavedTheme() : 'system',
        pickTheme(mode) {
            this.theme = mode;
            window.setTheme(mode);
            this.themeOpen = false;
        },
        openSidebar() {
            this.sidebarOpen = true;
            this.profileOpen = false;
            this.themeOpen = false;
        },
        closeSidebar() {
            this.sidebarOpen = false;
        }
    }"
    x-effect="
        document.documentElement.classList.toggle('overflow-hidden', sidebarOpen && window.innerWidth < 1024);
        document.body.classList.toggle('overflow-hidden', sidebarOpen && window.innerWidth < 1024);
    "
    @keydown.escape.window="sidebarOpen = false; profileOpen = false; themeOpen = false"
    class="min-h-screen"
>
    <div
        x-cloak
        x-show="sidebarOpen"
        x-transition.opacity
        data-sidebar-overlay
        class="fixed inset-0 z-40 bg-gray-900/60 backdrop-blur-[1px] lg:hidden"
        @click="closeSidebar()"
    ></div>

    @persist('admin-sidebar')
    <aside
        id="admin-sidebar"
        data-admin-sidebar
        :data-open="sidebarOpen ? 'true' : 'false'"
        :aria-hidden="(!sidebarOpen && window.innerWidth < 1024).toString()"
        class="fixed top-0 left-0 z-50 h-[100dvh] w-[min(20rem,calc(100vw-3rem))] bg-white border-r border-gray-200 transition-transform duration-300 ease-out lg:w-64 flex flex-col dark:bg-gray-800 dark:border-gray-700"
    >
        <div class="h-16 px-4 border-b border-gray-200 flex items-center justify-between dark:border-gray-700">
            <div class="flex items-center gap-3">
                <div class="h-9 w-9 flex items-center justify-center">
                    <img src="data:image/png;base64,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" alt="ICT Unit logo" class="h-full w-full object-contain">
                </div>

                <div>
                    <div class="text-xl font-bold tracking-tight dark:text-white">PMAMS</div>
                    <div class="text-xs text-gray-500 -mt-0.5 dark:text-gray-400"> ICT Equipment Management System</div>
                </div>
            </div>

            <button
                type="button"
                data-sidebar-close
                class="lg:hidden inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                @click="closeSidebar()"
                aria-label="Close sidebar"
            >
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="px-3 py-4 overflow-y-auto h-[calc(100vh-4rem)] flex flex-col">
            <nav class="space-y-1 flex-1" @click="if ($event.target.closest('a')) closeSidebar()">
                <a
                    href="{{ route('admin.dashboard') }}"
                    data-nav-group="dashboard"
                    data-active="{{ $currentNavGroup === 'dashboard' ? 'true' : 'false' }}"
                    @if($currentNavGroup === 'dashboard') aria-current="page" @endif
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 {{ request()->routeIs('admin.dashboard') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12h18M12 3v18"/>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a
                    href="{{ route('admin.locations.index') }}"
                    data-nav-group="locations"
                    data-active="{{ $currentNavGroup === 'locations' ? 'true' : 'false' }}"
                    @if($currentNavGroup === 'locations') aria-current="page" @endif
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.locations.*') || request()->routeIs('admin.offices.*') || request()->routeIs('admin.staff.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 {{ request()->routeIs('admin.locations.*') || request()->routeIs('admin.offices.*') || request()->routeIs('admin.staff.*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"  d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/>

                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"  d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span>Locations</span>
                </a>

                <a
                    href="{{ route('admin.devices.index') }}"
                    data-nav-group="devices"
                    data-active="{{ $currentNavGroup === 'devices' ? 'true' : 'false' }}"
                    @if($currentNavGroup === 'devices') aria-current="page" @endif
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.devices.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 {{ request()->routeIs('admin.devices.*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0v10l-8 4m8-14l-8 4m0 10L4 17V7m8 4L4 7m8 4l8-4"/>
                    </svg>
                    <span>Equipment</span>
                </a>

                <a
                    href="{{ route('admin.issuance.index') }}"
                    data-nav-group="issuance"
                    data-active="{{ $currentNavGroup === 'issuance' ? 'true' : 'false' }}"
                    @if($currentNavGroup === 'issuance') aria-current="page" @endif
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.issuance.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 {{ request()->routeIs('admin.issuance.*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7h8M8 11h8M8 15h5M5 4h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z"/>
                    </svg>
                    <span>Issuance</span>
                </a>

                <a
                    href="{{ route('admin.reports.index') }}"
                    data-nav-group="reports"
                    data-active="{{ $currentNavGroup === 'reports' ? 'true' : 'false' }}"
                    @if($currentNavGroup === 'reports') aria-current="page" @endif
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.reports.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 {{ request()->routeIs('admin.reports.*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 17v-6m4 6V7m4 10v-3M5 19h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>Reports</span>
                </a>

                <a
                    href="{{ route('admin.scanner') }}"
                    data-nav-group="scanner"
                    data-active="{{ $currentNavGroup === 'scanner' ? 'true' : 'false' }}"
                    @if($currentNavGroup === 'scanner') aria-current="page" @endif
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.scanner') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 {{ request()->routeIs('admin.scanner') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7V5a1 1 0 011-1h2m10 0h2a1 1 0 011 1v2m0 10v2a1 1 0 01-1 1h-2M7 20H5a1 1 0 01-1-1v-2m3-5h10"/>
                    </svg>
                    <span>QR Scanner</span>
                </a>



                @if(auth()->user() && (auth()->user()->isAdmin() || auth()->user()->isUnitHead()))
                    <a
                        href="{{ route('admin.users.index') }}"
                        data-nav-group="users"
                        data-active="{{ $currentNavGroup === 'users' ? 'true' : 'false' }}"
                        @if($currentNavGroup === 'users') aria-current="page" @endif
                        class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                        {{ request()->routeIs('admin.users.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                    >
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.users.*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m5-4a4 4 0 100-8 4 4 0 000 8zm6 4a4 4 0 10-8 0v2h8v-2z"/>
                        </svg>
                        <span>Users</span>
                    </a>

                    <a
                        href="{{ route('admin.logs.index') }}"
                        data-nav-group="logs"
                        data-active="{{ $currentNavGroup === 'logs' ? 'true' : 'false' }}"
                        @if($currentNavGroup === 'logs') aria-current="page" @endif
                        class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                        {{ request()->routeIs('admin.logs.*') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                    >
                        <svg class="w-5 h-5 {{ request()->routeIs('admin.logs.*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 7h6m-6 4h6"/>
                        </svg>
                        <span>Activity Logs</span>
                    </a>
                    
                                    <a
                    href="{{ route('admin.support') }}"
                    data-nav-group="support"
                    data-active="{{ $currentNavGroup === 'support' ? 'true' : 'false' }}"
                    @if($currentNavGroup === 'support') aria-current="page" @endif
                    class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition
                    {{ request()->routeIs('admin.support') ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700' }}"
                >
                    <svg class="w-5 h-5 {{ request()->routeIs('admin.support') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M18 10a6 6 0 10-12 0v3a3 3 0 003 3h1m8-6v3a3 3 0 01-3 3h-1m-4 0h4m-4 0a2 2 0 104 0"/>
                    </svg>
                    <span>Support</span>
                </a>
                @endif
            </nav>

            <div class="mt-6 border-t border-gray-200 pt-4 space-y-4 dark:border-gray-700">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button
                        class="group flex min-h-11 w-full items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        <svg class="w-5 h-5 text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H9m4 8H7a2 2 0 01-2-2V6a2 2 0 012-2h6"/>
                        </svg>
                        <span>Logout</span>
                    </button>
                </form>

                <div class="rounded-xl bg-gray-50 border border-gray-200 px-3 py-3 dark:bg-gray-900/40 dark:border-gray-700">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 truncate dark:text-white">
                                {{ auth()->user()->name ?? 'User' }}
                            </div>

                            <div class="text-xs text-gray-500 truncate dark:text-gray-400">
                                {{ auth()->user()->email ?? '' }}
                            </div>
                        </div>

                        @if(auth()->user())
                            <span class="shrink-0 inline-flex rounded-full {{ (auth()->user()->isAdmin() || auth()->user()->isUnitHead()) ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400' }} px-2 py-0.5 text-[11px] font-medium">
                                {{ auth()->user()->roleLabel() }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </aside>
    @endpersist

    <div class="lg:ml-64 min-h-screen">
        <header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-gray-200 dark:bg-gray-800/90 dark:border-gray-700">
            <div class="h-16 px-3 sm:px-6 flex items-center justify-between">
                <div class="flex items-center gap-3 min-w-0">
                    <button
                        type="button"
                        data-sidebar-open
                        class="lg:hidden inline-flex h-11 w-11 items-center justify-center rounded-xl text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                        @click="openSidebar()"
                        aria-controls="admin-sidebar"
                        :aria-expanded="sidebarOpen.toString()"
                        aria-label="Open sidebar"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>

                    <h1 class="truncate text-lg sm:text-xl font-semibold text-gray-900 dark:text-white">
                        @yield('page_title', 'Admin')
                        @if(trim($__env->yieldContent('breadcrumbs')))
                            <nav class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                              @yield('breadcrumbs')
                            </nav>
                    @endif
                    </h1>
                </div>

                <div class="flex items-center gap-3 shrink-0">
                    {{-- Theme switcher: Light / Dark / System --}}
                    <div class="relative">
                        <button
                            type="button"
                            class="flex items-center justify-center h-11 w-11 rounded-xl text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700"
                            @click="themeOpen = !themeOpen; profileOpen = false"
                            title="Change theme"
                            :aria-expanded="themeOpen.toString()"
                        >
                            <svg x-show="theme === 'light'" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="4"/>
                                <path stroke-linecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                            </svg>
                            <svg x-show="theme === 'dark'" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                            </svg>
                            <svg x-show="theme === 'system'" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <rect x="2" y="3" width="20" height="14" rx="2"/>
                                <path stroke-linecap="round" d="M8 21h8M12 17v4"/>
                            </svg>
                        </button>

                        <div
                            x-cloak
                            x-show="themeOpen"
                            x-transition
                            @click.away="themeOpen = false"
                            class="absolute right-0 mt-2 w-44 rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 overflow-hidden dark:bg-gray-800 dark:ring-gray-700"
                        >
                            <button type="button" @click="pickTheme('light')" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                :class="theme === 'light' ? 'text-blue-600 font-medium dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="4"/>
                                    <path stroke-linecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
                                </svg>
                                Light
                            </button>
                            <button type="button" @click="pickTheme('dark')" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                :class="theme === 'dark' ? 'text-blue-600 font-medium dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
                                </svg>
                                Dark
                            </button>
                            <button type="button" @click="pickTheme('system')" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-700"
                                :class="theme === 'system' ? 'text-blue-600 font-medium dark:text-blue-400' : 'text-gray-700 dark:text-gray-300'">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                                    <path stroke-linecap="round" d="M8 21h8M12 17v4"/>
                                </svg>
                                System
                            </button>
                        </div>
                    </div>

                    {{-- Profile menu --}}
                    <div class="relative">
                        <button
                            type="button"
                            class="flex min-h-11 min-w-11 items-center gap-3 rounded-xl p-1.5 hover:bg-gray-100 dark:hover:bg-gray-700"
                            @click="profileOpen = !profileOpen; themeOpen = false"
                            :aria-expanded="profileOpen.toString()"
                        >
                            <div class="hidden text-right sm:block">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ auth()->user()->name ?? 'Admin' }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ auth()->user()->email ?? 'admin@example.com' }}
                                </div>
                            </div>

                            <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-orange-100 shadow-sm ring-2 ring-white dark:bg-orange-900/40 dark:ring-gray-800">
                                <svg class="h-6 w-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="8" r="3.2" stroke-width="1.8"/>
                                    <path stroke-linecap="round" stroke-width="1.8" d="M5.5 19c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"/>
                                </svg>
                            </div>
                        </button>

                        <div
                            x-cloak
                            x-show="profileOpen"
                            x-transition
                            @click.away="profileOpen = false"
                            class="absolute right-0 mt-2 w-[calc(100vw-1.5rem)] max-w-72 overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700"
                        >
                            <div class="border-b border-gray-100 px-4 py-4 dark:border-gray-700">
                                <div class="font-semibold text-gray-900 dark:text-white">
                                    {{ auth()->user()->name ?? 'Admin' }}
                                </div>
                                <div class="truncate text-sm text-gray-500 dark:text-gray-400">
                                    {{ auth()->user()->email ?? 'admin@example.com' }}
                                </div>
                            </div>

                            <div class="py-2">
                                <a href="{{ route('admin.dashboard') }}" @click="profileOpen = false" class="block min-h-11 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Dashboard
                                </a>
                                <a href="{{ route('admin.devices.index') }}" @click="profileOpen = false" class="block min-h-11 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Equipment
                                </a>
                                <a href="{{ route('admin.reports.index') }}" @click="profileOpen = false" class="block min-h-11 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Reports
                                </a>
                                <a href="{{ route('admin.reports.checklist') }}" @click="profileOpen = false" class="block min-h-11 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Checklist
                                </a>
                                <a href="{{ route('admin.change-password') }}" @click="profileOpen = false" class="block min-h-11 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Change Password
                                </a>
                                <a href="{{ route('admin.scanner') }}" @click="profileOpen = false" class="block min-h-11 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    QR Scanner
                                </a>
                                <a href="{{ route('admin.support') }}" @click="profileOpen = false" class="block min-h-11 px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700">
                                    Support
                                </a>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="block min-h-11 w-full px-4 py-3 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Sign out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="p-3 sm:p-6" data-current-nav-group="{{ $currentNavGroup }}">
            @if (session('success'))
                <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-green-700 shadow-sm dark:border-green-800 dark:bg-green-900/30 dark:text-green-400">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-red-700 shadow-sm dark:border-red-800 dark:bg-red-900/30 dark:text-red-400">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="px-3 pb-5 text-center text-xs text-gray-500 dark:text-gray-400 sm:px-6">
            PMAMS Version 3.0 &copy; 2026. All rights reserved.
        </footer>
    </div>
</div>

@stack('scripts')
@livewireScripts
</body>
</html>

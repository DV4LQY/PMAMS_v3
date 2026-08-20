@extends('admin.layouts.app')

@section('title', 'QR Scanner')
@section('page_title', 'QR Scanner')

@section('breadcrumb')
    <span>Scanner</span>
@endsection

@section('content')
<div class="space-y-4">
    <div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Use your device camera to scan a QR code. Valid HTTP/HTTPS links open automatically after detection.
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">The scanner uses the rear camera when available.</p>
            </div>
            <button id="start-scanner"
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 8.5A2.5 2.5 0 0 1 5.5 6h2l1.2-1.5h4.6L14.5 6h4A2.5 2.5 0 0 1 21 8.5v8A2.5 2.5 0 0 1 18.5 19h-13A2.5 2.5 0 0 1 3 16.5v-8Z" />
                    <circle cx="12" cy="12.5" r="3.25" />
                </svg>
                Start Scanner
            </button>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-5 shadow-sm dark:bg-gray-800">
        <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">Scan Result</h2>
        <div class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <div class="text-gray-500 dark:text-gray-400">Status</div>
                <div id="scan-status" class="font-medium text-gray-900 dark:text-white" aria-live="polite">Idle</div>
            </div>
            <div>
                <div class="text-gray-500 dark:text-gray-400">Scanned Value</div>
                <button id="scan-result"
                        type="button"
                        class="block max-w-full break-all rounded-lg border border-transparent bg-transparent p-0 text-left font-medium text-blue-700 underline decoration-blue-300 underline-offset-2 transition hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:cursor-not-allowed disabled:no-underline disabled:opacity-60 dark:text-blue-300 dark:decoration-blue-700 dark:hover:text-blue-200"
                        aria-live="polite"
                        aria-label="Scanned value. No safe HTTP/HTTPS link is ready to open."
                        title="No safe HTTP/HTTPS link is ready to open"
                        disabled>-</button>
            </div>
        </div>
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">Tip: point the camera steadily at the QR code and wait for it to focus.</p>
    </div>
</div>

{{-- Camera scanner modal. The frame and animated scan line provide a clear augmented scanning target. --}}
<div id="qr-scanner-modal"
     class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
     role="dialog"
     aria-modal="true"
     aria-labelledby="qr-scanner-modal-title">
    <div class="w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <div>
                <h2 id="qr-scanner-modal-title" class="font-semibold text-gray-900 dark:text-white">Scan equipment QR code</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Align the code inside the frame.</p>
            </div>
            <button id="close-scanner" type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-2xl leading-none text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white" aria-label="Close scanner">&times;</button>
        </div>

        <div class="p-5">
            <div class="relative mx-auto w-full max-w-lg overflow-hidden rounded-2xl bg-black shadow-inner">
                <div id="reader" class="min-h-[300px] w-full overflow-hidden sm:min-h-[420px]"></div>
                <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                    <div class="qr-scan-frame relative h-[min(72vw,300px)] w-[min(72vw,300px)] max-h-[68%] max-w-[68%] rounded-2xl border-2 border-blue-400/90 shadow-[0_0_0_9999px_rgba(2,6,23,0.28),0_0_24px_rgba(96,165,250,0.6)]">
                        <span class="qr-scan-corner qr-scan-corner-tl"></span>
                        <span class="qr-scan-corner qr-scan-corner-tr"></span>
                        <span class="qr-scan-corner qr-scan-corner-bl"></span>
                        <span class="qr-scan-corner qr-scan-corner-br"></span>
                        <span class="qr-scan-line"></span>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <div class="text-sm text-gray-600 dark:text-gray-300" aria-live="polite">
                    <span id="scanner-modal-status">Point the camera at a QR code.</span>
                </div>
                <button id="stop-scanner" type="button" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-gray-700 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-400 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-600 dark:hover:bg-gray-500" disabled>
                    Stop Scanner
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .qr-scan-corner { position: absolute; width: 2rem; height: 2rem; border-color: #bfdbfe; }
    .qr-scan-corner-tl { top: -2px; left: -2px; border-top: 4px solid; border-left: 4px solid; border-top-left-radius: .75rem; }
    .qr-scan-corner-tr { top: -2px; right: -2px; border-top: 4px solid; border-right: 4px solid; border-top-right-radius: .75rem; }
    .qr-scan-corner-bl { bottom: -2px; left: -2px; border-bottom: 4px solid; border-left: 4px solid; border-bottom-left-radius: .75rem; }
    .qr-scan-corner-br { right: -2px; bottom: -2px; border-right: 4px solid; border-bottom: 4px solid; border-bottom-right-radius: .75rem; }
    .qr-scan-line { position: absolute; left: 8%; right: 8%; top: 12%; height: 2px; background: #60a5fa; box-shadow: 0 0 12px #60a5fa; animation: qr-scan-line 2s ease-in-out infinite; }
    @keyframes qr-scan-line { 0%, 100% { transform: translateY(0); opacity: .65; } 50% { transform: translateY(235px); opacity: 1; } }
    @media (prefers-reduced-motion: reduce) { .qr-scan-line { animation: none; } }
</style>

@endsection

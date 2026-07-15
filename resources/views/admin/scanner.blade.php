@extends('admin.layouts.app')

@section('title', 'QR Scanner')
@section('page_title', 'QR Scanner')

@section('breadcrumb')
    <span>Scanner</span>
@endsection

@section('content')
<div class="space-y-4">
    <div class="bg-white rounded shadow-sm p-6">
        <h1 class="text-2xl font-semibold mb-2">QR Scanner</h1>
        <p class="text-sm text-gray-600 mb-4">
            Use your camera to scan an equipment QR code. When a valid code is detected,
            the system will open the corresponding equipment page automatically.
        </p>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div id="reader" class="w-full max-w-2xl border rounded bg-black/5 overflow-hidden"></div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button id="start-scanner"
                            type="button"
                            class="px-4 py-2 rounded bg-blue-600 text-white">
                        Start Scanner
                    </button>

                    <button id="stop-scanner"
                            type="button"
                            class="px-4 py-2 rounded bg-gray-900 text-white"
                            disabled>
                        Stop Scanner
                    </button>
                </div>
            </div>

            <div class="bg-gray-50 rounded border p-4">
                <h2 class="font-semibold mb-3">Scan Result</h2>

                <div class="space-y-3 text-sm">
                    <div>
                        <div class="text-gray-500">Status</div>
                        <div id="scan-status" class="font-medium">Idle</div>
                    </div>

                    <div>
                        <div class="text-gray-500">Scanned Value</div>
                        <div id="scan-result" class="font-medium break-all">-</div>
                    </div>

                    <div class="text-gray-600">
                        Tip: point the camera steadily at the QR code and wait for it to focus.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

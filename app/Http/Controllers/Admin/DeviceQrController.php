<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DeviceQrController extends Controller
{
    /**
     * Display all equipment QR codes in a printable layout.
     */
    public function index()
    {
        $devices = Device::orderBy('property_number')->get();

        $qrCodes = $devices->mapWithKeys(function ($device) {
            $qrPayload = route('admin.devices.show', $device)
                . '?property_number=' . urlencode($device->property_number ?? '');

            return [
                $device->id => QrCode::size(180)->generate($qrPayload),
            ];
        });

        return view('admin.devices.generate-qr', compact('devices', 'qrCodes'));
    }
}

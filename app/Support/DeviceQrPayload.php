<?php

namespace App\Support;

use App\Models\Device;

/**
 * Builds the value encoded in an equipment QR code.
 *
 * The QR value deliberately contains the application path only (not the
 * scheme, host, or port).  The PMAMS scanner resolves that path against the
 * environment it is currently running in, so a code generated locally also
 * works when the scanner is opened on the hosted installation, and vice
 * versa.  The deployment base path is retained when Laravel is installed in
 * a subdirectory (for example /pmams/public).
 */
final class DeviceQrPayload
{
    private function __construct()
    {
    }

    public static function for(Device $device): string
    {
        $absoluteUrl = route('admin.devices.show', $device);
        $path = parse_url($absoluteUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = '/admin/devices/' . $device->getRouteKey();
        }

        $query = http_build_query(
            ['property_number' => $device->property_number ?? ''],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        return $path . ($query !== '' ? '?' . $query : '');
    }
}

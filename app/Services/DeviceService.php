<?php

namespace App\Services;

use App\Models\DeviceType;
use App\Models\Location;
use App\Models\Office;

class DeviceService
{
    /**
     * Remove computer-only fields when the device is not a Desktop or Laptop.
     * Call this before any Device::create() or $device->update().
     */
    public function cleanByType(array $data): array
    {
        $type     = DeviceType::find($data['device_type_id'] ?? null);
        $typeName = strtolower($type?->name ?? '');

        $isComputer = in_array($typeName, ['desktop', 'laptop']);
        $isNetworkDevice = $typeName === 'network device';

        if (! $isComputer) {
            if (! $isNetworkDevice) {
                $data['mac_address'] = null;
            }

            if (! $isNetworkDevice) {
                $data['network_device_type'] = null;
                $data['location_deployed'] = null;
                $data['location_deployed_id'] = null;
                $data['office_deployed_id'] = null;
            } else {
                $location = null;
                $office = null;
                if (! empty($data['office_deployed_id'])) {
                    $office = Office::with('location')->find((int) $data['office_deployed_id']);
                    $location = $office?->location;
                }
                if (! empty($data['location_deployed_id'])) {
                    $location ??= Location::find((int) $data['location_deployed_id']);
                }
                if (! $location && filled($data['location_deployed'] ?? null)) {
                    $value = trim((string) $data['location_deployed']);
                    $officeName = trim((string) preg_replace('/\s+-\s+.*$/', '', $value));
                    $office = Office::with('location')
                        ->whereRaw('LOWER(name) = ?', [strtolower($officeName)])
                        ->first();
                    $location = $office?->location ?: Location::whereRaw('LOWER(name) = ?', [strtolower($value)])
                        ->orWhereRaw('LOWER(code) = ?', [strtolower($value)])
                        ->first();
                }
                if ($location) {
                    $data['location_deployed_id'] = $location->id;
                    $data['office_deployed_id'] = $office?->id;
                    $locationLabel = trim($location->name . ($location->code ? ' (' . $location->code . ')' : ''));
                    $data['location_deployed'] = $office ? trim($office->name . ' - ' . $locationLabel) : $locationLabel;
                } elseif (blank($data['location_deployed'] ?? null)) {
                    $data['location_deployed_id'] = null;
                    $data['office_deployed_id'] = null;
                } else {
                    $data['location_deployed_id'] = null;
                    $data['office_deployed_id'] = null;
                }
            }

            $data['specs'] = collect($data['specs'] ?? [])
                ->except(['os', 'memory', 'storage', 'form_factor'])
                ->toArray();

            if (empty($data['specs'])) {
                $data['specs'] = null;
            }
        }

        if ($isComputer) {
            $data['specs'] = collect($data['specs'] ?? [])
                ->filter(fn ($value) => filled($value))
                ->toArray();

            if (empty($data['specs'])) {
                $data['specs'] = null;
            }
        }

        return $data;
    }

    /**
     * Ensure the allowed device types exist in the database and return them
     * sorted in the display order expected by Add / Edit dropdowns.
     */
    public function allowedTypes(): \Illuminate\Support\Collection
    {
        $allowedTypes = [
            'Desktop',
            'Laptop',
            'Printer',
            'Monitor',
            'UPS',
            'AVR',
            'Scanner',
            'Network Device',
            'Other',
        ];

        foreach ($allowedTypes as $typeName) {
            DeviceType::firstOrCreate(
                ['name' => $typeName],
                ['slug' => strtolower(str_replace(' ', '-', $typeName))]
            );
        }

        return DeviceType::whereIn('name', $allowedTypes)
            ->get()
            ->sortBy(function ($type) use ($allowedTypes) {
                return array_search($type->name, $allowedTypes);
            })
            ->values();
    }
}

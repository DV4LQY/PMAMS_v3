<?php

namespace App\Services;

use App\Models\DeviceType;

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

        if (! $isComputer) {
            $data['mac_address'] = null;

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
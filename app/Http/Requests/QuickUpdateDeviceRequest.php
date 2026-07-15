<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuickUpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $deviceId = $this->route('device')?->id;

        return [
            'device_type_id' => ['nullable', 'exists:device_types,id'],

            'property_number' => [
                'required',
                'string',
                'max:255',
                'unique:devices,property_number,' . $deviceId,
            ],

            'serial_number' => ['nullable', 'string', 'max:255'],
            'brand'         => ['nullable', 'string', 'max:255'],
            'model'         => ['nullable', 'string', 'max:255'],
            'mac_address'   => ['nullable', 'string', 'max:255'],

            'unit_price'    => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'date_acquired' => ['nullable', 'date'],

            'condition' => ['nullable', 'in:serviceable,unserviceable,condemned'],
            'status'    => ['nullable', 'in:available,issued,repair,retired'],

            'last_maintenance_date' => ['nullable', 'date'],
            'maintenance_remarks'   => ['nullable', 'string'],
            'notes'                 => ['nullable', 'string'],

            'specs'              => ['nullable', 'array'],
            'specs.os'           => ['nullable', 'string', 'max:255'],
            'specs.os_version'   => ['nullable', 'string', 'max:255'],
            'specs.os_license'   => ['nullable', 'string', 'max:255'],
            'specs.memory'       => ['nullable', 'string', 'max:255'],
            'specs.storage'      => ['nullable', 'string', 'max:255'],
            'specs.form_factor'  => ['nullable', 'string', 'max:255'],
            'specs.office_version' => ['nullable', 'string', 'max:255'],
            'specs.office_license' => ['nullable', 'string', 'max:255'],
        ];
    }
}

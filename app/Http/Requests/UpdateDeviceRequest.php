<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\DeviceType;

class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_type_id' => ['required', 'exists:device_types,id'],

            'property_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:' . StoreDeviceRequest::PROPERTY_NUMBER_REGEX,
                'required_without:part_of_property_number',
                'unique:devices,property_number,' . $this->route('device')->id,
            ],

            'part_of_property_number' => [
                'nullable',
                'string',
                'max:50',
                'regex:' . StoreDeviceRequest::PROPERTY_NUMBER_REGEX,
                'required_without:property_number',
                Rule::exists('devices', 'property_number')->where(function ($query) {
                    $query
                        ->where('id', '!=', $this->route('device')->id)
                        ->whereNull('part_of_property_number');
                }),
            ],

            'serial_number' => [
                'nullable',
                'string',
                'max:100',
                'regex:' . StoreDeviceRequest::SERIAL_NUMBER_REGEX,
            ],

            'computer_name' => ['nullable', 'string', 'max:100'],

            'brand'       => ['nullable', 'string', 'max:100', 'regex:' . StoreDeviceRequest::BRAND_MODEL_REGEX],
            'model'       => ['nullable', 'string', 'max:100', 'regex:' . StoreDeviceRequest::BRAND_MODEL_REGEX],
            'mac_address' => ['nullable', 'string', 'regex:' . StoreDeviceRequest::MAC_ADDRESS_REGEX],

            'unit_price'    => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'date_acquired' => ['nullable', 'date', 'before_or_equal:today'],

            /*
            |--------------------------------------------------------------------------
            | Keep status nullable
            |--------------------------------------------------------------------------
            */
            'status'    => ['nullable', 'in:available,issued,repair,retired'],
            'condition' => ['nullable', 'in:serviceable,unserviceable,condemned'],

            'notes' => ['nullable', 'string', 'max:2000'],
            'equipment_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],

            'last_maintenance_date' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_remarks'   => ['nullable', 'string', 'max:1000'],

            /*
            |--------------------------------------------------------------------------
            | Device Specifications (JSON)
            |--------------------------------------------------------------------------
            */
            'specs'             => ['nullable', 'array'],
            'specs.memory'      => ['nullable', 'string', 'max:255'],
            'specs.storage'     => ['nullable', 'string', 'max:255'],
            'specs.form_factor' => ['nullable', 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | OS & MS Office (separate columns, Desktop/Laptop only)
            |--------------------------------------------------------------------------
            */
            'os_version'        => ['nullable', 'string', 'in:Windows 7,Windows 8,Windows 10,Windows 11,Windows Server,Linux'],
            'os_license'        => ['nullable', 'string', 'in:Cracked,OEM Licensed,Open Source'],
            'ms_office_version' => ['nullable', 'string', 'in:Office 2007,Office 2010,Office 2013,Office 2016,Office 2019,Office 2021,Microsoft 365'],
            'ms_office_license' => ['nullable', 'string', 'in:Cracked,OEM Licensed'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!filled($this->input('part_of_property_number'))) {
                return;
            }

            $typeName = strtolower((string) DeviceType::whereKey($this->input('device_type_id'))->value('name'));
            if (!in_array($typeName, ['printer', 'monitor', 'avr', 'ups', 'scanner', 'other'], true)) {
                $validator->errors()->add(
                    'part_of_property_number',
                    'Part of property number is available only for Printer, Monitor, AVR, UPS, Scanner, or Other equipment.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'property_number.regex' => 'Property number may only contain letters, numbers, hyphens, and slashes.',
            'property_number.required_without' => 'Enter a property number, or select a parent property number for this linked equipment.',
            'part_of_property_number.exists' => 'The selected parent property number does not exist.',
            'part_of_property_number.required_without' => 'Select a parent property number when the equipment property number is blank.',
            'part_of_property_number.regex' => 'Part of property number may only contain letters, numbers, hyphens, and slashes.',
            'serial_number.regex'   => 'Serial number may only contain letters, numbers, and hyphens.',
            'brand.regex'           => 'Brand may only contain letters and numbers.',
            'model.regex'           => 'Model may only contain letters and numbers.',
            'mac_address.regex'     => 'Enter one or more MAC addresses in colon format, separated by semicolons (for example, 90:DE:80:08:8D:5C; 00:DE:80:08:8D:5C).',

            'unit_price.numeric' => 'The unit price must be a valid number.',
            'unit_price.min'     => 'The unit price cannot be negative.',
            'unit_price.max'     => 'The unit price is too large. Please enter a valid amount, for example 13000 or 25500.',

            'date_acquired.before_or_equal'        => 'Date acquired cannot be in the future.',
            'last_maintenance_date.before_or_equal' => 'Last maintenance date cannot be in the future.',

            'condition.in' => 'The condition must be serviceable, unserviceable, or condemned.',

            'serial_number.max' => 'The serial number must not exceed 100 characters.',
            'equipment_photo.mimes' => 'The equipment photo must be a JPG, PNG, WEBP, HEIC, or HEIF file.',
            'equipment_photo.max' => 'The equipment photo must not be larger than 10 MB.',

            'specs.memory.max'      => 'The memory field must not exceed 255 characters.',
            'specs.storage.max'     => 'The storage field must not exceed 255 characters.',
            'specs.form_factor.max' => 'The form factor field must not exceed 255 characters.',

            'os_version.in'        => 'Invalid OS version selected.',
            'os_license.in'        => 'OS license must be Cracked, OEM Licensed, or Open Source.',
            'ms_office_version.in' => 'Invalid MS Office version selected.',
            'ms_office_license.in' => 'MS Office license must be either Cracked or OEM Licensed.',
        ];
      }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceRequest extends FormRequest
{
    /**
     * Letters, numbers, hyphens, and slashes — covers formats like
     * "PN-2026-0001" or "2026/0045".
     */
    public const PROPERTY_NUMBER_REGEX = '/^[A-Za-z0-9][A-Za-z0-9\-\/]*$/';

    public const SERIAL_NUMBER_REGEX = '/^[A-Za-z0-9\-]+$/';

    public const BRAND_MODEL_REGEX = '/^[A-Za-zÑñ0-9][A-Za-zÑñ0-9.\-\/\s]*$/u';

    public const MAC_ADDRESS_REGEX = '/^[0-9A-Fa-f]{2}(:[0-9A-Fa-f]{2}){5}$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_type_id' => ['required', 'exists:device_types,id'],

            'property_number' => [
                'required',
                'string',
                'max:50',
                'regex:' . self::PROPERTY_NUMBER_REGEX,
                'unique:devices,property_number',
            ],

            'serial_number' => [
                'nullable',
                'string',
                'max:100',
                'regex:' . self::SERIAL_NUMBER_REGEX,
            ],

            'computer_name' => ['nullable', 'string', 'max:100'],

            'brand' => ['nullable', 'string', 'max:100', 'regex:' . self::BRAND_MODEL_REGEX],
            'model' => ['nullable', 'string', 'max:100', 'regex:' . self::BRAND_MODEL_REGEX],
            'mac_address' => ['nullable', 'string', 'regex:' . self::MAC_ADDRESS_REGEX],

            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'date_acquired' => ['nullable', 'date', 'before_or_equal:today'],

            /*
            |--------------------------------------------------------------------------
            | Device Availability Status
            |--------------------------------------------------------------------------
            | Newly added devices should always be available.
            | This is handled in DeviceController@store:
            | $data['status'] = 'available';
            */
            'status' => ['nullable', 'in:available,issued,repair,retired'],

            /*
            |--------------------------------------------------------------------------
            | Device Condition
            |--------------------------------------------------------------------------
            | Status = availability: available, issued, repair, retired
            | Condition = physical condition: serviceable, unserviceable
            */
            'condition' => ['nullable', 'in:serviceable,unserviceable,condemned'],

            'notes' => ['nullable', 'string', 'max:2000'],
            'equipment_photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:10240'],

            'last_maintenance_date' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_remarks' => ['nullable', 'string', 'max:1000'],

            /*
            |--------------------------------------------------------------------------
            | Device Specifications (JSON)
            |--------------------------------------------------------------------------
            | These are mainly for Desktop and Laptop.
            */
            'specs' => ['nullable', 'array'],
            'specs.memory'      => ['nullable', 'string', 'max:255'],
            'specs.storage'     => ['nullable', 'string', 'max:255'],
            'specs.form_factor' => ['nullable', 'string', 'max:255'],

            /*
            |--------------------------------------------------------------------------
            | OS & MS Office (separate columns, Desktop/Laptop only)
            |--------------------------------------------------------------------------
            */
            'os_version'       => ['nullable', 'string', 'in:Windows 7,Windows 8,Windows 10,Windows 11'],
            'os_license'       => ['nullable', 'string', 'in:Cracked,OEM Licensed'],
            'ms_office_version' => ['nullable', 'string', 'in:Office 2007,Office 2010,Office 2013,Office 2016,Office 2019,Office 2021,Microsoft 365'],
            'ms_office_license' => ['nullable', 'string', 'in:Cracked,OEM Licensed'],
        ];
    }

    public function messages(): array
    {
        return [
            'property_number.regex' => 'Property number may only contain letters, numbers, hyphens, and slashes.',
            'serial_number.regex'   => 'Serial number may only contain letters, numbers, and hyphens.',
            'brand.regex'           => 'Brand may only contain letters and numbers.',
            'model.regex'           => 'Model may only contain letters and numbers.',
            'mac_address.regex'     => 'Please enter a valid MAC address, e.g. 00:1A:2B:3C:4D:5E.',

            'unit_price.numeric' => 'The unit price must be a valid number.',
            'unit_price.min'     => 'The unit price cannot be negative.',
            'unit_price.max'     => 'The unit price is too large. Please enter a valid amount.',

            'date_acquired.before_or_equal'       => 'Date acquired cannot be in the future.',
            'last_maintenance_date.before_or_equal' => 'Last maintenance date cannot be in the future.',

            'condition.in' => 'The condition must be serviceable, unserviceable, or condemned.',

            'serial_number.max' => 'The serial number must not exceed 100 characters.',
            'equipment_photo.mimes' => 'The equipment photo must be a JPG, PNG, WEBP, HEIC, or HEIF file.',
            'equipment_photo.max' => 'The equipment photo must not be larger than 10 MB.',

            'specs.memory.max'      => 'The memory field must not exceed 255 characters.',
            'specs.storage.max'     => 'The storage field must not exceed 255 characters.',
            'specs.form_factor.max' => 'The form factor field must not exceed 255 characters.',

            'os_version.in'        => 'Invalid OS version selected.',
            'os_license.in'        => 'OS license must be either Cracked or OEM Licensed.',
            'ms_office_version.in' => 'Invalid MS Office version selected.',
            'ms_office_license.in' => 'MS Office license must be either Cracked or OEM Licensed.',
        ];
    }
}

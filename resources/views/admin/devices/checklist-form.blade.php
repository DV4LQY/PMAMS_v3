@extends('admin.layouts.app')

@section('title', 'Maintenance Checklist')
@section('page_title', 'Maintenance Checklist')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600">Dashboard</a>
    <span>/</span>
    <a href="{{ route('admin.devices.index') }}" class="hover:text-blue-600">Equipment</a>
    <span>/</span>
    <a href="{{ route('admin.devices.show', $device) }}" class="hover:text-blue-600">Equipment Details</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Maintenance Checklist</span>
@endsection

@section('content')
@php
    $assignment = $device->currentAssignment;
    $staff = $assignment?->staff;
    $office = $staff?->office;
    $college = $office?->college;
@endphp

<div class="space-y-6">
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Preventive Maintenance Checklist
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose OK or Not OK for each hardware item. For software, choose ✓ or -.
                </p>
            </div>

            <a
                href="{{ route('admin.devices.show', $device) }}"
                class="inline-flex rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                Back to Equipment
            </a>
        </div>
    </div>

    <form
        method="POST"
        action="{{ route('admin.devices.checklist.save', $device) }}"
        target="_self"
        class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
    >
        @csrf

        @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/30 dark:text-red-400">
                <div class="font-semibold">Please check the checklist form.</div>
                <ul class="mt-1 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Date Checked</label>
                <input
                    type="date"
                    name="date_checked"
                    value="{{ old('date_checked', $defaultDate ?? now()->toDateString()) }}"
                    max="{{ now()->toDateString() }}"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Office / Unit</label>
                <input
                    type="text"
                    value="{{ $office?->name ?? 'Unassigned' }}"
                    readonly
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300"
                >
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                <input
                    type="text"
                    value="{{ $college?->name ?? '-' }}"
                    readonly
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300"
                >
            </div>
        </div>

        <div class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-900/40">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Equipment Type</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $device->type?->name ?? '-' }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Property Number</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $device->property_number }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Serial Number</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ $device->serial_number ?: '-' }}</div>
                </div>

                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Checked By</div>
                    <div class="font-semibold text-gray-900 dark:text-white">{{ auth()->user()->name ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-gray-900/40">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Section</th>
                        <th class="px-4 py-3 font-semibold text-gray-700 dark:text-gray-300">Checklist Item</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">OK</th>
                        <th class="px-4 py-3 text-center font-semibold text-gray-700 dark:text-gray-300">Not OK</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
                    @foreach($checklistItems as $key => $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $item['group'] ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $item['label'] ?? '-' }}</td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="hardware[{{ $key }}]"
                                        value="OK"
                                        class="peer sr-only"
                                        @checked(old("hardware.$key") === 'OK')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:text-green-700 dark:peer-checked:bg-green-900/30 dark:peer-checked:text-green-400">
                                        ✓
                                    </span>
                                </label>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="hardware[{{ $key }}]"
                                        value="Not OK"
                                        class="peer sr-only"
                                        @checked(old("hardware.$key") === 'Not OK')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-red-600 peer-checked:bg-red-50 peer-checked:text-red-700 dark:peer-checked:bg-red-900/30 dark:peer-checked:text-red-400">
                                        ✓
                                    </span>
                                </label>
                            </td>
                        </tr>
                    @endforeach

                    @foreach($softwareItems as $key => $label)
                        <tr>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">Software</td>
                            <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $label }}</td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="software[{{ $key }}]"
                                        value="check"
                                        class="peer sr-only"
                                        @checked(old("software.$key") === 'check')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-green-600 peer-checked:bg-green-50 peer-checked:text-green-700 dark:peer-checked:bg-green-900/30 dark:peer-checked:text-green-400">
                                        ✓
                                    </span>
                                </label>
                            </td>

                            <td class="px-4 py-3 text-center">
                                <label class="inline-flex cursor-pointer items-center justify-center">
                                    <input
                                        type="radio"
                                        name="software[{{ $key }}]"
                                        value="dash"
                                        class="peer sr-only"
                                        @checked(old("software.$key") === 'dash')
                                    >
                                    <span class="flex h-8 w-8 items-center justify-center rounded border-2 border-gray-400 text-lg font-bold text-transparent dark:border-gray-500 peer-checked:border-gray-600 peer-checked:bg-gray-50 peer-checked:text-gray-700 dark:peer-checked:bg-gray-700 dark:peer-checked:text-gray-300">
                                        -
                                    </span>
                                </label>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-5 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Remarks</label>
                <textarea
                    name="remarks"
                    rows="4"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Optional remarks"
                >{{ old('remarks') }}</textarea>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Corrective Action</label>
                <textarea
                    name="corrective_action"
                    rows="4"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    placeholder="Optional corrective action"
                >{{ old('corrective_action') }}</textarea>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-2">
            <a
                href="{{ route('admin.devices.show', $device) }}"
                class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
            >
                Cancel
            </a>

            <button
                type="submit"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
            >
                Save Checklist
            </button>
        </div>
    </form>
</div>
@endsection

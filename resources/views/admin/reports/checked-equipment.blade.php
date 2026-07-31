@extends('admin.layouts.app')

@section('title', 'Checked Equipment Report')
@section('page_title', 'Checked Equipment Report')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">
        Dashboard
    </a>

    <span class="dark:text-gray-500">/</span>

    <a href="{{ route('admin.reports.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">
        Reports
    </a>

    <span class="dark:text-gray-500">/</span>

    <span class="font-medium text-gray-800 dark:text-gray-200">
        Checked Equipment
    </span>
@endsection


@section('content')

@php
    // Checklist history deletion follows the Maintenance Checklist role
    // permission. Super Admins remain unrestricted.
    $canDeleteCheckedHistory = auth()->user()?->isSuperAdmin()
        || auth()->user()?->canAction('checklist', 'delete');
    $canBulkDeleteCheckedHistory = auth()->user()?->isSuperAdmin();
@endphp

<div class="space-y-5">

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">

        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
                Checked Equipment Report
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Equipment marked checked through the maintenance checklist.
            </p>
        </div>


        <a href="{{ route('admin.reports.index') }}"
           class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200">
            Back to Reports
        </a>

    </div>


    {{-- Summary --}}

    <div class="grid grid-cols-1 gap-4 md:grid-cols-1">

        @forelse(($checkerSummary ?? $adminSummary)->take(3) as $summary)

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">

                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $summary->checkedBy?->name ?? 'Unknown User' }}
                </div>

                <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-gray-100">
                    {{ number_format($summary->total) }}
                </div>

                <div class="mt-1 text-xs uppercase text-gray-400">
                    Marked Checked
                </div>

            </div>

        @empty

            <div class="rounded-xl border p-5 md:col-span-3">
                No checked equipment records yet.
            </div>

        @endforelse

    </div>



    {{-- Filters --}}

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">

        <form method="GET"
              class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-6">


            <input
                name="q"
                value="{{ $q }}"
                placeholder="Search property #, remarks..."
                class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">


            @if($canViewAllCheckedReports ?? true)
                <select name="checker_id"
                        class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">

                    <option value="">All checked by</option>

                    @foreach(($checkerUsers ?? $adminUsers) as $checker)
                        <option value="{{ $checker->id }}"
                        @selected((int)($checkerId ?? $adminId ?? 0)===$checker->id)>
                            {{ $checker->name }}
                        </option>
                    @endforeach
                </select>
            @else
              
                <input type="hidden" name="checker_id" value="{{ auth()->id() }}">
            @endif


            <select name="location_id"
                    class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">
                <option value="">All locations</option>
                @foreach($locations as $location)
                    <option value="{{ $location->id }}" @selected((int) $locationId === $location->id)>
                        {{ $location->name }}@if($location->code) ({{ $location->code }})@endif
                    </option>
                @endforeach
            </select>


            <select name="type_id"
                    class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">

                <option value="">
                    All equipment types
                </option>


                @foreach($types as $type)

                    <option value="{{ $type->id }}"
                    @selected((int)$typeId === $type->id)>
                        {{ $type->name }}
                    </option>

                @endforeach

            </select>



            <input type="date"
                   name="date_from"
                   value="{{ $dateFrom }}"
                   class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">


            <input type="date"
                   name="date_to"
                   value="{{ $dateTo }}"
                   class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">


            <button
                class="rounded-xl bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">
                Generate
            </button>


        </form>

    </div>




    {{-- Records --}}


    <form
        id="checked-equipment-print-form"
        method="POST"
        action="{{ route('admin.reports.checkedEquipment.pdfSelected') }}"
        target="_blank"
        onsubmit="return validateCheckedEquipmentSelection(this);"
        class="overflow-hidden rounded-2xl border bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

        @csrf



        <div class="flex flex-col gap-3 border-b p-5 lg:flex-row lg:items-center lg:justify-between">


            <div>

                <h2 class="font-semibold text-gray-900 dark:text-gray-100">
                    Marked Checked Records
                </h2>

                <p class="text-sm text-gray-500">
                    {{ number_format($records->total()) }} result(s)
                </p>

            </div>



            <div class="flex flex-wrap gap-2">
<!-- Print PDF Buttons 
                <a
                    href="{{ route('admin.reports.checkedEquipment.pdfFiltered', request()->query()) }}"
                    target="_blank"
                    data-no-spa="true"
                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700"
                >
                    Print Filtered PDF
                </a>-->


                <label class="flex items-center gap-2 text-sm">

                    <input
                        type="checkbox"
                        onchange="toggleCheckedEquipmentSelection(this)"
                        class="checked-all">

                    Select All

                </label>



                <button
                    class="rounded-xl bg-blue-600 px-4 py-2 text-sm text-white">

                    Print Selected PDF

                </button>

                @if($canDeleteCheckedHistory)
                    <button type="button"
                            onclick="openCheckedEquipmentDeleteRemarks()"
                            class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">
                        Delete Selected History
                    </button>
                @endif


            </div>


        </div>





        <div class="overflow-x-auto">


            <table class="min-w-full text-sm">


                <thead class="bg-gray-50 dark:bg-gray-800">

                    <tr>

                        <th class="px-4 py-3">
                            Select
                        </th>

                        <th class="px-4 py-3">
                            Date
                        </th>

                        <th class="px-4 py-3">
                            Checked By
                        </th>

                        <th class="px-4 py-3">
                            Equipment
                        </th>

                        <th class="px-4 py-3">
                            Type
                        </th>

                        <th class="px-4 py-3">
                            Location
                        </th>

                        <th class="px-4 py-3">
                            Remarks
                        </th>

                        <th class="px-4 py-3">
                            Corrective Action
                        </th>

                        <th class="px-4 py-3">
                            PDF
                        </th>

                        @if($canDeleteCheckedHistory)
                            <th class="px-4 py-3">
                                Delete
                            </th>
                        @endif

                    </tr>

                </thead>




                <tbody class="divide-y">


                @forelse($records as $record)


                    @php

                        $device = $record->device;

                        $snapshot = is_array($record->checklist_data) ? data_get($record->checklist_data, 'snapshot', []) : [];

                        $assignment = $device?->currentAssignment;

                        $office = $assignment?->office ?: $assignment?->staff?->office;

                        $location = $record->location ?? $office?->location;
                        $locationName = data_get($snapshot, 'location') ?? $location?->name;
                        $propertyNumber = data_get($snapshot, 'property_number') ?? $device?->property_number;
                        $equipmentType = data_get($snapshot, 'equipment_type') ?? $device?->type?->name;

                    @endphp



                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">


                        <td class="px-4 py-3 text-center">

                            @if($device)

                            <input
                                type="checkbox"
                                name="record_ids[]"
                                value="{{ $record->id }}"
                                class="checked-equipment-checkbox">

                            @else

                            -

                            @endif

                        </td>



                        <td class="px-4 py-3">

                            {{ $record->maintenance_date?->format('M d, Y') ?? '-' }}

                        </td>



                        <td class="px-4 py-3 font-medium">

                            {{ $record->checkedBy?->name ?? '-' }}

                        </td>




                        <td class="px-4 py-3">

                            {{ $propertyNumber ?? '-' }}

                        </td>



                        <td class="px-4 py-3">

                            {{ $equipmentType ?? '-' }}

                        </td>




                        <td class="px-4 py-3">

                            {{ $locationName ?? '-' }}

                        </td>




                        <td class="px-4 py-3">

                            {{ $record->remarks ?? '-' }}

                        </td>

                        <td class="px-4 py-3">

                            {{ $record->corrective_action ?? '-' }}

                        </td>




                        <td class="px-4 py-3 align-middle">
                            @if($device)
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('admin.reports.checkedEquipment.pdf',$record) }}"
                                       data-no-spa="true"
                                       target="_blank"
                                       class="inline-flex items-center justify-center whitespace-nowrap rounded bg-gray-900 px-3 py-1.5 leading-5 text-white hover:bg-gray-700">
                                        PDF
                                    </a>
                                    <a href="{{ route('admin.reports.checkedEquipment.preview', $record) }}"
                                       class="inline-flex items-center justify-center whitespace-nowrap rounded bg-indigo-600 px-3 py-1.5 leading-5 text-white hover:bg-indigo-500">
                                        Preview
                                    </a>
                                </div>
                            @endif
                        </td>

                        @if($canDeleteCheckedHistory)
                            <td class="px-4 py-3">
                                @if($device)
                                    <button type="button"
                                            onclick="deleteCheckedEquipmentRecord({{ $record->id }})"
                                            class="rounded bg-red-600 px-3 py-1 text-white hover:bg-red-700">
                                        Delete
                                    </button>
                                @endif
                            </td>
                        @endif



                    </tr>


                @empty


                    <tr>

                        <td colspan="{{ $canDeleteCheckedHistory ? 10 : 9 }}"
                            class="px-5 py-10 text-center">

                            No records found.

                        </td>

                    </tr>


                @endforelse


                </tbody>


            </table>


        </div>



        <div class="border-t p-5">

            {{ $records->links() }}

        </div>



    </form>

    @if($canDeleteCheckedHistory)
        <form id="checked-equipment-delete-form"
              method="POST"
              action="{{ route('admin.reports.checkedEquipment.delete') }}"
              class="rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-900/60 dark:bg-red-950/20">
            @csrf
            @method('DELETE')
            <input type="hidden" name="filter_checker_id" value="{{ $checkerId }}">
            <input type="hidden" name="filter_type_id" value="{{ $typeId }}">
            <input type="hidden" name="filter_location_id" value="{{ $locationId }}">
            <input type="hidden" name="filter_q" value="{{ $q }}">
            <input type="hidden" name="date_from" value="{{ $dateFrom }}">
            <input type="hidden" name="date_to" value="{{ $dateTo }}">
            <input type="hidden" name="select_all" id="checked-equipment-delete-all" value="0">
            <input type="hidden" name="remarks" id="checked-equipment-delete-remarks" value="">
            <div id="checked-equipment-delete-ids"></div>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="font-semibold text-red-900 dark:text-red-200">Delete checklist history</h2>
                    <p class="mt-1 text-sm text-red-800/80 dark:text-red-200/80">Select individual rows above to move them to Checklist Cleanup.</p>
                    @if($canBulkDeleteCheckedHistory)
                        <label class="mt-3 inline-flex items-center gap-2 text-sm text-red-900 dark:text-red-200">
                            <input id="checked-equipment-delete-all-toggle" type="checkbox" class="h-4 w-4">
                            Select all checklist history matching these filters across every page
                        </label>
                    @endif
                </div>
                <div class="flex w-full flex-col gap-2 lg:w-auto lg:min-w-96">
                    <button type="button" onclick="openCheckedEquipmentDeleteRemarks()" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Delete selected history</button>
                </div>
            </div>
        </form>

        <div id="checked-equipment-delete-remarks-modal" onclick="if (event.target === this) closeCheckedEquipmentDeleteRemarks()" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="checked-equipment-delete-remarks-title" aria-hidden="true">
            <div class="w-full max-w-lg rounded-2xl border border-red-300 bg-white p-5 shadow-2xl dark:border-red-900/70 dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 id="checked-equipment-delete-remarks-title" class="text-lg font-semibold text-gray-900 dark:text-white">Deletion remarks required</h2>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Explain why the selected checklist history is being deleted. It can be restored from Checklist Cleanup.</p>
                    </div>
                    <button type="button" onclick="closeCheckedEquipmentDeleteRemarks()" class="rounded-lg px-2 py-1 text-xl leading-none text-gray-500 hover:bg-gray-100 hover:text-gray-800 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Close">&times;</button>
                </div>
                <label for="checked-equipment-delete-remarks-input" class="mt-4 block text-sm font-medium text-gray-800 dark:text-gray-200">Remarks</label>
                <textarea id="checked-equipment-delete-remarks-input" maxlength="1000" rows="4" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white" placeholder="Why is this checklist history being deleted?"></textarea>
                <p id="checked-equipment-delete-remarks-error" class="mt-2 hidden text-sm text-red-600 dark:text-red-300">Remarks are required before deletion.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" onclick="closeCheckedEquipmentDeleteRemarks()" class="rounded-lg bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">Cancel</button>
                    <button type="button" onclick="confirmCheckedEquipmentDeleteRemarks()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Confirm deletion</button>
                </div>
            </div>
        </div>
    @endif


</div>

@endsection




@push('scripts')

<script>

function toggleCheckedEquipmentSelection(source)
{
    document.querySelectorAll('.checked-equipment-checkbox')
        .forEach(cb => cb.checked = source.checked);
}



function validateCheckedEquipmentSelection(form)
{

    const selected =
        form.querySelectorAll('.checked-equipment-checkbox:checked').length;


    if(selected === 0)
    {
        alert('Please select at least one checked equipment record.');
        return false;
    }


    return true;

}

function deleteCheckedEquipmentRecord(recordId)
{
    const form = document.getElementById('checked-equipment-delete-form');
    if (!form) return;

    openCheckedEquipmentDeleteRemarks(recordId);
}

function openCheckedEquipmentDeleteRemarks(recordId = null)
{
    const deleteAll = document.getElementById('checked-equipment-delete-all-toggle')?.checked === true;
    const ids = document.getElementById('checked-equipment-delete-ids');
    ids.innerHTML = '';

    if (recordId !== null && recordId !== undefined) {
        document.getElementById('checked-equipment-delete-all-toggle').checked = false;
        document.getElementById('checked-equipment-delete-all').value = '0';
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'record_ids[]';
        input.value = recordId;
        ids.appendChild(input);
    } else if (deleteAll) {
        document.getElementById('checked-equipment-delete-all').value = '1';
    } else {
        document.getElementById('checked-equipment-delete-all').value = '0';
        document.querySelectorAll('.checked-equipment-checkbox:checked').forEach((checkbox) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'record_ids[]';
            input.value = checkbox.value;
            ids.appendChild(input);
        });

        if (!ids.children.length) {
            alert('Select at least one checklist history row or choose delete all matching records.');
            return;
        }
    }

    const modal = document.getElementById('checked-equipment-delete-remarks-modal');
    const input = document.getElementById('checked-equipment-delete-remarks-input');
    const error = document.getElementById('checked-equipment-delete-remarks-error');
    if (!modal || !input) return;

    input.value = '';
    error?.classList.add('hidden');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    window.setTimeout(() => input.focus(), 0);
}

function closeCheckedEquipmentDeleteRemarks()
{
    const modal = document.getElementById('checked-equipment-delete-remarks-modal');
    if (!modal) return;

    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
}

function confirmCheckedEquipmentDeleteRemarks()
{
    const input = document.getElementById('checked-equipment-delete-remarks-input');
    const hiddenRemarks = document.getElementById('checked-equipment-delete-remarks');
    const form = document.getElementById('checked-equipment-delete-form');
    const error = document.getElementById('checked-equipment-delete-remarks-error');
    const reason = input?.value.trim() || '';

    if (!reason) {
        error?.classList.remove('hidden');
        input?.focus();
        return;
    }

    if (!form || !hiddenRemarks) return;
    hiddenRemarks.value = reason;
    closeCheckedEquipmentDeleteRemarks();
    form.submit();
}

</script>


@endpush

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

    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">

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
              class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-7">


            <input
                name="q"
                value="{{ $q }}"
                placeholder="Search property #, remarks..."
                class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">


            <select name="checker_id"
                    class="rounded-lg border px-3 py-2 text-sm dark:bg-gray-800 dark:text-white">

                <option value="">
                    All checked by
                </option>

                @foreach(($checkerUsers ?? $adminUsers) as $checker)

                    <option value="{{ $checker->id }}"
                    @selected((int)($checkerId ?? $adminId ?? 0)===$checker->id)>
                        {{ $checker->name }}
                    </option>

                @endforeach

            </select>


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

                    </tr>

                </thead>




                <tbody class="divide-y">


                @forelse($records as $record)


                    @php

                        $device = $record->device;

                        $snapshot = is_array($record->checklist_data) ? data_get($record->checklist_data, 'snapshot', []) : [];

                        $assignment = $device?->currentAssignment;

                        $office = $assignment?->staff?->office;

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




                        <td class="px-4 py-3">


                            @if($device)

                            <a href="{{ route('admin.reports.checkedEquipment.pdf',$record) }}" data-no-spa="true"
                               target="_blank"
                               class="rounded bg-gray-900 px-3 py-1 text-white">

                                PDF

                            </a>


                            @endif


                        </td>



                    </tr>


                @empty


                    <tr>

                        <td colspan="9"
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

</script>


@endpush

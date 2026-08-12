@extends('admin.layouts.app')

@section('title', 'Reports')
@section('page_title', 'Reports')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Reports</span>
@endsection

@section('content')
<div class="space-y-6">

    <div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Generate inventory, schedule monitoring, maintenance checklist, and other equipment reports.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">

        {{-- Assets --}}
        <a href="{{ route('admin.reports.assets') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-blue-500">

            <div class="text-sm font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">
                All Assets
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                Assets by Type / Office / Location
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Generate reports for all equipment by type, location, office, or in keyword.
            </p>
        </a>

        {{-- Issuance --}}
        @if(auth()->user()?->canMenu('issuance'))
        <a href="{{ route('admin.reports.issuance') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-amber-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-amber-500">

            <div class="text-sm font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">
                Issuance
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                Issued Equipment / End Users
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Verify active equipment issued to registered end users and generate issuance reports.
            </p>
        </a>
        @endif

        {{-- Accounts --}}
        @if(auth()->user()?->isSuperAdmin())
            <a href="{{ route('admin.reports.accounts') }}"
               class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-violet-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-violet-500">

                <div class="text-sm font-semibold uppercase tracking-wide text-violet-600 dark:text-violet-400">
                    Accounts
                </div>

                <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                    All Registered Accounts and Roles
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    List user accounts by role with searchable names and emails.
                </p>
            </a>
        @endif

        {{-- Checklist Equipment --}}
        <a href="{{ route('admin.reports.checkedEquipment') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-500">

            <div class="text-sm font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">
                Equipment Checklist
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                Equipment Checklist Reports
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Review and generate reports for checked equipment with completed checklist.
            </p>
        </a>

        {{-- Preventive maintenance schedule monitoring --}}
        @if(auth()->user()?->canMenu('reports'))
        <a href="{{ route('admin.reports.maintenanceSchedule') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-cyan-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-cyan-500">

            <div class="text-sm font-semibold uppercase tracking-wide text-cyan-600 dark:text-cyan-400">
                Preventive Maintenance
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                Schedule Monitoring
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Review and generate reports for preventive maintenance schedules, re-scheduled dates, actual checklist dates, person in-charge, signature, and remarks by office.
            </p>
        </a>

        <a href="{{ route('admin.reports.maintenanceQuality') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-emerald-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-emerald-500">

            <div class="text-sm font-semibold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">
                Quality Objective
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                PM Quality Monitoring
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Compare target, checked, maintained, compliance, and exceptions by office for each semiannual period.
            </p>
        </a>

        <a href="{{ route('admin.maintenance-attention.index') }}"
           class="group rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition-all duration-200 hover:-translate-y-1 hover:border-amber-300 hover:shadow-lg dark:border-gray-700 dark:bg-gray-800 dark:hover:border-amber-500">

            <div class="text-sm font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-400">
                Maintenance Attention
            </div>

            <h2 class="mt-3 text-lg font-semibold text-gray-900 dark:text-white">
                Equipment Maintenance Recommendations
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Review filtered Desktop and Laptop recommendations for memory, storage, licensing, age, and maintenance concerns.
            </p>
        </a>
        @endif

        {{-- Checklist --}}

    </div>

</div>
@endsection

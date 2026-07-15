@extends('admin.layouts.app')

@section('title', 'Registered Accounts Report')
@section('page_title', 'Registered Accounts Report')
@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <a href="{{ route('admin.reports.index') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Reports</a>
    <span>/</span>
    <span class="font-medium text-gray-800 dark:text-gray-200">Registered Accounts</span>
@endsection

@section('content')
<div class="space-y-5">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between no-print">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Registered Accounts Report</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">List of admin and custodian accounts.</p>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="inline-flex items-center rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Back to Reports</a>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 no-print">
        <div class="rounded-2xl border-l-4 border-blue-500 bg-white p-5 shadow-sm dark:bg-gray-900 dark:border-blue-400">
            <div class="text-xs font-semibold uppercase tracking-widest text-blue-500 dark:text-blue-400">Admin Accounts</div>
            <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($adminCount) }}</div>
        </div>
        <div class="rounded-2xl border-l-4 border-amber-500 bg-white p-5 shadow-sm dark:bg-gray-900 dark:border-amber-400">
            <div class="text-xs font-semibold uppercase tracking-widest text-amber-500 dark:text-amber-400">Custodian Accounts</div>
            <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ number_format($custodianCount) }}</div>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm no-print dark:border-gray-700 dark:bg-gray-900">
        <form method="GET" class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <input name="q" value="{{ $q }}" placeholder="Search name, email, role..." class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-500 dark:focus:ring-blue-900/40">

            <select name="role" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:focus:ring-blue-900/40">
                <option value="">All roles</option>
                <option value="admin" @selected($role === 'admin')>Admin</option>
                <option value="custodian" @selected($role === 'custodian')>Custodian</option>
            </select>

            <div class="flex gap-2">
                <button type="submit" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600">Generate</button>
                <a href="{{ route('admin.reports.accounts') }}" class="rounded-xl bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Reset</a>
            </div>
        </form>
    </div>

    <div id="print-area" class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <div>
                <h2 class="font-semibold text-gray-900 dark:text-white">Registered Accounts Report</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ number_format($users->total()) }} result(s)</p>
            </div>
            <button type="button" onclick="window.print()" class="no-print rounded-xl bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-black dark:bg-gray-700 dark:hover:bg-gray-600">Print</button>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse($users as $user)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full {{ $user->isAdmin() ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' }} px-2.5 py-1 text-xs font-medium">
                                    {{ $user->roleLabel() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $user->created_at ? $user->created_at->format('M d, Y h:i A') : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">No accounts found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-gray-200 px-5 py-4 no-print dark:border-gray-700">
            {{ $users->links() }}
        </div>
    </div>
</div>

<style>
    @media print {
        body * {
            visibility: hidden !important;
        }

        #print-area,
        #print-area * {
            visibility: visible !important;
        }

        #print-area {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            border: none !important;
            box-shadow: none !important;
            background: #ffffff !important;
        }

        #print-area table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        #print-area th,
        #print-area td {
            border: 1px solid #111827 !important;
            padding: 8px !important;
        }

        #print-area thead {
            background: #f3f4f6 !important;
        }

        .no-print {
            display: none !important;
        }

        @page {
            size: A4 portrait;
            margin: 12mm;
        }
    }
</style>
@endsection
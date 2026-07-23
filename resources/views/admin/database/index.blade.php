@extends('admin.layouts.app')

@section('title', 'Database Backup & Restore')
@section('page_title', 'Database Backup & Restore')

@section('breadcrumbs')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span>/</span>
    <span>Database Backup &amp; Restore</span>
@endsection

@section('content')
    <div class="mx-auto max-w-5xl space-y-6">
        @if ($errors->any())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300" role="alert">
                <p class="font-semibold">The database action could not be completed.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-7">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Database Backup &amp; Restore</h1>
                    <p class="mt-1 max-w-3xl text-sm text-gray-600 dark:text-gray-300">
                        Super Admin tools for exporting a portable SQL backup and restoring it on this PMAMS installation.
                    </p>
                </div>
                <div class="rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-600 dark:bg-gray-900 dark:text-gray-300">
                    <div>Driver: <span class="font-semibold">{{ strtoupper($driver) }}</span></div>
                    <div>Database: <span class="font-semibold">{{ $database }}</span></div>
                </div>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2Z"/>
                        </svg>
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">Download a backup</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Creates a standard <code>.sql</code> file containing the tables and records. It can be imported through XAMPP phpMyAdmin or the MySQL client.</p>
                    <a href="{{ route('admin.database.download') }}" data-no-spa="true" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                        Download SQL Backup
                    </a>
                </div>

                <div class="rounded-xl border border-gray-200 p-5 dark:border-gray-700">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v12m0 0 4-4m-4 4-4-4M5 21h14a2 2 0 0 0 2-2v-1a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v1a2 2 0 0 0 2 2Z"/>
                        </svg>
                    </div>
                    <h2 class="mt-4 text-base font-semibold text-gray-900 dark:text-white">Restore a SQL backup</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Upload a <code>.sql</code> or <code>.txt</code> MySQL/MariaDB dump. Foreign-key checks are paused while the statements are processed.</p>
                    <form method="POST" action="{{ route('admin.database.restore') }}" enctype="multipart/form-data" data-no-spa="true" class="mt-5 space-y-3" onsubmit="return confirm('Restore this SQL backup? Existing tables may be replaced and this cannot be undone. Create a backup first.');">
                        @csrf
                        <label for="database-backup" class="sr-only">SQL backup file</label>
                        <input id="database-backup" name="backup" type="file" accept=".sql,.txt,text/plain,application/sql" required class="block min-h-11 w-full cursor-pointer rounded-xl border border-gray-300 bg-gray-50 text-sm text-gray-700 file:mr-4 file:min-h-11 file:border-0 file:bg-gray-200 file:px-4 file:font-semibold dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 dark:file:bg-gray-700 dark:file:text-gray-100">
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800">
                            Restore Database
                        </button>
                    </form>
                </div>
            </div>

            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/70 dark:bg-amber-900/20 dark:text-amber-200" role="note">
                <p class="font-semibold">Important</p>
                <p class="mt-1">Restoring can replace current data and MySQL DDL is not fully transactional. Download a fresh backup first, and use phpMyAdmin's Import tab in XAMPP when moving the file to another installation.</p>
            </div>
        </section>
    </div>
@endsection

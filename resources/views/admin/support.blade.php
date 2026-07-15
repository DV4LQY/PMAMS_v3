@extends('admin.layouts.app')

@section('title', 'Support')
@section('page_title', 'Support')

@section('breadcrumb')
    <a href="{{ route('admin.dashboard') }}" class="hover:text-blue-600 dark:hover:text-blue-400">Dashboard</a>
    <span class="text-gray-400">/</span>
    <span class="font-medium text-gray-800 dark:text-gray-100">Support</span>
@endsection

@section('content')
@php
    $developers = config('support.developers', []);
@endphp

<div class="space-y-6">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-500 px-6 py-8 text-white">
            <p class="text-sm font-semibold uppercase tracking-[0.25em] text-blue-100">Support Team</p>
            <h1 class="mt-2 text-3xl font-bold">Developer Contacts</h1>
            <p class="mt-2 max-w-2xl text-sm text-blue-50">
                Contact the development team for system support, bug reports, page updates, and maintenance requests.
            </p>
        </div>

        <div class="grid gap-4 p-5" style="grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));">
            @foreach($developers as $developer)
                <article class="group rounded-2xl border border-gray-200 bg-gray-50 p-4 text-center transition hover:-translate-y-0.5 hover:border-blue-200 hover:bg-white hover:shadow-md dark:border-gray-700 dark:bg-gray-900/40 dark:hover:border-blue-700 dark:hover:bg-gray-900">
                    <div class="mx-auto h-24 w-24 overflow-hidden rounded-full ring-4 ring-white shadow-md dark:ring-gray-800">
                        <img
                            src="{{ asset($developer['photo']) }}"
                            alt="{{ $developer['name'] }} profile photo"
                            class="h-full w-full object-cover"
                            loading="lazy"
                        >
                    </div>

                    <div class="mt-4">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                            {{ $developer['name'] }}
                        </h2>
                        <p class="mt-1 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $developer['role'] }}
                        </p>
                        @if(!empty($developer['bio']))
                            <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">
                                {{ $developer['bio'] }}
                            </p>
                        @endif
                    </div>

                    <a
                        href="mailto:{{ $developer['email'] }}"
                        class="mt-4 inline-flex max-w-full items-center justify-center gap-2 rounded-xl bg-white px-3 py-2 text-sm font-medium text-blue-700 shadow-sm ring-1 ring-gray-200 transition hover:bg-blue-50 dark:bg-gray-800 dark:text-blue-300 dark:ring-gray-700 dark:hover:bg-gray-700"
                    >
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 8l9 6 9-6M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <span class="truncate">{{ $developer['email'] }}</span>
                    </a>
                </article>
            @endforeach
        </div>
    </div>

@endsection

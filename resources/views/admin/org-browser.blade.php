@extends('admin.layouts.app')

@section('title', 'Colleges')

@section('content')
    <h1 class="text-2xl font-semibold mb-4">Colleges → Offices → Staff → Issued Equipment</h1>
    <livewire:admin.college-browser />
@endsection
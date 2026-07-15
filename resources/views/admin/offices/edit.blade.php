@extends('admin.layouts.app')

@section('title', 'Edit Office')

@section('content')
<div class="mb-4">
    <div class="text-sm text-gray-600">
        <a class="text-blue-700 hover:underline" href="{{ route('admin.colleges.index') }}">Colleges</a>
        <span class="mx-2">/</span>
        <a class="text-blue-700 hover:underline" href="{{ route('admin.offices.index', $college) }}">{{ $college->name }}</a>
        <span class="mx-2">/</span>
        <span>Edit Office</span>
    </div>
    <h1 class="text-2xl font-semibold mt-1">Edit Office</h1>
</div>

<div class="bg-white rounded shadow-sm p-4 max-w-xl">
    <form method="POST" action="{{ route('admin.offices.update', [$college, $office]) }}" class="space-y-3">
        @csrf
        @method('PUT')

        <div>
            <label class="text-sm font-medium">Office Name</label>
            <input name="name" value="{{ old('name', $office->name) }}" class="mt-1 w-full border rounded px-3 py-2" required>
            @error('name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="flex gap-2">
            <button class="px-4 py-2 rounded bg-blue-600 text-white">Update</button>
            <a href="{{ route('admin.offices.index', $college) }}" class="px-4 py-2 rounded bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
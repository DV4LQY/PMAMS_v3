@extends('admin.layouts.app')

@section('title', 'Edit Staff')

@section('content')
<div class="mb-4">
    <div class="text-sm text-gray-600">
        <a class="text-blue-700 hover:underline" href="{{ route('admin.colleges.index') }}">Colleges</a>
        <span class="mx-2">/</span>
        <a class="text-blue-700 hover:underline"
           href="{{ route('admin.offices.index', $office->college) }}">{{ $office->college->name }}</a>
        <span class="mx-2">/</span>
        <a class="text-blue-700 hover:underline"
           href="{{ route('admin.staff.index', $office) }}">{{ $office->name }}</a>
        <span class="mx-2">/</span>
        <span>Edit Staff</span>
    </div>
    <h1 class="text-2xl font-semibold mt-1">Edit Staff</h1>
</div>

<div class="bg-white rounded shadow-sm p-4 max-w-xl">
    <form method="POST" action="{{ route('admin.staff.update', [$office, $staff]) }}" class="space-y-3">
        @csrf
        @method('PUT')

        <div>
            <label class="text-sm font-medium">First name</label>
            <input name="first_name" value="{{ old('first_name', $staff->first_name) }}"
                   class="mt-1 w-full border rounded px-3 py-2" required>
            @error('first_name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Last name</label>
            <input name="last_name" value="{{ old('last_name', $staff->last_name) }}"
                   class="mt-1 w-full border rounded px-3 py-2" required>
            @error('last_name') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Position (optional)</label>
            <input name="position" value="{{ old('position', $staff->position) }}"
                   class="mt-1 w-full border rounded px-3 py-2">
        </div>

        <div>
            <label class="text-sm font-medium">Email (optional)</label>
            <input name="email" type="email" value="{{ old('email', $staff->email) }}"
                   class="mt-1 w-full border rounded px-3 py-2">
            @error('email') <div class="text-sm text-red-600 mt-1">{{ $message }}</div> @enderror
        </div>

        <div>
            <label class="text-sm font-medium">Phone (optional)</label>
            <input name="phone" value="{{ old('phone', $staff->phone) }}"
                   class="mt-1 w-full border rounded px-3 py-2">
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $staff->is_active))>
            Active
        </label>

        <div class="flex gap-2">
            <button class="px-4 py-2 rounded bg-blue-600 text-white">Update</button>
            <a href="{{ route('admin.staff.index', $office) }}" class="px-4 py-2 rounded bg-gray-100">Cancel</a>
        </div>
    </form>
</div>
@endsection
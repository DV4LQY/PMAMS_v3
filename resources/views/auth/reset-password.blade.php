<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>Reset Password</title>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
    <form method="POST" action="{{ route('password.update') }}" class="bg-white p-6 rounded-lg shadow w-full max-w-sm">
        @csrf

        <h1 class="text-xl font-semibold mb-4">Reset Password</h1>

        <input type="hidden" name="token" value="{{ $token }}">

        <label class="block mb-2 text-sm">Email</label>
        <input
            name="email"
            type="email"
            value="{{ old('email', $email) }}"
            class="w-full border rounded px-3 py-2 mb-3"
            required
            maxlength="255"
        >

        <label class="block mb-2 text-sm">New Password</label>
        <input
            name="password"
            type="password"
            class="w-full border rounded px-3 py-2 mb-3"
            required
            minlength="8"
        >

        <label class="block mb-2 text-sm">Confirm Password</label>
        <input
            name="password_confirmation"
            type="password"
            class="w-full border rounded px-3 py-2 mb-3"
            required
            minlength="8"
        >

        @if ($errors->any())
            <div class="text-sm text-red-600 mb-3">
                {{ $errors->first() }}
            </div>
        @endif

        <button type="submit" class="w-full bg-black text-white rounded px-3 py-2">
            Reset Password
        </button>

        <div class="mt-4 text-center">
            <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">
                Back to Login
            </a>
        </div>
    </form>
</body>
</html>
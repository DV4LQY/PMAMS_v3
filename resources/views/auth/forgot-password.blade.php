<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>Forgot Password</title>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
    <form method="POST" action="{{ route('password.email') }}" class="bg-white p-6 rounded-lg shadow w-full max-w-sm">
        @csrf

        <h1 class="text-xl font-semibold mb-2">Forgot Password</h1>

        <p class="text-sm text-gray-600 mb-4">
            Enter your email address and we will send you a password reset link.
        </p>

        @if (session('status'))
            <div class="text-sm text-green-600 mb-3">
                {{ session('status') }}
            </div>
        @endif

        <label class="block mb-2 text-sm">Email</label>
        <input
            name="email"
            type="email"
            value="{{ old('email') }}"
            class="w-full border rounded px-3 py-2 mb-3"
            required
            maxlength="255"
            autofocus
        >

        @error('email')
            <div class="text-sm text-red-600 mb-3">
                {{ $message }}
            </div>
        @enderror

        <button type="submit" class="w-full bg-black text-white rounded px-3 py-2">
            Send Reset Link
        </button>

        <div class="mt-4 text-center">
            <a href="{{ route('login') }}" class="text-sm text-blue-600 hover:underline">
                Back to Login
            </a>
        </div>
    </form>
</body>
</html>

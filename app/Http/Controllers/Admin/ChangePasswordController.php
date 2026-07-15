<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ChangePasswordController extends Controller
{
    public function edit()
    {
        return view('admin.auth.change-password');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->symbols(),
            ],
            'logout_after_change' => ['nullable', 'boolean'],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.mixed' => 'The new password must contain at least one uppercase and one lowercase letter.',
            'password.symbols' => 'The new password must contain at least one special character such as #, @, $, or !.',
        ]);

        $user = $request->user();

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        if ($request->boolean('logout_after_change')) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('success', 'Password changed successfully. Please log in again.');
        }

        return back()->with('success', 'Password changed successfully.');
    }
}

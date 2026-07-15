<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function show()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $userExists = User::where('email', $request->email)->exists();

        if (! $userExists) {
            return back()
                ->withErrors(['email' => 'Email not found.'])
                ->onlyInput('email');
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', 'Password reset link has been sent to your email.');
        }

        return back()
            ->withErrors(['email' => 'Unable to send reset link. Please try again.'])
            ->onlyInput('email');
    }
}
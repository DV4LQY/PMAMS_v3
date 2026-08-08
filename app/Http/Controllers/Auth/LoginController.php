<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class LoginController extends Controller
{
    public function show()
    {
        // Keep one browser session tied to its current account. Opening the
        // login URL in another tab must not expose a second login form that
        // could replace the account used by the other tabs.
        if (Auth::check()) {
            return redirect('/admin/dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if ((bool) config('services.recaptcha.enabled')) {
            if (! filled(config('services.recaptcha.site_key')) || ! filled(config('services.recaptcha.secret_key'))) {
                return back()->withErrors([
                    'email' => 'Login security verification is not configured. Please contact an administrator.',
                ])->withInput($request->only('email'));
            }

            $token = trim((string) $request->input('recaptcha_token'));
            if ($token === '' || ! $this->verifyRecaptcha($token, $request)) {
                return back()->withErrors([
                    'email' => 'Security verification failed. Please try again.',
                ])->withInput($request->only('email'));
            }
        }

        $guard = Auth::guard();

        // A browser profile has one session cookie shared by every tab. Do
        // not let a second login silently replace the account that is already
        // active in another tab. The user can sign out first to switch, or
        // use a separate browser profile for a simultaneous account.
        if ($guard->check()) {
            $currentUser = $guard->user();
            $candidate = $guard->getProvider()->retrieveByCredentials($credentials);

            if ($candidate
                && $currentUser
                && (string) $candidate->getAuthIdentifier() !== (string) $currentUser->getAuthIdentifier()
                && $guard->getProvider()->validateCredentials($candidate, $credentials)) {
                return back()->withErrors([
                    'email' => 'Another account is already active in this browser. Sign out first before changing accounts.',
                ])->onlyInput('email');
            }
        }

        // Validate the new account before ending the current one. This lets a
        // user switch accounts in the same browser without logging out of the
        // current account when a mistyped password is submitted.
        if ($guard->validate($credentials)) {
            if ($guard->check()) {
                $guard->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            if (! $guard->attempt($credentials, $request->boolean('remember'))) {
                return back()->withErrors([
                    'email' => 'Unable to start the selected account session. Please try again.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();

            $user = $guard->user();

            if (in_array($user?->role, ['super_admin', 'admin', 'unit_head'], true)) {
                return redirect('/admin/dashboard');
            }

            return redirect()->intended('/admin/dashboard');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials.',
        ])->onlyInput('email');
    }

    /**
     * Verify the short-lived reCAPTCHA v3 token on the server. The secret key
     * is never sent to the browser or bundled into the Android WebView.
     */
    private function verifyRecaptcha(string $token, Request $request): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => config('services.recaptcha.secret_key'),
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);

            if (! $response->successful()) {
                return false;
            }

            $payload = $response->json();
            if (! ($payload['success'] ?? false)) {
                return false;
            }

            if (isset($payload['action']) && $payload['action'] !== 'login') {
                return false;
            }

            $score = $payload['score'] ?? null;
            if ($score !== null && (float) $score < (float) config('services.recaptcha.score_threshold', 0.5)) {
                return false;
            }

            $expectedHostname = trim((string) config('services.recaptcha.hostname', ''));
            if ($expectedHostname !== '') {
                $actualHostname = strtolower(trim((string) ($payload['hostname'] ?? '')));
                if ($actualHostname === '' || ! hash_equals(strtolower($expectedHostname), $actualHostname)) {
                    return false;
                }
            }

            return true;
        } catch (\Throwable $exception) {
            report($exception);
            return false;
        }
    }

    public function logout(Request $request)
    {
        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}

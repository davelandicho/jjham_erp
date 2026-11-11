<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view ('portal.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $login = trim($data['login']);
        $field = str_contains($login, '@') ? 'email' : 'mobile';

        $loggedIn = Auth::guard('applicant')->attempt(
            [$field => $login, 'password' => $data['password']],
            $request->boolean('remember'),
        );

        if (! $loggedIn) {
            throw ValidationException::withMessages([
                'login' => 'These details do not match our records.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('portal.home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('applicant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('status', 'You have been logged out.');
    }
}

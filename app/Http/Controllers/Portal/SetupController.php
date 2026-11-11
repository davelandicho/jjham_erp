<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SetupController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.setup');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $login = trim($data['login']);
        $field = str_contains($login, '@') ? 'email' : 'mobile';
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code']));

        $applicant = Applicant::where($field, $login)->first();

        if (! $applicant || ! $applicant->hasValidPortalSetupCode($code)) {
            throw ValidationException::withMessages([
                'code' => 'This code is not valid or has expired. Please ask our staff for a new one.',
            ]);
        }

        $applicant->completePortalSetup($data['password']);

        Auth::guard('applicant')->login($applicant);
        $request->session()->regenerate();

        return redirect()->route('portal.home')->with('status', 'Your account is ready. Welcome!');
    }
}
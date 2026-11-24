<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Enums\OtpPurpose;
use App\Models\Applicant;
use App\Models\ApplicantOtp;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use App\Models\ApplicantDevice;

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

        $applicant = Applicant::where($field, $login)->first();

        if (! $applicant || ! $applicant->password
            || ! Hash::check($data['password'], $applicant->password)) {
            throw ValidationException::withMessages([
                'login' => 'These details do not match our records.',
            ]);
        }

        $device = ApplicantDevice::findValid($applicant,
        $request->cookie(ApplicantDevice::COOKIE));

        if ($device) {
            $device->update([
                'last_used_at' => now(),
                'ip_address' => $request->ip(),
            ]);

            Auth::guard('applicant')->login($applicant,
            $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(route('portal.home'));
        }

        if (! $applicant->email) {
            throw ValidationException::withMessages([
                'login' => 'Your account has no email for verification codes. Please contact our office.',
            ]);
        }

        try {
            $otp = ApplicantOtp::send($applicant, OtpPurpose::Login, $applicant->email);
        } catch (TransportExceptionInterface $e) {
            report($e);

            throw ValidationException::withMessages([
                'login' => 'We could not send your code right now. Please wait a moment and try again.',
            ]);
        }

        $request->session()->put('portal_login', [
            'applicant_id' => $applicant->id,
            'otp_id' => $otp->id,
            'remember' => $request->boolean('remember'),
        ]);

        return redirect()->route('portal.otp');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('applicant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('status', 'You have been logged out.');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantOtp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use App\Models\ApplicantDevice;
use Illuminate\Support\Facades\Cookie;

class OtpController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get('portal_login');

        if (! $pending) {
            return redirect()->route('portal.login');
        }

        $applicant = Applicant::findOrFail($pending['applicant_id']);

        return view('portal.auth.otp', [
            'maskedEmail' => $this->mask($applicant->email),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $pending = $request->session()->get('portal_login');
        $otp = $pending ? ApplicantOtp::find($pending['otp_id']) : null;

        if (! $otp || ! $otp->isUsable()) {
            $request->session()->forget('portal_login');

            return redirect()->route('portal.login')->withErrors([
                'login' => 'That code has expired or had too many tries. Please sign in again.',
            ]);
        }

        if (! $otp->verify($data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is incorrect. Please check your email and try again.',
            ]);
        }

        Auth::guard('applicant')->login($otp->applicant, $pending['remember']);
        if ($pending['remember']) {
            $token = ApplicantDevice::issue($otp->applicant, $request);

            Cookie::queue(ApplicantDevice::COOKIE, $token,
            ApplicantDevice::DAYS_VALID * 24 * 60);
        }

        $request->session()->forget('portal_login');
        $request->session()->regenerate();

        return redirect()->intended(route('portal.home'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $request->session()->get('portal_login');

        if (! $pending) {
            return redirect()->route('portal.login');
        }

        $applicant = Applicant::findOrFail($pending['applicant_id']);
        
        try {    
            $otp = ApplicantOtp::send($applicant, OtpPurpose::Login, $applicant->email);
        } catch (TransportExceptionInterface $e) {
            report ($e);

            return back()->withErrors([
                'code' => 'We could not send a new code right now. Please wait a moment and try again.'
            ]);
        }

        $pending['otp_id'] = $otp->id;
        $request->session()->put('portal_login', $pending);

        return back()->with('status', 'We sent you a new code.');
    }

    private function mask(string $email): string
    {
        [$name, $domain] = explode('@', $email, 2);

        return substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 1)) . '@' . $domain;
    }
}
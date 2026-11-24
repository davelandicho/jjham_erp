<x-layouts.portal title="Enter your code" simple>
    <div class="mx-auto w-full max-w-md">
        <div class="flex justify-center">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-20 w-auto">
        </div>

        <div class="mt-6 rounded-xl bg-white px-6 py-10 shadow-sm ring-1 ring-gray-950/5 sm:px-10">
            <h1 class="text-center text-2xl font-bold tracking-tight text-gray-950">Check your email</h1>
            <p class="mt-1 text-center text-sm text-gray-500">
                We sent a 6-digit code to <span class="font-medium text-gray-950">{{ $maskedEmail }}</span>
            </p>

            <form method="POST" action="{{ route('portal.otp.store') }}" class="mt-8 space-y-6">
                @csrf

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-950">
                        Verification code<sup class="text-red-600 font-medium">*</sup>
                    </label>
                    <div data-otp class="mt-2 flex justify-between gap-2">
                        <input type="hidden" name="code">
                        @for ($i = 0; $i < 6; $i++)
                            <input data-otp-box type="text" inputmode="numeric" aria-label="Digit {{ $i + 1 }}"
                                @if ($i === 0) id="code" autocomplete="one-time-code" autofocus @else autocomplete="off" @endif
                                class="size-12 rounded-lg bg-white text-center text-xl font-semibold shadow-sm ring-1 ring-gray-950/10 outline-none focus:ring-2 focus:ring-brand">
                        @endfor
                    </div>
                    @error('code')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-dark">
                    Verify and sign in
                </button>
            </form>

            <form method="POST" action="{{ route('portal.otp.resend') }}" class="mt-6 text-center">
                @csrf
                <button type="submit" class="text-sm font-semibold text-brand hover:underline">
                    Didn't get it? Send a new code
                </button>
            </form>

            <p class="mt-4 text-center text-sm text-gray-500">
                <a href="{{ route('portal.login') }}" class="hover:underline">Back to sign in</a>
            </p>
        </div>
    </div>
</x-layouts.portal>
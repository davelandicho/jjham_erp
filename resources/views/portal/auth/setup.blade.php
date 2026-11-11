<x-layouts.portal title="Set up your account" simple>
    <div class="mx-auto w-full max-w-md">
        <div class="flex justify-center">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-20 w-auto">
        </div>

        <div class="mt-6 rounded-xl bg-white px-6 py-10 shadow-sm ring-1 ring-gray-950/5 sm:px-10">
            <h1 class="text-center text-2xl font-bold tracking-tight text-gray-950">Set up your account</h1>
            <p class="mt-1 text-center text-sm text-gray-500">Use the setup code our staff gave you.</p>

            <form method="POST" action="{{ route('portal.setup.store') }}" class="mt-8 space-y-6">
                @csrf

                <div>
                    <label for="login" class="block text-sm font-medium text-gray-950">
                        Email or mobile number<sup class="text-red-600 font-medium">*</sup>
                    </label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus
                           autocomplete="username"
                           class="mt-2 block w-full rounded-lg bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-950/10 outline-none focus:ring-2 focus:ring-brand">
                    @error('login')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="code" class="block text-sm font-medium text-gray-950">
                        Setup code<sup class="text-red-600 font-medium">*</sup>
                    </label>
                    <input id="code" name="code" type="text" value="{{ old('code') }}" required
                           autocomplete="one-time-code" placeholder="XXXX-XXXX"
                           class="mt-2 block w-full rounded-lg bg-white px-3 py-2 text-sm uppercase tracking-widest shadow-sm ring-1 ring-gray-950/10 outline-none focus:ring-2 focus:ring-brand">
                    @error('code')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-950">
                        New password<sup class="text-red-600 font-medium">*</sup>
                    </label>
                    <input id="password" name="password" type="password" required
                           autocomplete="new-password"
                           class="mt-2 block w-full rounded-lg bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-950/10 outline-none focus:ring-2 focus:ring-brand">
                    <p class="mt-2 text-xs text-gray-500">At least 8 characters, with letters and numbers.</p>
                    @error('password')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-950">
                        Confirm new password<sup class="text-red-600 font-medium">*</sup>
                    </label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password"
                           class="mt-2 block w-full rounded-lg bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-950/10 outline-none focus:ring-2 focus:ring-brand">
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    Set password and sign in
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-gray-500">
                Already set up?
                <a href="{{ route('portal.login') }}" class="font-semibold text-brand hover:underline">Sign in</a>
            </p>
        </div>
    </div>
</x-layouts.portal>
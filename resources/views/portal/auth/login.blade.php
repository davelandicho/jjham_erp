<x-layouts.portal title="Sign in" simple>
    <div class="mx-auto w-full max-w-md">
        <div class="flex justify-center">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-20 w-auto">
        </div>

        <div class="mt-6 rounded-xl bg-white px-6 py-10 shadow-sm ring-1 ring-gray-950/5 sm:px-10">
            <h1 class="text-center text-2xl font-bold tracking-tight text-gray-950">Sign in</h1>
            <p class="mt-1 text-center text-sm text-gray-500">Applicant Portal</p>

            <form method="POST" action="{{ route('portal.login.store') }}" class="mt-8 space-y-6">
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
                    <label for="password" class="block text-sm font-medium text-gray-950">
                        Password<sup class="text-red-600 font-medium">*</sup>
                    </label>
                    <input id="password" name="password" type="password" required
                           autocomplete="current-password"
                           class="mt-2 block w-full rounded-lg bg-white px-3 py-2 text-sm shadow-sm ring-1 ring-gray-950/10 outline-none focus:ring-2 focus:ring-brand">
                </div>

                <label class="flex items-center gap-3 text-sm font-medium text-gray-950">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded accent-brand">
                    Remember me
                </label>

                <button type="submit"
                        class="w-full rounded-lg bg-brand px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-dark focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                    Sign in
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-gray-500">
                Got a setup code from our staff?
                <a href="{{ route('portal.setup') }}" class="font-semibold text-brand hover:underline">Set up your account</a>
            </p>
        </div>
    </div>
</x-layouts.portal>
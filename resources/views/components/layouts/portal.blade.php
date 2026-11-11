@props(['title' => null, 'simple' => false])

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">

        <title>{{ $title ? $title . ' · ' : ''}}{{config('app.name')}} Applicant Portal </title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])  
    </head>

    <body class="min-h-screen bg-gray-50 text-gray-900 antialiased flex flex-col">
        
        @unless ($simple)
            <header class="sticky top-0 z-10 bg-white shadow-sm ring-1 ring-gray-950/5">
                <div class="mx-auto flex h-16 max-w-3xl items-center justify-between px-4">
                    <a href="{{ route('portal.home') }}" class="flex items-center gap-3">
                        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}" class="h-10 w-auto">
                        <span class="hidden text-sm font-semibold text-gray-950 sm:inline">Applicant Portal</span>
                    </a>

                    @auth('applicant')
                        <div class="flex items-center gap-3">
                            <span class="hidden text-sm font-medium text-gray-700 sm:inline">
                                {{ auth('applicant')->user()->first_name }}
                            </span>
                            <span class="flex size-8 items-center justify-center rounded-full bg-brand text-sm font-semibold text-white">
                                {{ mb_substr(auth('applicant')->user()->first_name, 0, 1) }}
                            </span>
                            <form method="POST" action="{{ route('portal.logout') }}">
                                @csrf
                                <button type="submit"
                                        class="rounded-lg px-3 py-1.5 text-sm font-semibold text-gray-700 shadow-sm ring-1 ring-gray-950/10 hover:bg-gray-50">
                                    Log out
                                </button>
                            </form>
                        </div>
                    @endauth
                </div>
            </header>
        @endunless

        <main @class([
            'flex-1 mx-auto w-full max-w-3xl px-4 py-6',
            'flex flex-col justify-center' => $simple,
            ])>
            @if (session('status'))
                <div class="mb-6 rounded-xl bg-white p-4 text-sm font-medium text-green-700 shadow-sm ring-1 ring-green-600/20">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="py-6 text-center text-xs text-gray-500">
            $copy; {{ now()->year}} {{ config('app.name') }}
        </footer>
    </body>
</html>
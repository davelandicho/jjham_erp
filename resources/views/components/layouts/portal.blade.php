@props(['title' => null])

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
        
        <header class="bg-white border-b border-gray-200">
            <div class="mx-auto max-w-3xl px-4 py-3 flex items-center justify-between">
                <a href="{{route('portal.home') }}" class="font-semibold">
                    {{ config('app.name') }}
                </a>

                @auth('applicant')
                    <span class="text-sm text-gray-600">
                        {{auth('applicant')->user()->first_name }}
                    </span>
                @endauth
            </div>
        </header>

        <main class="flex-1 mx-auto w-full max-w-3xl px-4 py-6">
            @if (session('status'))
                <div class="mb-4 rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                    {{ session('status')}}
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="py-6 text-center text-xs text-gray-500">
            $copy; {{ now()->year}} {{ config('app.name') }}
        </footer>
    </body>
</html>
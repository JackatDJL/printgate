<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('printgate.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-100 font-sans text-stone-950 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-4xl flex-col gap-8 px-6 py-12">
            <header class="border-b border-stone-300 pb-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <img src="{{ asset('printgate.svg') }}" alt="" width="44" height="44">
                        <div>
                            <p class="text-lg font-semibold tracking-tight">Printgate</p>
                            <p class="mt-1 text-sm text-stone-600">{{ config('printgate.role') === 'hub' ? 'Remote printing hub' : 'Local printing gateway' }}</p>
                        </div>
                    </div>
                    @auth
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-stone-300 px-3 py-2 text-sm font-medium text-stone-700 hover:border-stone-400 hover:text-stone-950">Sign out</button>
                        </form>
                    @endauth
                </div>
            </header>

            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>

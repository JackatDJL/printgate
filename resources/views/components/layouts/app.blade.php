<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-100 font-sans text-stone-950 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-4xl flex-col gap-8 px-6 py-12">
            <header class="border-b border-stone-300 pb-5">
                <p class="text-lg font-semibold tracking-tight">Printgate</p>
                <p class="mt-1 text-sm text-stone-600">Local printing gateway</p>
            </header>

            {{ $slot }}
        </main>

        @livewireScripts
    </body>
</html>

<x-layouts.app>
    <section class="mx-auto w-full max-w-md rounded-lg border border-stone-300 bg-white p-6 shadow-sm">
        <div>
            <p class="text-sm font-medium text-stone-600">Printgate</p>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-950">Sign in</h1>
            <p class="mt-2 text-sm leading-6 text-stone-600">Use the account created on this Printgate host.</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-stone-800">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="mt-2 block w-full rounded-md border-stone-300 text-sm text-stone-900">
                @error('email')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-stone-800">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-md border-stone-300 text-sm text-stone-900">
                @error('password')
                    <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-stone-950 px-3 py-2 text-sm font-medium text-white hover:bg-stone-800">Sign in</button>
        </form>
    </section>
</x-layouts.app>

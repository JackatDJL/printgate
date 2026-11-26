<x-layouts.app>
    <main class="mx-auto max-w-3xl space-y-8 p-8">
        <h1 class="text-2xl font-semibold">Printgate Connect</h1>

        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif

        @if ($invitationToken)
            <section>
                <h2 class="text-lg font-medium">Pairing invitation</h2>
                <p>Use this once within 10 minutes. Keep it private.</p>
                <code class="break-all">{{ $invitationToken }}</code>
            </section>
        @endif

        <form method="POST" action="{{ route('connect.invitations.store') }}">
            @csrf
            <button type="submit">Create pairing invitation</button>
        </form>

        <section>
            <h2 class="text-lg font-medium">Pending pairing requests</h2>
            @forelse ($claims as $claim)
                <form method="POST" action="{{ route('connect.claims.confirm', $claim) }}" class="space-y-2">
                    @csrf
                    <p>{{ $claim->site_name }} — comparison code <strong>{{ $claim->comparison_code }}</strong></p>
                    <label>Enter the code shown by the site
                        <input name="comparison_code" inputmode="numeric" maxlength="6" required>
                    </label>
                    @error('comparison_code')
                        <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    <button type="submit">Confirm pairing</button>
                </form>
            @empty
                <p>No pending pairing requests.</p>
            @endforelse
        </section>

        <section>
            <h2 class="text-lg font-medium">Sites</h2>
            @forelse ($sites as $site)
                <article class="flex items-center justify-between">
                    <span>{{ $site->name }} @if ($site->revoked_at) (revoked) @endif</span>
                    @unless ($site->revoked_at)
                        <form method="POST" action="{{ route('connect.sites.revoke', $site) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit">Revoke credential</button>
                        </form>
                    @endunless
                </article>
                @unless ($site->revoked_at)
                    <form method="POST" action="{{ route('connect.sites.jobs.store', $site) }}" enctype="multipart/form-data" class="mt-4 space-y-3 rounded border border-stone-200 p-4">
                        @csrf
                        <label class="block">Printer
                            <select name="printer_name" required class="mt-1 block w-full rounded border-stone-300">
                                <option value="">Choose a printer</option>
                                @foreach ($site->reported_printers ?? [] as $printer)
                                    <option value="{{ $printer['name'] }}">{{ $printer['display_name'] }} ({{ ucfirst($printer['state']) }})</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">PDF document
                            <input type="file" name="document" accept="application/pdf,.pdf" required class="mt-1 block w-full">
                        </label>
                        @error('printer_name')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        @error('document')<p class="text-sm text-red-700">{{ $message }}</p>@enderror
                        @if (($site->reported_printers ?? []) === [])
                            <p class="text-sm text-stone-600">No printers reported yet. This site must poll the hub before you can queue a job.</p>
                        @endif
                        <button type="submit" @disabled(($site->reported_printers ?? []) === []) class="rounded-md border border-stone-300 px-3 py-2 text-sm disabled:opacity-50">Queue print job</button>
                    </form>
                    @if ($site->connectPrintJobs->isNotEmpty())
                        <div class="mt-3 space-y-2">
                            <h3 class="font-medium">Recent jobs</h3>
                            @foreach ($site->connectPrintJobs as $job)
                                <p class="text-sm">{{ $job->original_filename }} → {{ $job->printer_name }} · {{ str_replace('_', ' ', $job->state->value) }}</p>
                            @endforeach
                        </div>
                    @endif
                @endunless
            @empty
                <p>No sites paired yet.</p>
            @endforelse
        </section>
    </main>
</x-layouts.app>

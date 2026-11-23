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
            @empty
                <p>No sites paired yet.</p>
            @endforelse
        </section>
    </main>
</x-layouts.app>

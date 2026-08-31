<?php

use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
use Livewire\Component;

new class extends Component
{
    /**
     * @var list<array{name: string, displayName: string, state: string}>
     */
    public array $printers = [];

    public ?string $printerDiscoveryError = null;

    public function mount(PrintBackend $printBackend): void
    {
        try {
            $this->printers = array_map(
                fn ($printer): array => [
                    'name' => $printer->name,
                    'displayName' => $printer->displayName,
                    'state' => $printer->state->value,
                ],
                $printBackend->printers(),
            );
        } catch (PrintBackendUnavailable) {
            $this->printerDiscoveryError = 'Printer discovery is unavailable. Check that CUPS is running on this host.';
        }
    }
};
?>

<div class="space-y-6">
    <section class="rounded-lg border border-stone-300 bg-white p-6 shadow-sm">
        <p class="text-sm font-medium text-stone-600">Dashboard</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-950">Printgate is ready.</h1>
        <p class="mt-3 max-w-xl text-sm leading-6 text-stone-600">
            Printing controls will appear here as the local print backend is connected.
        </p>
    </section>

    <section class="rounded-lg border border-stone-300 bg-white p-6 shadow-sm" aria-labelledby="printers-heading">
        <div class="flex items-baseline justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-stone-600">Local CUPS</p>
                <h2 id="printers-heading" class="mt-1 text-lg font-semibold text-stone-950">Printers</h2>
            </div>
            @if ($printerDiscoveryError === null)
                <span class="text-sm text-stone-500">{{ count($printers) }}</span>
            @endif
        </div>

        @if ($printerDiscoveryError !== null)
            <p class="mt-4 text-sm leading-6 text-stone-600" role="status">{{ $printerDiscoveryError }}</p>
        @elseif ($printers === [])
            <p class="mt-4 text-sm leading-6 text-stone-600">No printers are configured in CUPS.</p>
        @else
            <ul class="mt-4 divide-y divide-stone-200" aria-label="Discovered printers">
                @foreach ($printers as $printer)
                    <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-stone-900">{{ $printer['displayName'] }}</p>
                            <p class="truncate text-sm text-stone-500">{{ $printer['name'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">{{ ucfirst($printer['state']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

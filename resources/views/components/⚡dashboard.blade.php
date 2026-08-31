<?php

use App\Models\PrintJob;
use App\Printing\CancelPrintJob;
use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
use App\Printing\Exceptions\PrintSubmissionFailed;
use App\Printing\Exceptions\PrintValidationFailed;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Component;

new class extends Component
{
    /**
     * @var list<array{name: string, displayName: string, state: string}>
     */
    public array $printers = [];

    public ?string $printerDiscoveryError = null;

    /**
     * @var list<array{id: string, originalFilename: string, printerName: ?string, state: string, backendJobId: ?string, createdAt: string, updatedAt: string}>
     */
    public array $jobs = [];

    public ?string $jobActionError = null;

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

        $this->loadJobs();
    }

    public function cancelJob(CancelPrintJob $cancelPrintJob, string $jobId): void
    {
        $this->jobActionError = null;

        try {
            $cancelPrintJob->cancel($jobId);
        } catch (ModelNotFoundException) {
            $this->jobActionError = 'The selected print job could not be found.';
        } catch (PrintValidationFailed|PrintSubmissionFailed $exception) {
            $this->jobActionError = $exception->getMessage();
        }

        $this->loadJobs();
    }

    private function loadJobs(): void
    {
        $this->jobs = PrintJob::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (PrintJob $job): array => [
                'id' => $job->id,
                'originalFilename' => $job->original_filename,
                'printerName' => $job->printer_name,
                'state' => $job->state->value,
                'backendJobId' => $job->backend_job_id,
                'createdAt' => $job->created_at->toDateTimeString(),
                'updatedAt' => $job->updated_at->toDateTimeString(),
            ])
            ->all();
    }
};
?>

<div class="space-y-6">
    <section class="rounded-lg border border-stone-300 bg-white p-6 shadow-sm">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-stone-600">Dashboard</p>
                <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-950">Printgate is ready.</h1>
                <p class="mt-3 max-w-xl text-sm leading-6 text-stone-600">
                    Upload a PDF to create a pending print job for review.
                </p>
            </div>
            <a href="{{ route('print-jobs.create') }}" class="shrink-0 rounded-md bg-stone-950 px-3 py-2 text-sm font-medium text-white hover:bg-stone-800">New print job</a>
        </div>
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

    <section class="rounded-lg border border-stone-300 bg-white p-6 shadow-sm" aria-labelledby="recent-jobs-heading">
        <div class="flex items-baseline justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-stone-600">Printgate</p>
                <h2 id="recent-jobs-heading" class="mt-1 text-lg font-semibold text-stone-950">Recent jobs</h2>
            </div>
            <a href="{{ route('print-jobs.create') }}" class="text-sm font-medium text-stone-700 hover:text-stone-950">New print job</a>
        </div>

        @if ($jobActionError !== null)
            <p class="mt-4 text-sm leading-6 text-red-700" role="alert">{{ $jobActionError }}</p>
        @endif

        @if ($jobs === [])
            <p class="mt-4 text-sm leading-6 text-stone-600">No Printgate jobs have been created yet.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[60rem] text-left text-sm">
                    <thead class="border-b border-stone-200 text-xs font-medium uppercase tracking-wide text-stone-500">
                        <tr>
                            <th class="px-3 py-2">Job ID</th>
                            <th class="px-3 py-2">Document</th>
                            <th class="px-3 py-2">Printer</th>
                            <th class="px-3 py-2">State</th>
                            <th class="px-3 py-2">CUPS job ID</th>
                            <th class="px-3 py-2">Created</th>
                            <th class="px-3 py-2">Updated</th>
                            <th class="px-3 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-200 text-stone-700">
                        @foreach ($jobs as $job)
                            <tr wire:key="job-{{ $job['id'] }}">
                                <td class="whitespace-nowrap px-3 py-3 font-mono text-xs text-stone-900">{{ $job['id'] }}</td>
                                <td class="px-3 py-3 font-medium text-stone-900">{{ $job['originalFilename'] }}</td>
                                <td class="px-3 py-3">{{ $job['printerName'] ?? 'Not selected' }}</td>
                                <td class="px-3 py-3"><span class="rounded-full bg-stone-100 px-2.5 py-1 text-xs font-medium text-stone-700">{{ str($job['state'])->replace('_', ' ')->title() }}</span></td>
                                <td class="whitespace-nowrap px-3 py-3 font-mono text-xs">{{ $job['backendJobId'] ?? 'Not submitted' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs">{{ $job['createdAt'] }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-xs">{{ $job['updatedAt'] }}</td>
                                <td class="px-3 py-3 text-right">
                                    @if ($job['state'] === \App\Printing\PrintJobState::Submitted->value && $job['backendJobId'] !== null)
                                        <button type="button" wire:click="cancelJob('{{ $job['id'] }}')" wire:loading.attr="disabled" class="rounded-md border border-stone-300 px-2.5 py-1.5 text-xs font-medium text-stone-700 hover:border-stone-400 hover:text-stone-950">Cancel</button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

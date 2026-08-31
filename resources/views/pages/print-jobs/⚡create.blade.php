<?php

use App\Models\PrintJob;
use App\Printing\Contracts\PrintBackend;
use App\Printing\Exceptions\PrintBackendUnavailable;
use App\Printing\Exceptions\PrintSubmissionFailed;
use App\Printing\Exceptions\PrintValidationFailed;
use App\Printing\PrintJobState;
use App\Printing\PrintgateSettings;
use App\Printing\SubmitPendingPrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('components.layouts.app')] class extends Component
{
    use WithFileUploads;

    public mixed $document = null;

    public ?string $createdJobId = null;

    public ?string $createdJobFilename = null;

    public string $createdJobState = PrintJobState::Pending->value;

    /**
     * @var list<array{name: string, displayName: string, state: string}>
     */
    public array $printers = [];

    public ?string $selectedPrinterName = null;

    public ?string $printerDiscoveryError = null;

    public ?string $submissionError = null;

    protected function rules(): array
    {
        $settings = app(PrintgateSettings::class);

        return [
            'document' => [
                'required',
                'file',
                'extensions:pdf',
                File::types(['pdf'])->max($settings->maximumUploadMegabytes.'mb'),
            ],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();
        $settings = app(PrintgateSettings::class);
        $originalFilename = basename(str_replace('\\', '/', $validated['document']->getClientOriginalName()));
        $storedFilename = Str::random(40).'.pdf';
        $documentPath = $validated['document']->storeAs(
            path: 'documents',
            name: $storedFilename,
            options: $settings->documentDisk,
        );

        if (! is_string($documentPath)) {
            throw new \RuntimeException('The uploaded document could not be stored.');
        }

        try {
            $job = DB::transaction(fn (): PrintJob => PrintJob::create([
                'state' => PrintJobState::Pending,
                'printer_name' => null,
                'original_filename' => $originalFilename,
                'document_path' => $documentPath,
                'backend_job_id' => null,
            ]));
        } catch (\Throwable $exception) {
            Storage::disk($settings->documentDisk)->delete($documentPath);

            throw $exception;
        }

        $this->createdJobId = $job->id;
        $this->createdJobFilename = $job->original_filename;
        $this->createdJobState = $job->state->value;
        $this->document = null;
    }

    public function loadPrinters(PrintBackend $printBackend): void
    {
        $this->printerDiscoveryError = null;

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

    public function submit(SubmitPendingPrintJob $submitPendingPrintJob): void
    {
        $this->validate([
            'selectedPrinterName' => ['required', 'string'],
        ]);

        try {
            $job = $submitPendingPrintJob->submit($this->createdJobId ?? '', $this->selectedPrinterName);
        } catch (PrintValidationFailed $exception) {
            $this->addError('selectedPrinterName', $exception->getMessage());

            return;
        } catch (PrintSubmissionFailed $exception) {
            $this->submissionError = $exception->getMessage();

            return;
        }

        $this->createdJobState = $job->state->value;
        $this->submissionError = $job->failure_message;
    }
};
?>

<div class="max-w-xl rounded-lg border border-stone-300 bg-white p-6 shadow-sm">
    @if ($createdJobId !== null)
        <p class="text-sm font-medium text-stone-600">Print job</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-950">{{ $createdJobFilename }}</h1>
        @if ($createdJobState === \App\Printing\PrintJobState::Submitted->value)
            <p class="mt-3 text-sm leading-6 text-stone-600">Job {{ $createdJobId }} was submitted to CUPS.</p>
        @elseif ($createdJobState === \App\Printing\PrintJobState::Failed->value)
            <p class="mt-3 text-sm leading-6 text-red-700" role="alert">{{ $submissionError ?? 'CUPS could not accept the document.' }}</p>
        @else
            <p class="mt-3 text-sm leading-6 text-stone-600">Job {{ $createdJobId }} is pending. It has not been submitted to CUPS.</p>

            @if ($printerDiscoveryError !== null)
                <p class="mt-4 text-sm text-red-700" role="alert">{{ $printerDiscoveryError }}</p>
            @elseif ($printers === [])
                <button type="button" wire:click="loadPrinters" wire:loading.attr="disabled" class="mt-4 rounded-md border border-stone-300 px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50 disabled:cursor-not-allowed">Load available printers</button>
            @else
                <form wire:submit="submit" class="mt-6 space-y-4">
                    <div>
                        <label for="printer" class="block text-sm font-medium text-stone-800">Printer</label>
                        <select id="printer" wire:model="selectedPrinterName" class="mt-2 block w-full rounded-md border-stone-300 text-sm text-stone-900">
                            <option value="">Choose a printer</option>
                            @foreach ($printers as $printer)
                                <option value="{{ $printer['name'] }}">{{ $printer['displayName'] }} ({{ ucfirst($printer['state']) }})</option>
                            @endforeach
                        </select>
                        @error('selectedPrinterName')
                            <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    @if ($submissionError !== null)
                        <p class="text-sm text-red-700" role="alert">{{ $submissionError }}</p>
                    @endif

                    <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-stone-950 px-3 py-2 text-sm font-medium text-white hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-500">Submit to printer</button>
                </form>
            @endif
        @endif
        <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-md border border-stone-300 px-3 py-2 text-sm font-medium text-stone-800 hover:bg-stone-50">Back to dashboard</a>
    @else
        <p class="text-sm font-medium text-stone-600">New print job</p>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-stone-950">Upload a PDF</h1>
        <p class="mt-3 text-sm leading-6 text-stone-600">Your document is stored privately while the print job is pending review.</p>

        <form wire:submit="save" class="mt-6 space-y-4">
            <div>
                <label for="document" class="block text-sm font-medium text-stone-800">PDF document</label>
                <input id="document" type="file" wire:model="document" accept="application/pdf,.pdf" class="mt-2 block w-full text-sm text-stone-700 file:mr-4 file:rounded-md file:border-0 file:bg-stone-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-stone-800 hover:file:bg-stone-200">
                @error('document')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" wire:loading.attr="disabled" class="rounded-md bg-stone-950 px-3 py-2 text-sm font-medium text-white hover:bg-stone-800 disabled:cursor-not-allowed disabled:bg-stone-500">Create pending job</button>
        </form>
    @endif
</div>

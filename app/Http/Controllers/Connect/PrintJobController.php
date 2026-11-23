<?php

namespace App\Http\Controllers\Connect;

use App\Connect\ConnectPrintJobState;
use App\Http\Controllers\Controller;
use App\Models\ConnectPrintJob;
use App\Models\ConnectSite;
use App\Models\User;
use App\Printing\PrintgateSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Throwable;

class PrintJobController extends Controller
{
    public function store(Request $request, ConnectSite $site): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->connectSites()->whereKey($site->id)->whereNull('revoked_at')->exists(), 404);
        $settings = app(PrintgateSettings::class);
        $validated = $request->validate([
            'printer_name' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'extensions:pdf', File::types(['pdf'])->max($settings->maximumUploadMegabytes.'mb')],
        ]);
        $printers = $site->reported_printers ?? [];
        abort_unless(collect($printers)->contains(fn (array $printer): bool => $printer['name'] === $validated['printer_name']), 422, 'Choose a printer reported by this site.');

        $file = $validated['document'];
        $stream = fopen($file->getRealPath(), 'rb');
        $signature = is_resource($stream) ? fread($stream, 5) : false;
        if (is_resource($stream)) {
            fclose($stream);
        }
        abort_unless($signature === '%PDF-', 422, 'The uploaded file is not a valid PDF.');

        $storedFilename = Str::random(40).'.pdf';
        $documentPath = $file->storeAs('documents', $storedFilename, $settings->documentDisk);
        abort_unless(is_string($documentPath), 500, 'The document could not be stored.');

        try {
            $job = DB::transaction(function () use ($user, $site, $validated, $file, $documentPath, $settings): ConnectPrintJob {
                $lockedOwner = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
                $lockedSite = ConnectSite::query()
                    ->whereKey($site->id)
                    ->where('owner_id', $lockedOwner->id)
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->firstOrFail();
                abort_unless(collect($lockedSite->reported_printers ?? [])->contains(
                    fn (array $printer): bool => $printer['name'] === $validated['printer_name'],
                ), 422, 'Choose a printer reported by this site.');
                $activeCount = ConnectPrintJob::query()
                    ->where('owner_id', $lockedOwner->id)
                    ->whereIn('state', [ConnectPrintJobState::Queued->value, ConnectPrintJobState::Submitting->value])
                    ->where('expires_at', '>', now())
                    ->count();
                abort_if($activeCount >= (int) config('printgate.connect.max_active_jobs_per_owner', 10), 429, 'Your active print job limit has been reached.');

                return ConnectPrintJob::query()->create([
                    'owner_id' => $lockedOwner->id,
                    'site_id' => $site->id,
                    'printer_name' => $validated['printer_name'],
                    'original_filename' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                    'document_path' => $documentPath,
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'size' => $file->getSize(),
                    'state' => ConnectPrintJobState::Queued,
                    'expires_at' => now()->addMinutes($settings->retentionMinutes),
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($settings->documentDisk)->delete($documentPath);
            throw $exception;
        }

        return redirect()->route('connect.index')->with('status', "Print job {$job->id} queued.");
    }
}

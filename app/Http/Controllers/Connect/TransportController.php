<?php

namespace App\Http\Controllers\Connect;

use App\Connect\ConnectPrintJobState;
use App\Http\Controllers\Controller;
use App\Models\ConnectPrintJob;
use App\Models\ConnectSite;
use App\Printing\PrintgateSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransportController extends Controller
{
    public function poll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'protocol' => ['required', 'integer', 'in:1'],
            'printers' => ['present', 'array', 'max:100'],
            'printers.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'printers.*.display_name' => ['required', 'string', 'max:255'],
            'printers.*.state' => ['required', 'string', 'max:64'],
        ]);
        /** @var ConnectSite $site */
        $site = $request->attributes->get('connect.site');
        $site->forceFill([
            'reported_printers' => $validated['printers'],
            'last_poll_at' => now(),
        ])->save();

        $jobs = $site->connectPrintJobs()
            ->whereIn('state', [ConnectPrintJobState::Queued->value, ConnectPrintJobState::Submitting->value, ConnectPrintJobState::Submitted->value])
            ->where('expires_at', '>', now())
            ->oldest('updated_at')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'printer_name', 'sha256', 'size', 'expires_at', 'state']);

        return response()->json(['jobs' => $jobs->map(fn (ConnectPrintJob $job): array => [
            'id' => $job->id,
            'printer' => $job->printer_name,
            'sha256' => $job->sha256,
            'size' => $job->size,
            'state' => $job->state->value,
            'expires_at' => $job->expires_at->toIso8601String(),
        ])->values()])->header('Cache-Control', 'no-store');
    }

    public function document(Request $request, string $id): StreamedResponse
    {
        /** @var ConnectSite $site */
        $site = $request->attributes->get('connect.site');
        $job = $site->connectPrintJobs()->whereKey($id)->firstOrFail();
        abort_if($job->expires_at->isPast(), 410);
        $disk = Storage::disk(app(PrintgateSettings::class)->documentDisk);
        abort_unless($disk->exists($job->document_path), 410);
        $stream = $disk->readStream($job->document_path);
        abort_unless(is_resource($stream), 410);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) $job->size,
            'Content-Disposition' => 'inline; filename="document.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'state' => ['required', 'string', 'in:queued,submitting,submitted,failed,uncertain,completed_or_gone'],
            'backend_job_id' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);
        /** @var ConnectSite $site */
        $site = $request->attributes->get('connect.site');
        $next = ConnectPrintJobState::from($validated['state']);
        $job = DB::transaction(function () use ($site, $id, $next, $validated): ConnectPrintJob {
            $job = $site->connectPrintJobs()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($job->expires_at->isPast(), 410);
            abort_unless($job->state->canTransitionTo($next), 409, 'Job status cannot move backwards or leave a terminal state.');

            $job->state = $next;
            $job->backend_job_id = $validated['backend_job_id'] ?? $job->backend_job_id;
            $job->touch();

            return $job;
        });

        return response()->json(['id' => $job->id, 'state' => $job->state->value])->header('Cache-Control', 'no-store');
    }
}

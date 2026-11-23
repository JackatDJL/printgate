<?php

namespace App\Connect\SiteAgent;

use App\Printing\Contracts\PrintBackend;
use App\Printing\CupsJobState;
use App\Printing\PrintSubmissionRequest;
use App\Printing\Printer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class PollWorker
{
    public function __construct(
        private readonly CredentialStore $store,
        private readonly HubClient $hub,
        private readonly PrintBackend $backend,
    ) {
        ReceiptDatabase::configure();
    }

    public function poll(): int
    {
        $configuration = $this->store->read();
        $allowed = $configuration['allowed_printers'];
        $printers = array_values(array_filter($this->backend->printers(), fn (Printer $printer): bool => in_array($printer->name, $allowed, true)));
        $payload = ['protocol' => 1, 'printers' => array_map(fn (Printer $printer): array => [
            'name' => $printer->name,
            'display_name' => $printer->displayName,
            'state' => $printer->state->value,
        ], $printers)];
        $baseUrl = $configuration['base_url'];
        $response = $this->hub->response($this->hub->request($baseUrl, $configuration['credential'])
            ->post(rtrim($baseUrl, '/').'/api/connect/v1/poll', $payload));
        $jobs = $response['jobs'] ?? null;
        if (! is_array($jobs)) {
            throw new RuntimeException('The Printgate Hub returned an invalid job list.');
        }

        foreach ($jobs as $job) {
            if (is_array($job)) {
                $this->processJob($configuration, $job);
            }
        }

        return count($jobs);
    }

    /** @param array<string, mixed> $configuration @param array<string, mixed> $job */
    private function processJob(array $configuration, array $job): void
    {
        $jobId = $job['id'] ?? null;
        $printerName = $job['printer'] ?? null;
        if (! is_string($jobId) || ! is_string($printerName)) {
            return;
        }

        $now = now();
        DB::connection(ReceiptDatabase::CONNECTION)->table('connect_site_agent_receipts')->insertOrIgnore([
            'site_id' => $configuration['site_id'],
            'remote_job_id' => $jobId,
            'state' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $receipt = ConnectSiteReceipt::query()->where('site_id', $configuration['site_id'])
            ->where('remote_job_id', $jobId)->first();
        if ($receipt?->state === 'submitting' && $receipt->updated_at->lte(now()->subSeconds(60))) {
            ConnectSiteReceipt::query()->whereKey($receipt->id)->where('state', 'submitting')
                ->where('updated_at', '<=', now()->subSeconds(60))->update(['state' => 'uncertain', 'updated_at' => now()]);
            $receipt->refresh();
        }
        if (! $receipt) {
            return;
        }

        if ($receipt->state === 'queued') {
            $liveConfiguration = $this->store->read();
            if ($liveConfiguration['site_id'] !== $configuration['site_id'] || ! in_array($printerName, $liveConfiguration['allowed_printers'], true)) {
                $this->report($configuration, $jobId, 'failed');
                $receipt->update(['state' => 'failed']);

                return;
            }
            $claimed = ConnectSiteReceipt::query()->whereKey($receipt->id)->where('state', 'queued')->update(['state' => 'submitting']);
            if ($claimed !== 1) {
                $this->replayOutcome($configuration, $jobId, $receipt->fresh());

                return;
            }
            $this->submit($configuration, $job, $printerName, $receipt);
        }

        $this->replayOutcome($configuration, $jobId, $receipt->fresh());
    }

    /** @param array<string, mixed> $configuration @param array<string, mixed> $job */
    private function submit(array $configuration, array $job, string $printerName, ConnectSiteReceipt $receipt): void
    {
        $path = null;
        $submissionStarted = false;
        try {
            $jobId = $receipt->remote_job_id;
            $baseUrl = $configuration['base_url'];
            $response = $this->hub->request($baseUrl, $configuration['credential'])
                ->get(rtrim($baseUrl, '/').'/api/connect/v1/jobs/'.rawurlencode($jobId).'/document'));
            if ($response->status() >= 300 && $response->status() < 400) {
                throw new RuntimeException('The Printgate Hub redirected a document request.');
            }
            $response->throw();
            $bytes = $response->body();
            $expectedHash = $job['sha256'] ?? null;
            $expectedSize = $job['size'] ?? null;
            $expiresAt = $job['expires_at'] ?? null;
            if (! is_string($expectedHash) || ! preg_match('/^[a-f0-9]{64}$/i', $expectedHash)
                || ! is_int($expectedSize) || $expectedSize < 1 || $expectedSize > 104857600
                || ! is_string($expiresAt) || strtotime($expiresAt) === false || strtotime($expiresAt) <= time()
                || strlen($bytes) !== $expectedSize || ! hash_equals(strtolower($expectedHash), hash('sha256', $bytes))
                || ! str_starts_with($bytes, '%PDF-')) {
                throw new RuntimeException('The Printgate Hub document did not pass validation.');
            }
            $liveConfiguration = $this->store->read();
            $currentPrinters = $this->backend->printers();
            $printer = collect($currentPrinters)->first(fn (Printer $candidate): bool => $candidate->name === $printerName);
            if ($liveConfiguration['site_id'] !== $configuration['site_id']
                || ! in_array($printerName, $liveConfiguration['allowed_printers'], true)
                || ! $printer || $printer->state->value !== 'ready') {
                throw new RuntimeException('The selected printer is no longer allowlisted and ready.');
            }

            $path = tempnam(sys_get_temp_dir(), 'printgate-');
            if ($path === false || file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) {
                throw new RuntimeException('Could not securely stage the print document.');
            }
            chmod($path, 0600);
            $submissionStarted = true;
            $backendJob = $this->backend->submit(new PrintSubmissionRequest($printerName, $path));
            $receipt->update(['state' => 'submitted', 'backend_job_id' => $backendJob->backendJobId]);
        } catch (Throwable $exception) {
            $receipt->update(['state' => $submissionStarted ? 'uncertain' : 'failed']);
            $this->report($configuration, $receipt->remote_job_id, $submissionStarted ? 'uncertain' : 'failed');

            return;
        } finally {
            if ($path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function replayOutcome(array $configuration, string $jobId, ConnectSiteReceipt $receipt): void
    {
        $state = $receipt->state;
        if ($state === 'submitted' && $receipt->backend_job_id) {
            try {
                $state = $this->backend->jobState($receipt->backend_job_id) === CupsJobState::CompletedOrGone
                    ? 'completed_or_gone' : 'submitted';
                $receipt->update(['state' => $state]);
            } catch (Throwable) {
                $state = 'submitted';
            }
        }
        $this->report($configuration, $jobId, $state, $receipt->backend_job_id);
    }

    private function report(array $configuration, string $jobId, string $state, ?string $backendJobId = null): void
    {
        $payload = ['state' => $state];
        if ($backendJobId !== null) {
            $payload['backend_job_id'] = $backendJobId;
        }
        $baseUrl = $configuration['base_url'];
        $this->hub->response($this->hub->request($baseUrl, $configuration['credential'])
            ->post(rtrim($baseUrl, '/').'/api/connect/v1/jobs/'.rawurlencode($jobId).'/status', $payload));
    }
}

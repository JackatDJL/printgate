<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Printing\PrintJobState;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PrintJobUploadTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_a_pending_job_and_stores_a_valid_pdf_privately(): void
    {
        Storage::fake('printgate-documents');
        $file = UploadedFile::fake()->createWithContent('quarterly-report.pdf', "%PDF-1.4\n%âãÏÓ\n");

        $component = Livewire::test('pages::print-jobs.create')
            ->set('document', $file)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('createdJobFilename', 'quarterly-report.pdf');

        $job = PrintJob::sole();

        $this->assertSame(PrintJobState::Pending, $job->state);
        $this->assertSame('quarterly-report.pdf', $job->original_filename);
        $this->assertNull($job->printer_name);
        $this->assertNull($job->backend_job_id);
        $this->assertMatchesRegularExpression('/^[0-9a-hjkmnp-tv-z]{26}$/', $job->id);
        $this->assertMatchesRegularExpression('/^documents\/[A-Za-z0-9]{40}\.pdf$/', $job->document_path);
        Storage::disk('printgate-documents')->assertExists($job->document_path);
        $component->assertDontSee($job->document_path);
    }

    public function test_rejects_a_pdf_that_exceeds_the_configured_maximum_size(): void
    {
        Storage::fake('printgate-documents');
        $file = UploadedFile::fake()
            ->createWithContent('large.pdf', '%PDF-1.4')
            ->size((config('printgate.documents.max_upload_megabytes') * 1_000) + 1);

        Livewire::test('pages::print-jobs.create')
            ->set('document', $file)
            ->call('save')
            ->assertHasErrors(['document']);

        $this->assertSame(0, PrintJob::count());
        Storage::disk('printgate-documents')->assertDirectoryEmpty('');
    }

    public function test_rejects_a_file_that_is_not_a_pdf(): void
    {
        Storage::fake('printgate-documents');
        $file = UploadedFile::fake()->createWithContent('notes.txt', 'not a PDF');

        Livewire::test('pages::print-jobs.create')
            ->set('document', $file)
            ->call('save')
            ->assertHasErrors(['document' => 'extensions']);

        $this->assertSame(0, PrintJob::count());
        Storage::disk('printgate-documents')->assertDirectoryEmpty('');
    }

    public function test_uses_a_random_storage_name_and_never_uses_the_client_path(): void
    {
        Storage::fake('printgate-documents');
        $file = UploadedFile::fake()->createWithContent('../../private-report.pdf', '%PDF-1.4');

        Livewire::test('pages::print-jobs.create')
            ->set('document', $file)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('createdJobFilename', 'private-report.pdf');

        $job = PrintJob::sole();

        $this->assertSame('private-report.pdf', $job->original_filename);
        $this->assertStringNotContainsString('private-report', $job->document_path);
        $this->assertStringNotContainsString('..', $job->document_path);
        $this->assertFalse(str_starts_with($job->document_path, public_path()));
        Storage::disk('printgate-documents')->assertExists($job->document_path);
    }

    public function test_assigns_distinct_storage_names_to_uploads_with_the_same_client_filename(): void
    {
        Storage::fake('printgate-documents');

        Livewire::test('pages::print-jobs.create')
            ->set('document', UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4'))
            ->call('save')
            ->assertHasNoErrors();
        Livewire::test('pages::print-jobs.create')
            ->set('document', UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4'))
            ->call('save')
            ->assertHasNoErrors();

        $paths = PrintJob::query()->pluck('document_path')->all();

        $this->assertCount(2, $paths);
        $this->assertNotSame($paths[0], $paths[1]);
    }

    public function test_new_print_job_page_does_not_expose_stored_document_paths(): void
    {
        $response = $this->get(route('print-jobs.create'));

        $response->assertSee('Upload a PDF');
        $response->assertDontSee('printgate-documents');
        $response->assertDontSee('documents/');
    }
}

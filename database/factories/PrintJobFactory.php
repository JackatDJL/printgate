<?php

namespace Database\Factories;

use App\Models\PrintJob;
use App\Printing\PrintJobState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintJob>
 */
class PrintJobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'state' => PrintJobState::Pending,
            'printer_name' => null,
            'original_filename' => 'document.pdf',
            'document_path' => 'documents/'.fake()->uuid().'.pdf',
            'backend_job_id' => null,
            'failure_message' => null,
        ];
    }
}

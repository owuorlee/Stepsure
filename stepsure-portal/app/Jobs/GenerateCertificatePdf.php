<?php

namespace App\Jobs;

use App\Models\Certificate;
use App\Services\CertificatePdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a certificate PDF in the background (used for Excel uploads, where a whole class is issued at once).
 * If no queue worker is running, the PDF is built the first time it is previewed, downloaded or emailed instead.
 */
class GenerateCertificatePdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public Certificate $certificate) {}

    public function handle(CertificatePdf $pdf): void
    {
        $certificate = $this->certificate;

        if (! $certificate->pdf_path || ! Storage::disk('local')->exists($certificate->pdf_path)) {
            $pdf->store($certificate);
        }
    }
}

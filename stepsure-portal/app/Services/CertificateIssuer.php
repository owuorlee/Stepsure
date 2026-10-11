<?php

namespace App\Services;

use App\Jobs\GenerateCertificatePdf;
use App\Mail\CertificateMail;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Issues, updates, revokes, emails and bundles StepSure certificates.
 * Imported past certificates have no PDF here: they exist only so their IDs can be verified.
 */
class CertificateIssuer
{
    public function __construct(private CertificatePdf $pdf) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  bool  $renderNow  false for bulk uploads: the PDF is then built by a queued job (or on first use)
     */
    public function issue(array $data, ?User $issuer = null, bool $renderNow = true): Certificate
    {
        $certificate = Certificate::create([
            ...$data,
            'code' => Certificate::newCode(),
            'certificate_type' => $data['certificate_type'] ?? config('certificates.default_type'),
            'issued_at' => $data['issued_at'] ?? now(),
            'issued_by' => $issuer?->id,
            'source' => 'issued',
            'status' => 'valid',
        ]);

        if ($renderNow) {
            $this->pdf->store($certificate);
        } else {
            GenerateCertificatePdf::dispatch($certificate)->afterCommit();
        }

        return $certificate;
    }

    /**
     * Records a certificate issued before this system (e.g. on Zikoro), keeping its original ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function importPast(array $data, ?User $issuer = null): Certificate
    {
        return Certificate::create([
            ...$data,
            'code' => mb_strtolower($data['code']),
            'certificate_type' => $data['certificate_type'] ?? config('certificates.default_type'),
            'issued_at' => $data['issued_at'] ?? $data['ends_on'] ?? $data['starts_on'],
            'issued_by' => $issuer?->id,
            'source' => 'imported',
            'status' => 'valid',
        ]);
    }

    /** Rebuilds the PDF after a correction (the Certificate ID and QR code stay the same). */
    public function refresh(Certificate $certificate): void
    {
        if (! $certificate->isImported()) {
            $this->pdf->store($certificate);
        }
    }

    public function revoke(Certificate $certificate, string $reason): void
    {
        $certificate->update(['status' => 'revoked', 'revoked_reason' => $reason, 'revoked_at' => now()]);
        $this->refresh($certificate);
    }

    public function restore(Certificate $certificate): void
    {
        $certificate->update(['status' => 'valid', 'revoked_reason' => null, 'revoked_at' => null]);
        $this->refresh($certificate);
    }

    /** Emails the PDF to the participant. Returns false when there is no email address or no PDF to send. */
    public function email(Certificate $certificate): bool
    {
        if (! $certificate->participant_email || $certificate->isImported() || ! $certificate->isValid()) {
            return false;
        }
        if (! $certificate->pdf_path || ! Storage::disk('local')->exists($certificate->pdf_path)) {
            $this->pdf->store($certificate);
        }

        Mail::to($certificate->participant_email, $certificate->participant_name)->send(new CertificateMail($certificate));
        $certificate->forceFill(['emailed_at' => now()])->saveQuietly();

        return true;
    }

    /**
     * Bundles certificate PDFs into a ZIP (e.g. a whole class) and returns its absolute path.
     *
     * @param  Collection<int, Certificate>  $certificates
     */
    public function zip(Collection $certificates): string
    {
        set_time_limit(300); // PDFs not built yet take about a second each
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $path = $disk->path('exports/certificates-'.now()->format('Ymd-His').'-'.Str::random(6).'.zip');

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }
        foreach ($certificates->reject->isImported() as $certificate) {
            if (! $certificate->pdf_path || ! $disk->exists($certificate->pdf_path)) {
                $this->pdf->store($certificate);
            }
            $zip->addFile($disk->path($certificate->pdf_path), $this->downloadName($certificate));
        }
        $zip->close();

        return $path;
    }

    public function downloadName(Certificate $certificate): string
    {
        return Str::slug($certificate->participant_name).'-'.$certificate->code.'.pdf';
    }
}

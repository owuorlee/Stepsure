<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

/** Renders a certificate onto the StepSure artwork (A3 portrait) and stores the PDF on the local disk. */
class CertificatePdf
{
    public function render(Certificate $certificate): string
    {
        $qr = (new Builder(
            writer: new PngWriter,
            data: $certificate->verifyUrl(),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 600,
            margin: 0,
        ))->build();

        $nameSize = $this->fitSize($certificate->participant_name, 60, 22, 32);

        return Pdf::setOption([
            'chroot' => [resource_path(), storage_path()],
            'fontDir' => storage_path('fonts'),
            'fontCache' => storage_path('fonts'),
            'isRemoteEnabled' => false,
            'dpi' => 200,
        ])
            ->loadView('certificates.pdf', [
                'certificate' => $certificate,
                'qr' => $qr->getDataUri(),
                'background' => config('certificates.background'),
                'fonts' => resource_path('fonts'),
                'nameSize' => $nameSize,
                'nameTop' => round(136 + (60 - $nameSize) * 0.42, 1), // keeps smaller names sitting on the underline
                'courseSize' => $this->fitSize($certificate->course_title, 37, 32, 27),
            ])
            ->setPaper(config('certificates.paper'))
            ->output();
    }

    public function store(Certificate $certificate): string
    {
        $path = "certificates/{$certificate->code}.pdf";
        Storage::disk('local')->put($path, $this->render($certificate));
        $certificate->forceFill(['pdf_path' => $path])->saveQuietly();

        return $path;
    }

    /** Shrinks the font size for long text so names and course titles never run off the page. */
    private function fitSize(string $text, float $maxSize, int $comfortableLength, float $minSize): float
    {
        $length = mb_strlen($text);

        return $length <= $comfortableLength ? $maxSize : max($minSize, round($maxSize * $comfortableLength / $length, 1));
    }
}

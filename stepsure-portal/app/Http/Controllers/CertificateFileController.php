<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificateImporter;
use App\Services\CertificateIssuer;
use App\Services\CertificatePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Staff-only file endpoints used by the admin panel (PDF preview and download, Excel templates). */
class CertificateFileController extends Controller
{
    public function pdf(Request $request, Certificate $certificate, CertificatePdf $pdf, CertificateIssuer $issuer): StreamedResponse
    {
        abort_if($certificate->isImported(), 404);

        $disk = Storage::disk('local');
        if (! $certificate->pdf_path || ! $disk->exists($certificate->pdf_path)) {
            $pdf->store($certificate);
        }

        $name = $issuer->downloadName($certificate);

        return $request->boolean('download')
            ? $disk->download($certificate->pdf_path, $name)
            : $disk->response($certificate->pdf_path, $name, ['Content-Type' => 'application/pdf']);
    }

    public function template(Request $request, CertificateImporter $importer): BinaryFileResponse
    {
        $past = $request->boolean('past');

        return response()->download(
            $importer->template($past),
            $past ? 'StepSure past certificates template.xlsx' : 'StepSure certificates template.xlsx',
        );
    }
}

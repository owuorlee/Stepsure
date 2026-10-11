<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VerifyController extends Controller
{
    /** Home page: enter a Certificate ID (or arrive with ?code= from the form). */
    public function index(Request $request): View|RedirectResponse
    {
        $code = trim($request->string('code')->toString());

        if ($code !== '') {
            return redirect()->route('verify.show', preg_replace('/^certificate id:\s*/i', '', $code));
        }

        return view('verify', ['certificate' => null, 'searched' => false, 'code' => '']);
    }

    /** The QR code link. IDs are matched case-insensitively, since people often type them in capitals. */
    public function show(string $code): View
    {
        $certificate = Certificate::where('code', mb_strtolower(trim($code)))->first();

        return view('verify', ['certificate' => $certificate, 'searched' => true, 'code' => $code]);
    }
}

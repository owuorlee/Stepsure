<?php

use App\Http\Controllers\CertificateFileController;
use App\Http\Controllers\VerifyController;
use Illuminate\Support\Facades\Route;

// Public certificate verification (the QR code on every certificate links to /verify/{code})
Route::get('/', [VerifyController::class, 'index'])->name('verify.index');
Route::get('/verify/{code}', [VerifyController::class, 'show'])->middleware('throttle:60,1')->name('verify.show');

// Staff-only files for the admin panel (staff sign in at /admin)
Route::middleware('auth')->prefix('admin-files')->name('admin.certificates.')->group(function () {
    Route::get('/certificates/{certificate}/pdf', [CertificateFileController::class, 'pdf'])->name('pdf');
    Route::get('/certificates/template', [CertificateFileController::class, 'template'])->name('template');
});

<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Services\CertificateIssuer;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

/** Corrections (e.g. a misspelt name). The Certificate ID and QR code stay the same; the PDF is rebuilt. */
class EditCertificate extends EditRecord
{
    protected static string $resource = CertificateResource::class;

    protected static ?string $title = 'Correct certificate details';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(CertificateIssuer::class)->refresh($this->getRecord());
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Saved. The certificate PDF has been updated';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}

<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateResource;
use App\Services\CertificateIssuer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCertificate extends CreateRecord
{
    protected static string $resource = CertificateResource::class;

    protected static ?string $title = 'Issue a certificate';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(CertificateIssuer::class)->issue($data, auth()->user());
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Certificate issued';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}

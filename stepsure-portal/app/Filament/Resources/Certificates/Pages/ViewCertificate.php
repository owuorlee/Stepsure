<?php

namespace App\Filament\Resources\Certificates\Pages;

use App\Filament\Resources\Certificates\CertificateActions;
use App\Filament\Resources\Certificates\CertificateResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCertificate extends ViewRecord
{
    protected static string $resource = CertificateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CertificateActions::download(),
            CertificateActions::email(),
            CertificateActions::verify()->color('gray'),
            ActionGroup::make([
                EditAction::make()->label('Correct details'),
                CertificateActions::revoke(),
                CertificateActions::restore(),
            ]),
        ];
    }
}

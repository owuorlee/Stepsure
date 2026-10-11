<?php

namespace App\Filament\Resources\Certificates;

use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\EditCertificate;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Filament\Resources\Certificates\Pages\ViewCertificate;
use App\Filament\Resources\Certificates\Schemas\CertificateForm;
use App\Filament\Resources\Certificates\Schemas\CertificateInfolist;
use App\Filament\Resources\Certificates\Tables\CertificatesTable;
use App\Models\Certificate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CertificateResource extends Resource
{
    protected static ?string $model = Certificate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'Certificates';

    protected static ?string $recordTitleAttribute = 'participant_name';

    public static function form(Schema $schema): Schema
    {
        return CertificateForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CertificateInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CertificatesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCertificates::route('/'),
            'create' => CreateCertificate::route('/create'),
            'view' => ViewCertificate::route('/{record}'),
            'edit' => EditCertificate::route('/{record}/edit'),
        ];
    }
}

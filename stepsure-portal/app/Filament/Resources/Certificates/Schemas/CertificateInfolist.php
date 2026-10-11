<?php

namespace App\Filament\Resources\Certificates\Schemas;

use App\Models\Certificate;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CertificateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Details')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => $state === 'valid' ? 'success' : 'danger'),
                        TextEntry::make('participant_name')->label('Name'),
                        TextEntry::make('participant_email')->label('Email')->placeholder('None'),
                        TextEntry::make('course_title')->label('Course'),
                        TextEntry::make('starts_on')->label('Dates')->formatStateUsing(fn (Certificate $record): string => $record->dateRange()),
                        TextEntry::make('venue')->placeholder('None'),
                        TextEntry::make('class_code')->label('Class code')->placeholder('None'),
                        TextEntry::make('serial_no')->label('Serial no.')->placeholder('None'),
                        TextEntry::make('code')->label('Certificate ID')->copyable()->fontFamily('mono'),
                        TextEntry::make('issued_at')->label('Issued')->dateTime('j M Y, H:i'),
                        TextEntry::make('issuer.name')->label('Issued by')->placeholder('—'),
                        TextEntry::make('emailed_at')->label('Emailed')->dateTime('j M Y, H:i')->placeholder('Not yet'),
                        TextEntry::make('source')
                            ->label('Origin')
                            ->formatStateUsing(fn (string $state): string => $state === 'imported' ? 'Past certificate (imported)' : 'Issued here'),
                        TextEntry::make('legacy_url')->label('Old verification link')->url(fn (?string $state): ?string => $state, true)->visible(fn (Certificate $record): bool => filled($record->legacy_url)),
                        TextEntry::make('revoked_reason')->label('Revoked because')->visible(fn (Certificate $record): bool => ! $record->isValid()),
                    ]),
                Section::make('Certificate')
                    ->columnSpan(2)
                    ->schema([
                        ViewEntry::make('preview')->hiddenLabel()->view('filament.certificate-preview'),
                    ]),
            ]);
    }
}

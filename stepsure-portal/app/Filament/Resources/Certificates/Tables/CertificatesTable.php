<?php

namespace App\Filament\Resources\Certificates\Tables;

use App\Filament\Resources\Certificates\CertificateActions;
use App\Models\Certificate;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CertificatesTable
{
    public static function configure(Table $table): Table
    {
        $options = fn (string $column): array => Certificate::query()->whereNotNull($column)
            ->distinct()->orderBy($column)->pluck($column, $column)->all();

        return $table
            ->defaultSort('issued_at', 'desc')
            ->recordUrl(fn (Certificate $record): string => route('filament.admin.resources.certificates.view', $record))
            ->columns([
                TextColumn::make('participant_name')
                    ->label('Participant')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn (Certificate $record): string => $record->course_title),
                TextColumn::make('starts_on')
                    ->label('Dates')
                    ->formatStateUsing(fn (Certificate $record): string => $record->dateRange())
                    ->sortable(),
                TextColumn::make('venue')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('class_code')
                    ->label('Class')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('code')
                    ->label('Certificate ID')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => $state === 'valid' ? 'success' : 'danger'),
                TextColumn::make('source')
                    ->label('Origin')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => $state === 'imported' ? 'Past' : 'Issued here')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('emailed_at')
                    ->label('Emailed')
                    ->boolean()
                    ->getStateUsing(fn (Certificate $record): bool => $record->emailed_at !== null)
                    ->toggleable(),
                TextColumn::make('issued_at')
                    ->label('Issued')
                    ->date('j M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('class_code')->label('Class')->options(fn (): array => $options('class_code'))->searchable(),
                SelectFilter::make('course_title')->label('Course')->options(fn (): array => $options('course_title'))->searchable(),
                SelectFilter::make('status')->options(['valid' => 'Valid', 'revoked' => 'Revoked']),
                SelectFilter::make('source')->label('Origin')->options(['issued' => 'Issued here', 'imported' => 'Past (imported)']),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    CertificateActions::download(),
                    CertificateActions::email(),
                    CertificateActions::verify(),
                    EditAction::make()->label('Correct details'),
                    CertificateActions::revoke(),
                    CertificateActions::restore(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    CertificateActions::downloadZip(),
                    CertificateActions::emailSelected(),
                ]),
            ]);
    }
}

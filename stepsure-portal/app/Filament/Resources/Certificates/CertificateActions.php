<?php

namespace App\Filament\Resources\Certificates;

use App\Models\Certificate;
use App\Services\CertificateImporter;
use App\Services\CertificateIssuer;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Actions shared by the certificate list (rows and bulk) and the certificate page. */
class CertificateActions
{
    public static function download(): Action
    {
        return Action::make('download')
            ->label('Download PDF')
            ->icon('heroicon-o-arrow-down-tray')
            ->visible(fn (Certificate $record): bool => ! $record->isImported())
            ->url(fn (Certificate $record): string => route('admin.certificates.pdf', [$record, 'download' => 1]));
    }

    public static function verify(): Action
    {
        return Action::make('verify')
            ->label('Verification page')
            ->icon('heroicon-o-qr-code')
            ->url(fn (Certificate $record): string => $record->verifyUrl(), shouldOpenInNewTab: true);
    }

    public static function email(): Action
    {
        return Action::make('email')
            ->label(fn (Certificate $record): string => $record->emailed_at ? 'Email again' : 'Email to participant')
            ->icon('heroicon-o-envelope')
            ->visible(fn (Certificate $record): bool => $record->participant_email && $record->isValid() && ! $record->isImported())
            ->requiresConfirmation()
            ->modalDescription(fn (Certificate $record): string => "The certificate PDF will be emailed to {$record->participant_email}.")
            ->action(function (Certificate $record, CertificateIssuer $issuer): void {
                $issuer->email($record);
                Notification::make()->success()->title("Emailed to {$record->participant_email}")->send();
            });
    }

    public static function revoke(): Action
    {
        return Action::make('revoke')
            ->label('Revoke')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (Certificate $record): bool => $record->isValid())
            ->requiresConfirmation()
            ->modalDescription('The verification page will show this certificate as revoked. You can restore it later if needed.')
            ->schema([Textarea::make('reason')->label('Reason (internal, not shown publicly)')->required()])
            ->action(function (Certificate $record, array $data, CertificateIssuer $issuer): void {
                $issuer->revoke($record, $data['reason']);
                Notification::make()->success()->title('Certificate revoked')->send();
            });
    }

    public static function restore(): Action
    {
        return Action::make('restore')
            ->label('Restore')
            ->icon('heroicon-o-arrow-uturn-left')
            ->visible(fn (Certificate $record): bool => ! $record->isValid())
            ->requiresConfirmation()
            ->action(function (Certificate $record, CertificateIssuer $issuer): void {
                $issuer->restore($record);
                Notification::make()->success()->title('Certificate restored')->send();
            });
    }

    public static function downloadZip(): BulkAction
    {
        return BulkAction::make('downloadZip')
            ->label('Download PDFs (ZIP)')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->action(fn (Collection $records, CertificateIssuer $issuer): BinaryFileResponse => response()
                ->download($issuer->zip($records), 'stepsure-certificates-'.now()->format('Y-m-d').'.zip')
                ->deleteFileAfterSend())
            ->deselectRecordsAfterCompletion();
    }

    public static function emailSelected(): BulkAction
    {
        return BulkAction::make('emailSelected')
            ->label('Email to participants')
            ->icon('heroicon-o-envelope')
            ->requiresConfirmation()
            ->modalDescription('Each selected participant with an email address receives their certificate PDF. Revoked and past certificates are skipped.')
            ->action(function (Collection $records, CertificateIssuer $issuer): void {
                set_time_limit(300);
                $sent = $records->filter(fn (Certificate $certificate): bool => $issuer->email($certificate))->count();
                $skipped = $records->count() - $sent;
                Notification::make()->success()
                    ->title("{$sent} ".str('certificate')->plural($sent).' emailed')
                    ->body($skipped ? "{$skipped} skipped (no email address, revoked or past certificate)." : null)
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /** Issue a whole class from Excel, or record past certificates so their IDs can be verified. */
    public static function importFromExcel(): Action
    {
        return Action::make('importFromExcel')
            ->label('Issue from Excel')
            ->icon('heroicon-o-table-cells')
            ->color('gray')
            ->modalHeading('Issue certificates from an Excel list')
            ->modalSubmitActionLabel('Check and issue')
            ->schema([
                Text::make(new HtmlString('One row per participant. Required columns: <b>Name</b>, <b>Course</b>, <b>Start date</b>. Optional: Email, End date, Venue, Class code, Serial no. The whole file is checked first: if any row has a problem, nothing is issued and every problem is listed.')),
                Toggle::make('past')
                    ->label('These are past certificates (already issued, e.g. on Zikoro)')
                    ->helperText('Keeps their existing Certificate IDs so they can be verified here. No new PDFs are made. Requires a "Certificate ID" column.'),
                FileUpload::make('file')
                    ->label('Excel (.xlsx) or CSV file')
                    ->disk('local')
                    ->directory('imports')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    ])
                    ->required(),
            ])
            ->extraModalFooterActions([
                Action::make('template')->label('Download template')->color('gray')->url(route('admin.certificates.template')),
                Action::make('pastTemplate')->label('Template for past certificates')->color('gray')->url(route('admin.certificates.template', ['past' => 1])),
            ])
            ->action(function (array $data, CertificateImporter $importer): void {
                $path = Storage::disk('local')->path($data['file']);
                try {
                    $result = $importer->import($path, (bool) ($data['past'] ?? false), auth()->user());
                } finally {
                    Storage::disk('local')->delete($data['file']);
                }

                if ($result['errors'] !== []) {
                    $list = collect($result['errors'])->take(12)
                        ->map(fn (array $problems, int $row): string => "Row {$row}: ".implode(' ', $problems))
                        ->implode('<br>');
                    $more = count($result['errors']) > 12 ? '<br>…and '.(count($result['errors']) - 12).' more rows.' : '';
                    Notification::make()->danger()->persistent()
                        ->title('Nothing was issued: please fix these rows and upload again')
                        ->body(new HtmlString($list.$more))
                        ->send();

                    return;
                }

                $count = $result['created']->count();
                Notification::make()->success()
                    ->title(($data['past'] ?? false) ? "{$count} past ".str('certificate')->plural($count).' recorded' : "{$count} ".str('certificate')->plural($count).' issued')
                    ->body(($data['past'] ?? false) ? 'Their IDs can now be verified.' : 'Select them in the list to download a ZIP or email them.')
                    ->send();
            });
    }
}

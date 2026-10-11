<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Throwable;

/**
 * Reads an Excel (.xlsx) or CSV list and issues one certificate per row, or records past certificates.
 * The whole file is checked first: if any row has a problem, nothing is created and every problem is reported.
 */
class CertificateImporter
{
    /** Column headings accepted for each field (case and spacing are ignored). */
    private const COLUMNS = [
        'participant_name' => ['name', 'participant name', 'full name', 'participant', 'participants name'],
        'participant_email' => ['email', 'email address', 'participant email', 'e-mail'],
        'course_title' => ['course', 'course title', 'course name', 'training'],
        'starts_on' => ['start date', 'starts on', 'start', 'from', 'date from'],
        'ends_on' => ['end date', 'ends on', 'end', 'to', 'date to'],
        'venue' => ['venue', 'location'],
        'class_code' => ['class code', 'class', 'cohort', 'intake'],
        'serial_no' => ['serial no', 'serial', 'serial number', 'ref', 'reference'],
        'certificate_type' => ['certificate type', 'type'],
        'code' => ['certificate id', 'certificate code', 'certificate no', 'id'],
        'issued_at' => ['issue date', 'issued on', 'date issued', 'issued'],
        'legacy_url' => ['verification link', 'old verification link', 'zikoro link', 'link', 'url'],
    ];

    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'j M Y', 'j F Y', 'M j, Y', 'F j, Y'];

    public function __construct(private CertificateIssuer $issuer) {}

    /**
     * @return array{created: Collection<int, Certificate>, errors: array<int, list<string>>, rows: int}
     */
    public function import(string $path, bool $pastCertificates = false, ?User $user = null): array
    {
        $rows = $this->readRows($path);
        $errors = [];
        $records = [];
        $seenCodes = [];

        foreach ($rows as $line => $row) {
            [$data, $problems] = $this->validate($row, $pastCertificates);
            if ($pastCertificates && isset($data['code'])) {
                if (isset($seenCodes[$data['code']])) {
                    $problems[] = "Certificate ID {$data['code']} also appears on row {$seenCodes[$data['code']]}.";
                }
                $seenCodes[$data['code']] ??= $line;
            }
            $problems ? $errors[$line] = $problems : $records[$line] = $data;
        }

        if ($rows === []) {
            $errors[1] = ['The file has no rows under the heading row.'];
        }
        if ($errors !== []) {
            return ['created' => collect(), 'errors' => $errors, 'rows' => count($rows)];
        }

        $created = DB::transaction(fn () => collect($records)->map(fn (array $data) => $pastCertificates
            ? $this->issuer->importPast($data, $user)
            : $this->issuer->issue($data, $user, renderNow: false))->values());

        return ['created' => $created, 'errors' => [], 'rows' => count($rows)];
    }

    /** Builds a ready-to-fill Excel template and returns its absolute path. */
    public function template(bool $pastCertificates = false): string
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory('exports');
        $path = $disk->path('exports/'.($pastCertificates ? 'past-certificates-template.xlsx' : 'certificates-template.xlsx'));

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        if ($pastCertificates) {
            $writer->addRow(Row::fromValues(['Name', 'Course', 'Start date', 'End date', 'Venue', 'Class code', 'Serial no', 'Certificate ID', 'Issue date', 'Verification link']));
            $writer->addRow(Row::fromValues(['Jane Wanjiku', 'Data Analytics and AI in Finance', '2025-08-04', '2025-08-08', 'Nairobi Safari Club', 'SOM 3/2025', 'IA 2/2025', '3f9a2c7d1b84', '2025-08-08', 'https://credentials.zikoro.com/credentials/verify/certificate/3f9a2c7d1b84']));
        } else {
            $writer->addRow(Row::fromValues(['Name', 'Email', 'Course', 'Start date', 'End date', 'Venue', 'Class code', 'Serial no']));
            $writer->addRow(Row::fromValues(['Jane Wanjiku', 'jane@example.com', 'Data Analytics and AI in Finance', '2026-08-03', '2026-08-07', 'Nairobi Safari Club', 'SOM 3/2026', 'IA 2/2026']));
        }
        $writer->close();

        return $path;
    }

    /**
     * Reads the first sheet. Keys are spreadsheet row numbers (the heading is row 1), values are field => cell.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRows(string $path): array
    {
        $reader = str_ends_with(mb_strtolower($path), '.csv') ? new CsvReader : new XlsxReader;
        $reader->open($path);

        $fields = null;
        $rows = [];
        $line = 0;
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $line++;
                $cells = $row->toArray();
                if ($fields === null) {
                    $fields = array_map(fn ($heading) => $this->fieldFor((string) $heading), $cells);

                    continue;
                }
                if (collect($cells)->every(fn ($cell) => $cell === null || trim((string) ($cell instanceof DateTimeInterface ? 'x' : $cell)) === '')) {
                    continue;
                }
                $values = [];
                foreach ($fields as $index => $field) {
                    if ($field !== null) {
                        $values[$field] = $cells[$index] ?? null;
                    }
                }
                $rows[$line] = $values;
            }
            break;
        }
        $reader->close();

        return $rows;
    }

    private function fieldFor(string $heading): ?string
    {
        $heading = trim(preg_replace('/\s+/', ' ', mb_strtolower(str_replace(['_', '.', ':', "'"], ' ', $heading))));
        foreach (self::COLUMNS as $field => $aliases) {
            if (in_array($heading, $aliases, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validate(array $row, bool $pastCertificates): array
    {
        $text = fn (string $field): ?string => ($value = trim((string) ($row[$field] ?? ''))) === '' ? null : $value;
        $problems = [];

        $data = array_filter([
            'participant_name' => $text('participant_name'),
            'participant_email' => $text('participant_email'),
            'course_title' => $text('course_title'),
            'venue' => $text('venue'),
            'class_code' => $text('class_code'),
            'serial_no' => $text('serial_no'),
            'certificate_type' => $text('certificate_type'),
        ], fn ($value) => $value !== null);

        foreach (['participant_name' => 'Name', 'course_title' => 'Course'] as $field => $label) {
            if (! isset($data[$field])) {
                $problems[] = "{$label} is missing.";
            }
        }
        if (isset($data['participant_email']) && ! filter_var($data['participant_email'], FILTER_VALIDATE_EMAIL)) {
            $problems[] = "Email \"{$data['participant_email']}\" is not a valid address.";
        }

        $start = $this->date($row['starts_on'] ?? null);
        $end = $this->date($row['ends_on'] ?? null);
        if (! $start) {
            $problems[] = filled($row['starts_on'] ?? null) ? 'Start date is not a recognised date (use e.g. 2026-08-03 or 03/08/2026).' : 'Start date is missing.';
        }
        if (filled($row['ends_on'] ?? null) && ! $end) {
            $problems[] = 'End date is not a recognised date (use e.g. 2026-08-07 or 07/08/2026).';
        }
        if ($start && $end && $end->lt($start)) {
            $problems[] = 'End date is before the start date.';
        }
        $data['starts_on'] = $start?->toDateString();
        $data['ends_on'] = $end?->toDateString();

        if ($pastCertificates) {
            $code = $text('code');
            if (! $code) {
                $problems[] = 'Certificate ID is missing.';
            } elseif (! preg_match('/^[A-Za-z0-9\-\/]{4,40}$/', $code)) {
                $problems[] = "Certificate ID \"{$code}\" contains characters that are not allowed.";
            } elseif (Certificate::where('code', mb_strtolower($code))->exists()) {
                $problems[] = "Certificate ID {$code} is already in the system.";
            }
            $data['code'] = $code ? mb_strtolower($code) : null;
            $issued = $this->date($row['issued_at'] ?? null);
            $data['issued_at'] = $issued?->toDateString();
            $data['legacy_url'] = $text('legacy_url');
        } elseif ($start && isset($data['participant_name'], $data['course_title'])) {
            $existing = Certificate::where('participant_name', $data['participant_name'])
                ->where('course_title', $data['course_title'])->whereDate('starts_on', $start)->value('code');
            if ($existing) {
                $problems[] = "A certificate for this person and course already exists (ID {$existing}).";
            }
        }

        return [array_filter($data, fn ($value) => $value !== null), $problems];
    }

    private function date(mixed $value): ?CarbonInterface
    {
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }
        if (is_numeric($value) && $value > 20000 && $value < 80000) {
            return Carbon::create(1899, 12, 30)->addDays((int) $value);
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $value);
        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }
}

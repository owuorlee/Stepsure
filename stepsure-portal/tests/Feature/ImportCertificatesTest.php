<?php

namespace Tests\Feature;

use App\Jobs\GenerateCertificatePdf;
use App\Models\Certificate;
use App\Services\CertificateImporter;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ImportCertificatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * Writes rows to a temporary spreadsheet (.xlsx or .csv) and returns its path.
     *
     * @param  list<list<mixed>>  $rows
     */
    private function sheet(array $rows, string $extension = 'xlsx'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cert').'.'.$extension;
        if ($extension === 'csv') {
            $handle = fopen($path, 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);

            return $path;
        }

        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return $path;
    }

    public function test_a_class_list_issues_one_certificate_per_row(): void
    {
        $path = $this->sheet([
            ['Name', 'Email', 'Course', 'Start date', 'End date', 'Venue', 'Class code', 'Serial no'],
            ['Amani Otieno', 'amani@example.com', 'Data Analytics and AI in Finance', '03/08/2026', '07/08/2026', 'Nairobi Safari Club', 'SOM 3/2026', 'IA 2/2026'],
            ['Jane Wanjiku', '', 'Data Analytics and AI in Finance', new DateTimeImmutable('2026-08-03'), new DateTimeImmutable('2026-08-07'), 'Nairobi Safari Club', 'SOM 3/2026', 'IA 3/2026'],
            ['', '', '', '', '', '', '', ''],
        ]);

        $result = app(CertificateImporter::class)->import($path);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['created']);
        $amani = Certificate::where('participant_name', 'Amani Otieno')->sole();
        $this->assertSame('2026-08-03', $amani->starts_on->toDateString());
        $this->assertSame('2026-08-07', $amani->ends_on->toDateString());
        $this->assertSame('SOM 3/2026', $amani->class_code);
        Storage::disk('local')->assertExists($amani->pdf_path);
        $this->assertNull(Certificate::where('participant_name', 'Jane Wanjiku')->value('participant_email'));
    }

    public function test_bulk_pdfs_are_built_in_the_background(): void
    {
        Queue::fake();
        $path = $this->sheet([
            ['Name', 'Course', 'Start date'],
            ['Amani Otieno', 'Data Analytics', '2026-08-03'],
            ['Jane Wanjiku', 'Data Analytics', '2026-08-03'],
        ]);

        app(CertificateImporter::class)->import($path);

        Queue::assertPushed(GenerateCertificatePdf::class, 2);
        $this->assertSame(2, Certificate::whereNull('pdf_path')->count());
    }

    public function test_csv_files_and_other_heading_names_work(): void
    {
        $path = $this->sheet([
            ['Full name', 'Course title', 'From', 'To'],
            ['Amani Otieno', 'GIS and Remote Sensing', '2026-09-07', '2026-09-11'],
        ], 'csv');

        $result = app(CertificateImporter::class)->import($path);

        $this->assertSame([], $result['errors']);
        $this->assertSame('GIS and Remote Sensing', Certificate::sole()->course_title);
    }

    public function test_any_bad_row_stops_the_whole_import_and_reports_every_problem(): void
    {
        $path = $this->sheet([
            ['Name', 'Email', 'Course', 'Start date', 'End date'],
            ['Amani Otieno', 'amani@example.com', 'Data Analytics', '2026-08-03', '2026-08-07'],
            ['', 'not-an-email', 'Data Analytics', 'next week', '2026-08-07'],
            ['Jane Wanjiku', '', 'Data Analytics', '2026-08-07', '2026-08-03'],
        ]);

        $result = app(CertificateImporter::class)->import($path);

        $this->assertSame(0, Certificate::count());
        $this->assertSame([3, 4], array_keys($result['errors']));
        $this->assertContains('Name is missing.', $result['errors'][3]);
        $this->assertContains('Email "not-an-email" is not a valid address.', $result['errors'][3]);
        $this->assertContains('End date is before the start date.', $result['errors'][4]);
    }

    public function test_the_same_list_cannot_be_issued_twice(): void
    {
        $rows = [
            ['Name', 'Course', 'Start date'],
            ['Amani Otieno', 'Data Analytics', '2026-08-03'],
        ];
        $importer = app(CertificateImporter::class);
        $importer->import($this->sheet($rows));

        $second = $importer->import($this->sheet($rows));

        $this->assertSame(1, Certificate::count());
        $this->assertStringContainsString('already exists', $second['errors'][2][0]);
    }

    public function test_past_certificates_keep_their_ids_and_get_no_new_pdf(): void
    {
        $path = $this->sheet([
            ['Name', 'Course', 'Start date', 'End date', 'Certificate ID', 'Issue date', 'Verification link'],
            ['Jacinta Example', 'Project Management', '2025-09-01', '2025-09-05', '3F9A2C7D1B84', '2025-09-05', 'https://credentials.zikoro.com/credentials/verify/certificate/3f9a2c7d1b84'],
        ]);

        $result = app(CertificateImporter::class)->import($path, pastCertificates: true);

        $this->assertSame([], $result['errors']);
        $certificate = Certificate::sole();
        $this->assertSame('3f9a2c7d1b84', $certificate->code);
        $this->assertTrue($certificate->isImported());
        $this->assertNull($certificate->pdf_path);
        $this->assertSame('2025-09-05', $certificate->issued_at->toDateString());
        $this->get($certificate->verifyUrl())->assertSee('Jacinta Example');
    }

    public function test_past_certificates_need_an_unused_id(): void
    {
        Certificate::factory()->create(['code' => '3f9a2c7d1b84']);
        $path = $this->sheet([
            ['Name', 'Course', 'Start date', 'Certificate ID'],
            ['Jacinta Example', 'Project Management', '2025-09-01', '3f9a2c7d1b84'],
            ['Brian Example', 'Project Management', '2025-09-01', ''],
            ['Grace Example', 'Project Management', '2025-09-01', 'abc123abc123'],
            ['Mary Example', 'Project Management', '2025-09-01', 'abc123abc123'],
        ]);

        $result = app(CertificateImporter::class)->import($path, pastCertificates: true);

        $this->assertSame(1, Certificate::count());
        $this->assertStringContainsString('already in the system', $result['errors'][2][0]);
        $this->assertContains('Certificate ID is missing.', $result['errors'][3]);
        $this->assertStringContainsString('also appears on row 4', $result['errors'][5][0]);
    }

    public function test_templates_can_be_downloaded_and_imported_as_they_are(): void
    {
        $importer = app(CertificateImporter::class);

        $result = $importer->import($importer->template());

        $this->assertSame([], $result['errors']);
        $this->assertSame('Jane Wanjiku', Certificate::sole()->participant_name);
    }
}

<?php

namespace Tests\Feature;

use App\Mail\CertificateMail;
use App\Models\Certificate;
use App\Models\User;
use App\Services\CertificateIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class IssueCertificateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    /**
     * @return array<string, mixed>
     */
    private function details(array $overrides = []): array
    {
        return $overrides + [
            'participant_name' => 'Amani Otieno',
            'participant_email' => 'amani@example.com',
            'course_title' => 'Data Analytics and AI in Finance',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-07',
            'venue' => 'Nairobi Safari Club',
            'class_code' => 'SOM 3/2026',
            'serial_no' => 'IA 2/2026',
        ];
    }

    public function test_issuing_creates_a_unique_id_and_a_pdf(): void
    {
        $staff = User::factory()->create();

        $certificate = app(CertificateIssuer::class)->issue($this->details(), $staff);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $certificate->code);
        $this->assertSame('Participation', $certificate->certificate_type);
        $this->assertSame($staff->id, $certificate->issued_by);
        Storage::disk('local')->assertExists($certificate->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($certificate->pdf_path));
    }

    public function test_every_certificate_gets_a_different_id(): void
    {
        $issuer = app(CertificateIssuer::class);

        $first = $issuer->issue($this->details());
        $second = $issuer->issue($this->details(['participant_name' => 'Jane Wanjiku']));

        $this->assertNotSame($first->code, $second->code);
    }

    public function test_certificate_is_emailed_with_its_pdf(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->details());

        $this->assertTrue(app(CertificateIssuer::class)->email($certificate));

        Mail::assertSent(CertificateMail::class, fn (CertificateMail $mail): bool => $mail->hasTo('amani@example.com')
            && count($mail->attachments()) === 1);
        $this->assertNotNull($certificate->fresh()->emailed_at);
    }

    public function test_certificates_without_email_or_revoked_are_not_emailed(): void
    {
        $issuer = app(CertificateIssuer::class);
        $noEmail = $issuer->issue($this->details(['participant_email' => null]));
        $revoked = $issuer->issue($this->details(['participant_name' => 'Jane Wanjiku']));
        $issuer->revoke($revoked, 'Issued in error');

        $this->assertFalse($issuer->email($noEmail));
        $this->assertFalse($issuer->email($revoked->fresh()));
        Mail::assertNothingSent();
    }

    public function test_revoking_and_restoring(): void
    {
        $issuer = app(CertificateIssuer::class);
        $certificate = $issuer->issue($this->details());

        $issuer->revoke($certificate, 'Issued in error');
        $this->assertFalse($certificate->fresh()->isValid());

        $issuer->restore($certificate->fresh());
        $this->assertTrue($certificate->fresh()->isValid());
    }

    public function test_zip_contains_one_pdf_per_certificate(): void
    {
        $issuer = app(CertificateIssuer::class);
        $certificates = collect([
            $issuer->issue($this->details()),
            $issuer->issue($this->details(['participant_name' => 'Jane Wanjiku'])),
            Certificate::factory()->imported()->create(),
        ]);

        $zip = new ZipArchive;
        $zip->open($issuer->zip($certificates));

        $this->assertSame(2, $zip->numFiles);
        $this->assertStringStartsWith('amani-otieno-', $zip->getNameIndex(0));
        $zip->close();
    }

    public function test_long_names_and_course_titles_still_render(): void
    {
        $certificate = app(CertificateIssuer::class)->issue($this->details([
            'participant_name' => 'Dr. Wanjiru Kamau-Ochieng Mutiso Achieng',
            'course_title' => 'Monitoring, Evaluation, Accountability and Learning (MEAL) for Humanitarian Programmes',
            'venue' => null, 'class_code' => null, 'serial_no' => null,
        ]));

        Storage::disk('local')->assertExists($certificate->pdf_path);
    }
}

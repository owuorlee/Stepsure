<?php

namespace Tests\Feature;

use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\EditCertificate;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\User;
use App\Services\CertificateIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->staff = User::factory()->create();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $certificate = Certificate::factory()->create();

        $this->get('/admin/certificates')->assertRedirect('/admin/login');
        $this->get(route('admin.certificates.pdf', $certificate))->assertRedirect('/admin/login');
        $this->get(route('admin.certificates.template'))->assertRedirect('/admin/login');
    }

    public function test_staff_screens_load(): void
    {
        $certificate = app(CertificateIssuer::class)->issue(Certificate::factory()->raw(['code' => null]));
        $this->actingAs($this->staff);

        $this->get('/admin')->assertOk();
        $this->get('/admin/certificates')->assertOk()->assertSee($certificate->participant_name);
        $this->get('/admin/certificates/create')->assertOk();
        $this->get("/admin/certificates/{$certificate->id}")->assertOk()->assertSee($certificate->code);
        $this->get("/admin/certificates/{$certificate->id}/edit")->assertOk();
    }

    public function test_staff_issue_a_certificate_from_the_form(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(CreateCertificate::class)
            ->fillForm([
                'participant_name' => 'Amani Otieno',
                'participant_email' => 'amani@example.com',
                'course_title' => 'Data Analytics and AI in Finance',
                'starts_on' => '2026-08-03',
                'ends_on' => '2026-08-07',
                'venue' => 'Nairobi Safari Club',
                'class_code' => 'SOM 3/2026',
                'serial_no' => 'IA 2/2026',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $certificate = Certificate::sole();
        $this->assertSame($this->staff->id, $certificate->issued_by);
        Storage::disk('local')->assertExists($certificate->pdf_path);
    }

    public function test_end_date_cannot_be_before_start_date(): void
    {
        $this->actingAs($this->staff);

        Livewire::test(CreateCertificate::class)
            ->fillForm(['participant_name' => 'Amani Otieno', 'course_title' => 'Data Analytics', 'starts_on' => '2026-08-07', 'ends_on' => '2026-08-03'])
            ->call('create')
            ->assertHasFormErrors(['ends_on']);
    }

    public function test_correcting_a_name_keeps_the_same_id(): void
    {
        $certificate = app(CertificateIssuer::class)->issue(Certificate::factory()->raw(['participant_name' => 'Amni Otieno']));
        $this->actingAs($this->staff);

        Livewire::test(EditCertificate::class, ['record' => $certificate->getRouteKey()])
            ->fillForm(['participant_name' => 'Amani Otieno'])
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $certificate->fresh();
        $this->assertSame('Amani Otieno', $fresh->participant_name);
        $this->assertSame($certificate->code, $fresh->code);
    }

    public function test_staff_revoke_from_the_list(): void
    {
        $certificate = app(CertificateIssuer::class)->issue(Certificate::factory()->raw());
        $this->actingAs($this->staff);

        Livewire::test(ListCertificates::class)
            ->callTableAction('revoke', $certificate, ['reason' => 'Issued in error']);

        $this->assertSame('revoked', $certificate->fresh()->status);
    }

    public function test_staff_can_preview_and_download_the_pdf_and_templates(): void
    {
        $certificate = app(CertificateIssuer::class)->issue(Certificate::factory()->raw(['participant_name' => 'Amani Otieno']));
        $this->actingAs($this->staff);

        $this->get(route('admin.certificates.pdf', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('admin.certificates.pdf', [$certificate, 'download' => 1]))->assertDownload("amani-otieno-{$certificate->code}.pdf");
        $this->get(route('admin.certificates.template'))->assertDownload('StepSure certificates template.xlsx');
        $this->get(route('admin.certificates.template', ['past' => 1]))->assertDownload('StepSure past certificates template.xlsx');
    }
}

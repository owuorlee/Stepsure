<?php

namespace Tests\Feature;

use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_offers_the_verification_form(): void
    {
        $this->get('/')->assertOk()->assertSee('Verify a certificate')->assertSee('name="code"', false);
    }

    public function test_qr_link_shows_a_valid_certificate_for_the_right_person(): void
    {
        $certificate = Certificate::factory()->create(['participant_name' => 'Amani Otieno', 'course_title' => 'Data Analytics and AI in Finance']);

        $this->get($certificate->verifyUrl())
            ->assertOk()
            ->assertSee('Valid certificate')
            ->assertSee('Amani Otieno')
            ->assertSee('Data Analytics and AI in Finance')
            ->assertSee($certificate->code);
    }

    public function test_ids_typed_in_capitals_still_match(): void
    {
        $certificate = Certificate::factory()->create(['code' => '3f9a2c7d1b84']);

        $this->get(route('verify.show', '3F9A2C7D1B84'))->assertOk()->assertSee('Valid certificate');
        $this->assertSame($certificate->code, '3f9a2c7d1b84');
    }

    public function test_form_accepts_the_id_with_its_printed_label(): void
    {
        $this->get('/?code=Certificate+ID:+3f9a2c7d1b84')->assertRedirect(route('verify.show', '3f9a2c7d1b84'));
    }

    public function test_revoked_certificates_are_shown_as_revoked(): void
    {
        $certificate = Certificate::factory()->revoked()->create();

        $this->get($certificate->verifyUrl())->assertOk()->assertSee('has been revoked')->assertDontSee('Valid certificate');
    }

    public function test_unknown_ids_are_reported(): void
    {
        $this->get(route('verify.show', 'doesnotexist'))->assertOk()->assertSee('No certificate found');
    }

    public function test_imported_past_certificates_verify(): void
    {
        $certificate = Certificate::factory()->imported()->create(['participant_name' => 'Jacinta Example']);

        $this->get($certificate->verifyUrl())->assertOk()->assertSee('Valid certificate')->assertSee('Jacinta Example');
    }

    public function test_internal_details_are_not_shown_publicly(): void
    {
        $certificate = Certificate::factory()->revoked()->create(['participant_email' => 'private@example.com', 'revoked_reason' => 'Internal note']);

        $this->get($certificate->verifyUrl())->assertDontSee('private@example.com')->assertDontSee('Internal note');
    }
}

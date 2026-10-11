<?php

namespace Database\Seeders;

use App\Services\CertificateIssuer;
use Illuminate\Database\Seeder;

/**
 * Local demo data for showing the portal to StepSure. All people are fictional.
 * Run with: php artisan db:seed --class=DemoCertificatesSeeder (refuses to run in production).
 */
class DemoCertificatesSeeder extends Seeder
{
    public function run(CertificateIssuer $issuer): void
    {
        if (app()->isProduction()) {
            return;
        }

        $class = ['course_title' => 'Data Analytics and AI in Finance', 'starts_on' => '2026-08-03', 'ends_on' => '2026-08-07',
            'venue' => 'Nairobi Safari Club', 'class_code' => 'SOM 3/2026'];

        $issuer->issue($class + ['participant_name' => 'Demo Participant One', 'participant_email' => 'demo.one@example.com', 'serial_no' => 'IA 1/2026']);
        $issuer->issue($class + ['participant_name' => 'Demo Participant Two', 'serial_no' => 'IA 2/2026']);
        $revoked = $issuer->issue($class + ['participant_name' => 'Demo Participant Three', 'serial_no' => 'IA 3/2026']);
        $issuer->revoke($revoked, 'Demo: issued in error');

        $issuer->issue([
            'participant_name' => 'Demo Participant Four', 'course_title' => 'GIS and Remote Sensing for Climate Adaptation',
            'starts_on' => '2026-09-28', 'ends_on' => '2026-10-02', 'venue' => 'Online', 'class_code' => 'GIS 9/2026', 'serial_no' => 'IA 4/2026',
        ]);

        $issuer->importPast([
            'participant_name' => 'Demo Past Participant', 'course_title' => 'Project Management for Development Programmes',
            'starts_on' => '2025-09-01', 'ends_on' => '2025-09-05', 'code' => 'd3m0a1b2c3d4',
            'legacy_url' => 'https://credentials.zikoro.com/credentials/verify/certificate/d3m0a1b2c3d4',
        ]);
    }
}

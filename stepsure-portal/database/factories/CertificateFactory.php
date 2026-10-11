<?php

namespace Database\Factories;

use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'code' => bin2hex(random_bytes(6)),
            'participant_name' => fake()->name(),
            'participant_email' => fake()->safeEmail(),
            'course_title' => fake()->randomElement([
                'Data Analytics and AI in Finance',
                'Project Management for Development Programmes',
                'GIS and Remote Sensing for Climate Adaptation',
            ]),
            'certificate_type' => 'Participation',
            'starts_on' => $start,
            'ends_on' => (clone $start)->modify('+4 days'),
            'venue' => 'Nairobi Safari Club',
            'class_code' => 'SOM 3/2026',
            'serial_no' => 'IA 2/2026',
            'issued_at' => now(),
            'source' => 'issued',
            'status' => 'valid',
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => 'revoked', 'revoked_reason' => 'Issued in error', 'revoked_at' => now()]);
    }

    public function imported(): static
    {
        return $this->state(fn () => ['source' => 'imported', 'legacy_url' => 'https://credentials.zikoro.com/credentials/verify/certificate/'.bin2hex(random_bytes(6))]);
    }
}

<?php

namespace Tests\Unit;

use App\Models\Certificate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateDateRangeTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, string}>
     */
    public static function ranges(): array
    {
        return [
            'same month' => ['2026-08-03', '2026-08-07', '3rd – 7th August, 2026'],
            'across months' => ['2026-07-28', '2026-08-01', '28th July – 1st August, 2026'],
            'across years' => ['2026-12-28', '2027-01-02', '28th December, 2026 – 2nd January, 2027'],
            'one day' => ['2026-08-21', null, '21st August, 2026'],
            'same start and end' => ['2026-08-22', '2026-08-22', '22nd August, 2026'],
            'teens use th' => ['2026-08-11', '2026-08-13', '11th – 13th August, 2026'],
        ];
    }

    #[DataProvider('ranges')]
    public function test_dates_are_written_in_stepsure_style(string $start, ?string $end, string $expected): void
    {
        $certificate = new Certificate(['starts_on' => $start, 'ends_on' => $end]);

        $this->assertSame($expected, $certificate->dateRange());
    }

    public function test_pdf_version_puts_ordinals_in_superscript(): void
    {
        $certificate = new Certificate(['starts_on' => '2026-08-03', 'ends_on' => '2026-08-07']);

        $this->assertSame('3<sup>rd</sup> – 7<sup>th</sup> August, 2026', $certificate->dateRange(html: true));
    }
}

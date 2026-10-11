<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A StepSure certificate. The code is the "Certificate ID" printed on the certificate and encoded in its QR link,
 * so a certificate can only ever verify as the person it was issued to.
 */
#[Fillable([
    'code', 'participant_name', 'participant_email', 'course_title', 'certificate_type', 'starts_on', 'ends_on',
    'venue', 'class_code', 'serial_no', 'issued_at', 'issued_by', 'source', 'legacy_url', 'pdf_path', 'emailed_at',
    'status', 'revoked_reason', 'revoked_at',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'issued_at' => 'datetime',
            'emailed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** A new random Certificate ID in StepSure's existing style: 12 lowercase hex characters, e.g. 3f9a2c7d1b84. */
    public static function newCode(): string
    {
        do {
            $code = bin2hex(random_bytes(6));
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public function verifyUrl(): string
    {
        return route('verify.show', $this->code);
    }

    public function isValid(): bool
    {
        return $this->status === 'valid';
    }

    public function isImported(): bool
    {
        return $this->source === 'imported';
    }

    /**
     * Course dates in StepSure's style: "3rd – 7th August, 2026", "28th July – 1st August, 2026"
     * or "28th December, 2026 – 2nd January, 2027". With $html, ordinals are wrapped in <sup> for the PDF.
     */
    public function dateRange(bool $html = false): string
    {
        $day = function (CarbonInterface $date) use ($html): string {
            $suffix = $date->format('S');

            return $date->format('j').($html ? "<sup>{$suffix}</sup>" : $suffix);
        };
        $start = $this->starts_on;
        $end = $this->ends_on;

        if ($end === null || $end->isSameDay($start)) {
            return $day($start).' '.$start->format('F, Y');
        }
        if ($start->year !== $end->year) {
            return $day($start).' '.$start->format('F, Y').' – '.$day($end).' '.$end->format('F, Y');
        }
        if ($start->month !== $end->month) {
            return $day($start).' '.$start->format('F').' – '.$day($end).' '.$end->format('F, Y');
        }

        return $day($start).' – '.$day($end).' '.$end->format('F, Y');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}

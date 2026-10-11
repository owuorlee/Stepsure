<?php

namespace App\Mail;

use App\Models\Certificate;
use App\Services\CertificateIssuer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificateMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Certificate $certificate) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your certificate: {$this->certificate->course_title}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.certificate');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->certificate->pdf_path)
                ->as(app(CertificateIssuer::class)->downloadName($this->certificate))
                ->withMime('application/pdf'),
        ];
    }
}

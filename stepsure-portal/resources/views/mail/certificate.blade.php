<x-mail::message>
# Congratulations, {{ $certificate->participant_name }}

Thank you for attending **{{ $certificate->course_title }}** ({{ $certificate->dateRange() }}). Your certificate is attached as a PDF.

Each certificate has its own QR code and Certificate ID (**{{ $certificate->code }}**), so employers and institutions can confirm it is genuine:

<x-mail::button :url="$certificate->verifyUrl()">
Verify certificate
</x-mail::button>

Kind regards,<br>
{{ config('certificates.issuer') }}
</x-mail::message>

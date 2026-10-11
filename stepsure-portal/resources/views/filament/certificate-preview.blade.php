@php($certificate = $getRecord())
@if ($certificate->isImported())
  <p style="color: #6b7280; font-size: 14px;">This is a past certificate recorded for verification only. Its original PDF was issued outside this system.</p>
@else
  <iframe src="{{ route('admin.certificates.pdf', $certificate) }}#view=FitH" title="Certificate preview"
    style="width: 100%; aspect-ratio: 297.9 / 421.3; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb;"></iframe>
@endif

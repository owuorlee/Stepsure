<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $searched ? 'Certificate verification' : 'Verify a certificate' }} · {{ config('certificates.issuer') }}</title>
<meta name="description" content="Check that a {{ config('certificates.issuer') }} certificate is genuine.">
@if ($searched)<meta name="robots" content="noindex">@endif
<link rel="icon" type="image/png" sizes="64x64" href="{{ asset('favicon-64.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="#111111">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Playfair+Display:ital@1&display=swap" rel="stylesheet">
<style>
  :root { --red: #EB2024; --red-dark: #B5121B; --ink: #111111; --slate: #4B4B4B; --cream: #F6F3E7; --line: #E4DFCC; --ok: #17603A; --ok-bg: #E3F4EA; --bad-bg: #FCE6E2; }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--cream); color: var(--ink); font: 16px/1.6 Montserrat, system-ui, sans-serif; }
  a { color: var(--red-dark); }
  :focus-visible { outline: 3px solid var(--red); outline-offset: 2px; }
  .top { background: var(--ink); color: #fff; border-bottom: 4px solid var(--red); }
  .top .in { max-width: 860px; margin: 0 auto; padding: 14px 16px; display: flex; align-items: center; gap: 14px; }
  .top img { width: 52px; height: 52px; }
  .top b { display: block; font-size: 15px; letter-spacing: .04em; text-transform: uppercase; }
  .top small { color: #CFCFCF; font-size: 13px; }
  main { max-width: 860px; margin: 0 auto; padding: 32px 16px 48px; }
  h1 { font-size: clamp(26px, 5vw, 36px); line-height: 1.15; margin: 0 0 8px; }
  .lede { color: var(--slate); margin: 0 0 24px; max-width: 640px; }
  .card { background: #fff; border: 1px solid var(--line); border-radius: 16px; overflow: hidden; margin-bottom: 24px; }
  .status { display: flex; gap: 14px; align-items: center; padding: 18px 22px; font-weight: 700; font-size: 18px; }
  .status svg { flex: none; }
  .status small { display: block; font-weight: 500; font-size: 14px; opacity: .85; }
  .status.ok { background: var(--ok-bg); color: var(--ok); }
  .status.bad { background: var(--bad-bg); color: #8E2410; }
  .status.unknown { background: #F1F1F1; color: var(--ink); }
  dl { margin: 0; padding: 6px 22px 18px; display: grid; grid-template-columns: 190px 1fr; }
  dt, dd { margin: 0; padding: 12px 0; border-bottom: 1px solid #EEE; }
  dt { color: var(--slate); font-size: 14px; }
  dd { font-weight: 600; }
  dd.name { font: italic 26px/1.2 'Playfair Display', Georgia, serif; }
  dl > :nth-last-child(-n+2) { border-bottom: 0; }
  form.card { padding: 22px; }
  form h2 { font-size: 19px; margin: 0 0 4px; }
  form p { margin: 0 0 14px; color: var(--slate); font-size: 14px; }
  .row { display: flex; gap: 10px; }
  input { flex: 1; min-width: 0; font: 500 16px Montserrat, sans-serif; padding: 13px 14px; border: 1.5px solid #CFC9B4; border-radius: 10px; }
  input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px rgba(235, 32, 36, .25); }
  button { font: 700 15px Montserrat, sans-serif; background: var(--red); color: #fff; border: 0; border-radius: 10px; padding: 0 22px; cursor: pointer; }
  button:hover { background: var(--red-dark); }
  .note { font-size: 14px; color: var(--slate); }
  footer { text-align: center; font-size: 13px; color: var(--slate); padding: 0 16px 32px; }
  @media (max-width: 600px) {
    dl { grid-template-columns: 1fr; }
    dt { border-bottom: 0; padding-bottom: 0; }
    .row { flex-direction: column; }
    button { padding: 13px; }
  }
</style>
</head>
<body>
<header class="top"><div class="in">
  <img src="{{ asset('images/stepsure-logo.png') }}" alt="" width="52" height="52">
  <div><b>{{ config('certificates.issuer') }}</b><small>Certificate verification</small></div>
</div></header>

<main>
  @if (! $searched)
    <h1>Verify a certificate</h1>
    <p class="lede">Every {{ config('certificates.issuer') }} certificate carries a QR code and a unique Certificate ID. Scan the QR code, or enter the ID below, to confirm the certificate is genuine.</p>
  @else
    <h1>Certificate verification</h1>
    <div class="card" aria-live="polite">
      @if (! $certificate)
        <div class="status unknown">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5M12 16.5v.01"/></svg>
          <span>No certificate found<small>We couldn’t find a certificate with the ID “{{ Str::limit($code, 40) }}”. Check the ID on the certificate and try again.</small></span>
        </div>
      @elseif ($certificate->isValid())
        <div class="status ok">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3z"/><path d="M8.5 12l2.5 2.5 4.5-5"/></svg>
          <span>Valid certificate<small>This certificate was issued by {{ config('certificates.issuer') }}.</small></span>
        </div>
      @else
        <div class="status bad">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M5 5l14 14"/></svg>
          <span>This certificate has been revoked<small>It is no longer valid. Please contact us if you have questions.</small></span>
        </div>
      @endif

      @if ($certificate)
        <dl>
          <dt>Awarded to</dt><dd class="name">{{ $certificate->participant_name }}</dd>
          <dt>Certificate</dt><dd>Certificate of {{ $certificate->certificate_type }}</dd>
          <dt>Course</dt><dd>{{ $certificate->course_title }}</dd>
          <dt>Dates</dt><dd>{{ $certificate->dateRange() }}</dd>
          @if ($certificate->venue)<dt>Venue</dt><dd>{{ $certificate->venue }}</dd>@endif
          <dt>Certificate ID</dt><dd>{{ $certificate->code }}</dd>
          @unless ($certificate->isValid())<dt>Revoked on</dt><dd>{{ $certificate->revoked_at?->format('j F Y') }}</dd>@endunless
        </dl>
      @endif
    </div>
  @endif

  <form class="card" method="get" action="{{ route('verify.index') }}">
    <h2>{{ $searched ? 'Check another certificate' : 'Enter a Certificate ID' }}</h2>
    <p>The Certificate ID is printed under the QR code, for example <b>3f9a2c7d1b84</b>.</p>
    <div class="row">
      <label for="code" style="position:absolute;left:-9999px">Certificate ID</label>
      <input id="code" name="code" type="text" required maxlength="60" placeholder="Certificate ID" autocomplete="off" spellcheck="false" autocapitalize="none">
      <button type="submit">Verify</button>
    </div>
  </form>

  <p class="note">Only the details printed on the certificate are shown. For help, contact <a href="mailto:{{ config('certificates.contact_email') }}">{{ config('certificates.contact_email') }}</a>.</p>
</main>

<footer>© {{ date('Y') }} {{ config('certificates.issuer') }} · <a href="{{ config('certificates.website') }}">{{ parse_url(config('certificates.website'), PHP_URL_HOST) }}</a></footer>
</body>
</html>

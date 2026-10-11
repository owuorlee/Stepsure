@php($font = fn (string $file) => str_replace('\\', '/', $fonts.DIRECTORY_SEPARATOR.$file))
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $certificate->participant_name }}: {{ $certificate->course_title }}</title>
<style>
  /* A3 portrait, positions in mm measured from StepSure's original certificate. Rendered by dompdf: keep CSS simple. */
  @font-face { font-family: 'Name'; font-style: italic; font-weight: normal; src: url('{{ $font('PlayfairDisplay-Italic.ttf') }}') format('truetype'); }
  @font-face { font-family: 'Course'; font-style: italic; font-weight: bold; src: url('{{ $font('TeXGyreSchola-BoldItalic.ttf') }}') format('truetype'); }
  @font-face { font-family: 'Sans'; font-style: normal; font-weight: normal; src: url('{{ $font('Montserrat-Medium.ttf') }}') format('truetype'); }
  @page { margin: 0; }
  html, body { margin: 0; padding: 0; }
  .bg { position: absolute; left: 0; top: 0; width: 297.9mm; height: 421.3mm; }
  .line { position: absolute; left: 12mm; right: 12mm; text-align: center; color: #000; line-height: 1.2; }
  .name { font-family: 'Name'; font-style: italic; }
  .course { top: 196mm; left: 18mm; right: 18mm; font-family: 'Course'; font-style: italic; font-weight: bold; color: #F06767; line-height: 1.15; }
  .held { top: 259.5mm; font-family: 'Sans'; font-size: 32pt; }
  .held sup { font-size: 19pt; line-height: 0; vertical-align: 11pt; }
  .venue { top: 283mm; font-family: 'Sans'; font-size: 27.5pt; }
  .class { top: 298mm; font-family: 'Sans'; font-size: 25pt; }
  .qr { position: absolute; left: 131.6mm; top: 349.5mm; width: 36.2mm; height: 36.2mm; }
  .cid { top: 395.4mm; font-family: 'Sans'; font-size: 14pt; }
  .serial { top: 405.2mm; font-family: 'Sans'; font-size: 22pt; }
  .revoked { position: absolute; left: 0; right: 0; top: 230mm; text-align: center; font-family: 'Sans'; font-size: 90pt; color: #EB2024; opacity: .25; }
</style>
</head>
<body>
  <img class="bg" src="{{ $background }}" alt="">

  <div class="line name" style="top: {{ $nameTop }}mm; font-size: {{ $nameSize }}pt">{{ $certificate->participant_name }}</div>
  <div class="line course" style="font-size: {{ $courseSize }}pt">{{ $certificate->course_title }}</div>
  <div class="line held">Held on {!! $certificate->dateRange(html: true) !!}</div>
  @if ($certificate->venue)<div class="line venue">{{ $certificate->venue }}</div>@endif
  @if ($certificate->class_code)<div class="line class">{{ $certificate->class_code }}</div>@endif

  <img class="qr" src="{{ $qr }}" alt="Verification QR code">
  <div class="line cid">Certificate ID: {{ $certificate->code }}</div>
  @if ($certificate->serial_no)<div class="line serial">{{ $certificate->serial_no }}</div>@endif

  @unless ($certificate->isValid())<div class="revoked">REVOKED</div>@endunless
</body>
</html>

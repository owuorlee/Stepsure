# StepSure Certificates

Certificate generation and online verification for **StepSure Training & Research Institute** (stepsureresearchinstitute.org).

> **Private repository.** It contains StepSure's certificate artwork, including the signatures of its signatories. Do not make it public or share the files outside the team.

- **Staff portal** (`/admin`): issue certificates one at a time or a whole class from Excel or CSV, preview, download (single or ZIP), email to participants, correct details, revoke and restore. Past certificates (e.g. from Zikoro) can be imported with their existing IDs so they verify too.
- **Public verification** (`/` and `/verify/{id}`): the QR code on every certificate opens a StepSure-branded page confirming it is genuine, or showing that it was revoked or doesn't exist.
- Planned live address: `certificates.stepsureresearchinstitute.org`.

## What's here

| Path | Contents |
| --- | --- |
| `stepsure-portal/` | The Laravel 13 + Filament 5 app |
| `tools/build_background.py` | Builds the certificate background from one of StepSure's own certificate PDFs |
| `docs/certificate-analysis.md` | Analysis of StepSure's original certificate: layout, fonts, colours, problems found, open questions |
| `preview-demo-certificate.pdf` | Sample output with a fictional participant |

## How certificates are drawn

The design is StepSure's own artwork. `tools/build_background.py` removes everything that changes per person (name, course, dates, venue, codes, ID, old QR code), restores the faded watermark letters, fixes the "EXCELLENCE" spelling, and saves `stepsure-portal/resources/certificate/background.jpg` (A3 portrait, 200 dpi). The app prints the variable text on top at positions measured in mm (`resources/views/certificates/pdf.blade.php`). The fonts are free substitutes in `resources/fonts`:
- Playfair Display (name)
- TeX Gyre Schola (course; a Century Schoolbook copy)
- Montserrat (Gotham substitute)

To rebuild the background from new artwork (needs Python with PyMuPDF):

```bash
python tools/build_background.py "path/to/a-stepsure-certificate.pdf"
```

## Running it locally (Windows)

1. Install [Laravel Herd](https://herd.laravel.com) (PHP 8.4 and Composer).
2. In `stepsure-portal`:
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   php artisan migrate
   ```
3. Set `DEV_ADMIN_PASSWORD` in `.env`, then create your local login and some fictional demo certificates:
   ```bash
   php artisan db:seed
   php artisan db:seed --class=DemoCertificatesSeeder
   ```
4. Link the folder in Herd (`herd link stepsure-portal`) and open http://stepsure-portal.test/admin.
5. Run the tests before pushing: `php artisan test --compact`. Format PHP with `vendor/bin/pint`.

Excel uploads build their PDFs in the background. Run `php artisan queue:work` to process them; otherwise each PDF is built the first time it is previewed, downloaded or emailed. Emails go to `storage/logs/laravel.log` locally.

## Waiting on StepSure

- NameSilo, hosting and WordPress access, to go live on their domain and link the portal from the main site
- The original Illustrator file and 300 dpi signature scans (sharper printing)
- The meaning of the class code ("SOM 3/2026") and serial ("IA 2/2026")
- Other certificate types besides Participation
- An export of past certificates from Zikoro

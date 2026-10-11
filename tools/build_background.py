"""Build the StepSure certificate background from the client's own certificate PDF.

Removes everything that changes per certificate (name, course, dates, venue, codes, ID, old Zikoro QR code),
fixes the "EXELENCE" spelling on the seal banner, and renders the result as a 200 dpi A3 JPEG used by the portal.

Usage:  python build_background.py "<path to one of StepSure's issued certificate PDFs>"
        (keep that PDF out of Git: it contains a participant's personal data)
Output: stepsure-portal/resources/certificate/background.jpg
"""
import math
import os
import sys

import fitz  # PyMuPDF

if len(sys.argv) != 2:
    sys.exit(__doc__)
SOURCE = sys.argv[1]
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "stepsure-portal", "resources", "certificate", "background.jpg")
CONSTANTIA_BOLD = r"C:\Windows\Fonts\constanb.ttf"
MM = 72 / 25.4

# Vertical bands (mm from the top) that hold per-certificate text on StepSure's layout:
# name, course title, dates/venue/class code, and Certificate ID/serial. Static text lies outside them.
VARIABLE_BANDS_MM = ((140, 172), (190, 255), (255, 316), (392, 418))
QR_RECT = fitz.Rect(129.6 * MM, 347.5 * MM, 169.8 * MM, 387.4 * MM)  # old QR plus a 2 mm margin

WATERMARK_GREY = (213 / 255,) * 3  # rendered colour of the faded ring letters (they sit in a translucent layer)


def watermark_letters(page: fitz.Page) -> dict:
    """Large ring letters of the faded watermark, keyed by rounded origin, with their angle and size."""
    letters = {}
    for span in page.get_texttrace():
        if "Constantia" in span["font"] and span["size"] > 30:
            cos, sin = span["dir"]
            for char in span["chars"]:
                key = (round(char[2][0], 1), round(char[2][1], 1))
                letters[key] = (chr(char[0]), math.degrees(math.atan2(sin, cos)), span["size"])
    return letters


doc = fitz.open(SOURCE)
page = doc[0]
ring_before = watermark_letters(page)

banner = None
for block in page.get_text("dict")["blocks"]:
    text = " ".join(s["text"] for line in block.get("lines", []) for s in line["spans"]).strip()
    rect = fitz.Rect(block["bbox"])
    centre_mm = (rect.y0 + rect.y1) / 2 / MM
    is_watermark = all("Constantia" in span["font"] for line in block.get("lines", []) for span in line["spans"])
    if text.startswith("COMMITTED TO"):
        banner = rect
        page.add_redact_annot(rect)
    elif text and not is_watermark and any(top <= centre_mm <= bottom for top, bottom in VARIABLE_BANDS_MM):
        page.add_redact_annot(rect)
# Pass 1: remove the text only; keep every image and drawing (background, watermark, dots, underline)
page.apply_redactions(images=fitz.PDF_REDACT_IMAGE_NONE, graphics=fitz.PDF_REDACT_LINE_ART_NONE)

# Redaction also removes watermark letters that overlapped the variable text: draw those back in place
font_ring = fitz.Font(fontfile=CONSTANTIA_BOLD)
for (x, y), (letter, angle, size) in ring_before.items():
    if (x, y) in watermark_letters(page):
        continue
    writer = fitz.TextWriter(page.rect)
    writer.append((x, y), letter, font=font_ring, fontsize=size)
    writer.write_text(page, color=WATERMARK_GREY, morph=(fitz.Point(x, y), fitz.Matrix(-angle)))

# The old Zikoro QR code: cover it with a white square (the new QR code is printed on white in the same place)
page.draw_rect(QR_RECT, color=None, fill=(1, 1, 1))

# Corrected banner text in the original typeface, shrunk to fit the same width
if banner is not None:
    font = fitz.Font(fontfile=CONSTANTIA_BOLD)
    text = "COMMITTED TO EXCELLENCE"
    size = min(16.4, (banner.width - 2) / font.text_length(text, fontsize=1))
    x = banner.x0 + (banner.width - font.text_length(text, fontsize=size)) / 2
    y = banner.y0 + banner.height / 2 + size * 0.35
    page.insert_font(fontname="constb", fontfile=CONSTANTIA_BOLD)
    page.insert_text((x, y), text, fontname="constb", fontsize=size, color=(0, 0, 0))

os.makedirs(os.path.dirname(OUT), exist_ok=True)
pix = page.get_pixmap(dpi=200, alpha=False)
pix.save(OUT, jpg_quality=90)
print(f"Saved {OUT} ({pix.width}x{pix.height}px, {os.path.getsize(OUT) // 1024} KB)")

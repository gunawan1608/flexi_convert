# Gotenberg Setup

This project now uses Gotenberg as the primary engine for supported document and PDF workflows.

## Quick start

1. Start the service:

   ```bash
   docker compose up -d gotenberg
   ```

2. Check health:

   ```bash
   curl http://127.0.0.1:3000/health
   ```

3. Ensure these environment variables exist:

   ```env
   ENGINE_OFFICE_TO_PDF=gotenberg
   GOTENBERG_URL=http://127.0.0.1:3000
   GOTENBERG_TIMEOUT=120
   ```

3. Run the Laravel app as usual.

## Flows now using Gotenberg

- Word to PDF
- Excel to PDF
- PowerPoint to PDF
- Images to PDF
- HTML to PDF
- Merge PDF
- Split PDF

## Flows removed from Gotenberg-only mode

- PDF to Word / Excel / PowerPoint
- PDF to Image
- Compress PDF
- Rotate PDF
- Add watermark
- Add page numbers

## Custom fonts

If a source document depends on specific fonts, place the `.ttf` or `.otf` files in:

```text
docker/gotenberg/fonts/
```

Then rebuild the image:

```bash
docker compose build gotenberg
docker compose up -d gotenberg
```

## Important limitation

No engine can guarantee that every source document will be converted with zero visual differences.
Gotenberg improves packaging and deployment, but exact 1:1 fidelity still depends on the source file,
fonts, page settings, and LibreOffice/Chromium behavior inside the container.

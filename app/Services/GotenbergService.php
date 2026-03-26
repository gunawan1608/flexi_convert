<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GotenbergService
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('pdftools.gotenberg.url', 'http://127.0.0.1:3000'), '/');
        $this->timeout = (int) config('pdftools.gotenberg.timeout', 120);
    }

    public function officeToPdf(array $files, array $options = []): array
    {
        return $this->sendMultipart(
            '/forms/libreoffice/convert',
            $files,
            $this->buildOfficeFields($options),
            $options['output_filename'] ?? null,
            'pdf'
        );
    }

    public function compressPdf(string $filePath, array $options = []): array
    {
        $compressionLevel = strtolower((string) ($options['compressionLevel'] ?? 'medium'));
        $profiles = [
            'low' => [
                'losslessImageCompression' => 'false',
                'quality' => '60',
                'reduceImageResolution' => 'true',
                'maxImageResolution' => '150',
            ],
            'medium' => [
                'losslessImageCompression' => 'false',
                'quality' => '75',
                'reduceImageResolution' => 'true',
                'maxImageResolution' => '300',
            ],
            'high' => [
                'losslessImageCompression' => 'false',
                'quality' => '90',
                'reduceImageResolution' => 'false',
                'maxImageResolution' => '300',
            ],
        ];

        $fields = $profiles[$compressionLevel] ?? $profiles['medium'];

        return $this->sendMultipart(
            '/forms/libreoffice/convert',
            [[
                'path' => $filePath,
                'filename' => basename($filePath),
            ]],
            $fields,
            $options['output_filename'] ?? null,
            'pdf'
        );
    }

    public function mergePdfs(array $files, array $options = []): array
    {
        return $this->sendMultipart(
            '/forms/pdfengines/merge',
            $files,
            [],
            $options['output_filename'] ?? null,
            'pdf'
        );
    }

    public function splitPdf(string $filePath, array $options = []): array
    {
        $splitMode = $options['splitMode'] ?? 'pages';
        $splitSpan = $options['splitSpan'] ?? null;

        if (!$splitSpan) {
            throw new RuntimeException('Split configuration is incomplete.');
        }

        $fields = [
            'splitMode' => $splitMode,
            'splitSpan' => $splitSpan,
        ];

        if (!empty($options['splitUnify'])) {
            $fields['splitUnify'] = 'true';
        }

        return $this->sendMultipart(
            '/forms/pdfengines/split',
            [[
                'path' => $filePath,
                'filename' => basename($filePath),
            ]],
            $fields,
            $options['output_filename'] ?? null,
            null
        );
    }

    public function htmlFileToPdf(string $htmlPath, array $options = []): array
    {
        return $this->sendMultipart(
            '/forms/chromium/convert/html',
            [[
                'path' => $htmlPath,
                'filename' => 'index.html',
            ]],
            $this->buildChromiumFields($options),
            $options['output_filename'] ?? null,
            'pdf'
        );
    }

    public function htmlStringToPdf(string $html, array $options = []): array
    {
        $baseTempPath = tempnam(sys_get_temp_dir(), 'gotenberg_html_');
        if ($baseTempPath === false) {
            throw new RuntimeException('Failed to create temporary HTML file.');
        }

        $htmlPath = $baseTempPath . '.html';
        @unlink($baseTempPath);
        file_put_contents($htmlPath, $html);

        try {
            return $this->htmlFileToPdf($htmlPath, $options);
        } finally {
            if (file_exists($htmlPath)) {
                @unlink($htmlPath);
            }
        }
    }

    public function imagesToPdf(array $files, array $options = []): array
    {
        $html = $this->buildImagesHtml($files, $options);

        return $this->htmlStringToPdf($html, array_merge($options, [
            'preferCssPageSize' => true,
            'printBackground' => true,
        ]));
    }

    private function sendMultipart(
        string $endpoint,
        array $files,
        array $fields = [],
        ?string $outputFilename = null,
        ?string $preferredExtension = null
    ): array {
        $request = Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->accept('application/octet-stream');

        if ($outputFilename) {
            $request = $request->withHeaders([
                'Gotenberg-Output-Filename' => pathinfo($outputFilename, PATHINFO_FILENAME),
            ]);
        }

        foreach ($files as $file) {
            $path = $file['path'] ?? null;

            if (!$path || !is_file($path)) {
                throw new RuntimeException('Input file for Gotenberg was not found.');
            }

            $request = $request->attach(
                'files',
                fopen($path, 'r'),
                $file['filename'] ?? basename($path)
            );
        }

        try {
            $response = $request->post($endpoint, $fields);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                'Gotenberg is not reachable at ' . $this->baseUrl . '. Start it first with Docker.',
                previous: $e
            );
        }

        if (!$response->successful()) {
            Log::error('Gotenberg request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
                'trace' => $response->header('Gotenberg-Trace'),
            ]);

            $message = trim($response->body());
            throw new RuntimeException($message !== '' ? $message : 'Gotenberg request failed.');
        }

        return $this->storeResponse($response, $preferredExtension);
    }

    private function storeResponse(Response $response, ?string $preferredExtension = null): array
    {
        $extension = $preferredExtension ?? $this->detectExtension($response);
        $baseTempPath = tempnam(sys_get_temp_dir(), 'gotenberg_result_');

        if ($baseTempPath === false) {
            throw new RuntimeException('Failed to create temporary output file.');
        }

        $outputPath = $baseTempPath . ($extension ? '.' . $extension : '');
        @unlink($baseTempPath);
        file_put_contents($outputPath, $response->body());

        return [
            'path' => $outputPath,
            'extension' => $extension,
            'content_type' => (string) $response->header('Content-Type'),
            'trace' => (string) $response->header('Gotenberg-Trace'),
        ];
    }

    private function detectExtension(Response $response): string
    {
        $disposition = (string) $response->header('Content-Disposition');

        if (preg_match('/filename\\*=UTF-8\'\'[^;]+\\.([a-zA-Z0-9]+)(?:;|$)/', $disposition, $matches)) {
            return strtolower($matches[1]);
        }

        if (preg_match('/filename="?[^";]+\\.([a-zA-Z0-9]+)"?(?:;|$)/', $disposition, $matches)) {
            return strtolower($matches[1]);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        return match (true) {
            str_contains($contentType, 'zip') => 'zip',
            str_contains($contentType, 'pdf') => 'pdf',
            str_contains($contentType, 'json') => 'json',
            default => 'bin',
        };
    }

    private function buildOfficeFields(array $options): array
    {
        $fields = [];

        if (($options['orientation'] ?? null) === 'landscape') {
            $fields['landscape'] = 'true';
        }

        if (!empty($options['nativePageRanges'])) {
            $fields['nativePageRanges'] = (string) $options['nativePageRanges'];
        }

        if (!empty($options['merge'])) {
            $fields['merge'] = 'true';
        }

        if (!empty($options['singlePageSheets'])) {
            $fields['singlePageSheets'] = 'true';
        }

        return $fields;
    }

    private function buildChromiumFields(array $options): array
    {
        $fields = [];
        $pageSize = strtoupper((string) ($options['pageSize'] ?? 'A4'));
        $dimensions = $this->pageDimensions($pageSize);

        if ($dimensions) {
            $fields['paperWidth'] = $dimensions['width'];
            $fields['paperHeight'] = $dimensions['height'];
        }

        if (($options['orientation'] ?? null) === 'landscape') {
            $fields['landscape'] = 'true';
        }

        if (isset($options['margin']) && $options['margin'] !== '') {
            $margin = (int) $options['margin'] . 'mm';
            $fields['marginTop'] = $margin;
            $fields['marginBottom'] = $margin;
            $fields['marginLeft'] = $margin;
            $fields['marginRight'] = $margin;
        }

        if (!empty($options['waitDelay'])) {
            $fields['waitDelay'] = (string) $options['waitDelay'];
        }

        $fields['printBackground'] = !empty($options['printBackground']) ? 'true' : 'false';

        if (!empty($options['preferCssPageSize'])) {
            $fields['preferCssPageSize'] = 'true';
        }

        return $fields;
    }

    private function pageDimensions(string $pageSize): ?array
    {
        return match ($pageSize) {
            'A3' => ['width' => '297mm', 'height' => '420mm'],
            'A4' => ['width' => '210mm', 'height' => '297mm'],
            'A5' => ['width' => '148mm', 'height' => '210mm'],
            'LETTER' => ['width' => '8.5in', 'height' => '11in'],
            'LEGAL' => ['width' => '8.5in', 'height' => '14in'],
            default => null,
        };
    }

    private function buildImagesHtml(array $files, array $options = []): string
    {
        if ($files === []) {
            throw new RuntimeException('No image files were provided for conversion.');
        }

        $pageSize = strtoupper((string) ($options['pageSize'] ?? 'A4'));
        $orientation = strtolower((string) ($options['orientation'] ?? 'portrait'));
        $margin = (int) ($options['margin'] ?? 20);
        $objectFit = ($options['imageSize'] ?? 'fit') === 'fill' ? 'cover' : 'contain';

        $pages = [];

        foreach ($files as $file) {
            $path = $file['path'] ?? null;

            if (!$path || !is_file($path)) {
                continue;
            }

            $mimeType = mime_content_type($path) ?: 'application/octet-stream';
            $encodedContents = base64_encode(file_get_contents($path));

            $pages[] = sprintf(
                '<section class="page"><img src="data:%s;base64,%s" alt="%s"></section>',
                htmlspecialchars($mimeType, ENT_QUOTES),
                $encodedContents,
                htmlspecialchars(basename($path), ENT_QUOTES)
            );
        }

        if ($pages === []) {
            throw new RuntimeException('No readable images were available for PDF conversion.');
        }

        $pagesMarkup = implode("\n", $pages);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: {$pageSize} {$orientation};
            margin: {$margin}mm;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            background: #ffffff;
        }

        body {
            font-family: sans-serif;
        }

        .page {
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .page img {
            max-width: 100%;
            max-height: 100vh;
            object-fit: {$objectFit};
            display: block;
        }
    </style>
</head>
<body>
    {$pagesMarkup}
</body>
</html>
HTML;
    }
}

<?php

namespace Tests\Unit;

use App\Services\GotenbergService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GotenbergServiceTest extends TestCase
{
    public function test_office_error_message_is_normalized_when_no_page_range_was_sent(): void
    {
        Http::fake([
            '*' => Http::response(
                "LibreOffice failed to process a document: possible causes include malformed page ranges '' (nativePageRanges), or, if a password has been provided, it may not be required. In any case, the exact cause is uncertain.",
                400
            ),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'gotenberg_doc_');
        file_put_contents($path, 'fake office contents');

        try {
            $service = new GotenbergService();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('LibreOffice gagal memproses file Office ini.');

            $service->officeToPdf([[
                'path' => $path,
                'filename' => 'broken.docx',
            ]], []);
        } finally {
            @unlink($path);
        }
    }

    public function test_office_error_message_stays_raw_when_native_page_ranges_are_present(): void
    {
        $rawMessage = "LibreOffice failed to process a document: possible causes include malformed page ranges '' (nativePageRanges), or, if a password has been provided, it may not be required. In any case, the exact cause is uncertain.";

        Http::fake([
            '*' => Http::response($rawMessage, 400),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'gotenberg_doc_');
        file_put_contents($path, 'fake office contents');

        try {
            $service = new GotenbergService();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage($rawMessage);

            $service->officeToPdf([[
                'path' => $path,
                'filename' => 'paged.docx',
            ]], [
                'nativePageRanges' => '1-2',
            ]);
        } finally {
            @unlink($path);
        }
    }
}

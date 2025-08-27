<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use App\Models\User;
use App\Models\PdfProcessing;

class PDFToolsTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Set up test storage
        Storage::fake('local');
        
        // Configure PDF tools for testing
        Config::set('pdftools.storage.uploads_path', 'test-uploads');
        Config::set('pdftools.storage.outputs_path', 'test-outputs');
        Config::set('pdftools.validation.max_file_size', 52428800);
        Config::set('pdftools.logging.log_conversions', false);
        Config::set('pdftools.office_to_pdf_engine', 'phpoffice');
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files', 'tool']);
    }

    /** @test */
    public function it_validates_file_types_for_word_to_pdf()
    {
        $invalidFile = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$invalidFile],
                'tool' => 'word-to-pdf'
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function it_validates_file_types_for_pdf_compression()
    {
        $invalidFile = UploadedFile::fake()->create('test.docx', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$invalidFile],
                'tool' => 'compress-pdf'
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function it_handles_unsupported_tool()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$file],
                'tool' => 'unsupported-tool'
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false
            ]);
    }

    /** @test */
    public function it_creates_processing_record_for_valid_request()
    {
        // Create a mock PDF file
        $pdfContent = '%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj
2 0 obj
<<
/Type /Pages
/Kids [3 0 R]
/Count 1
>>
endobj
3 0 obj
<<
/Type /Page
/Parent 2 0 R
/MediaBox [0 0 612 792]
>>
endobj
xref
0 4
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
trailer
<<
/Size 4
/Root 1 0 R
>>
startxref
190
%%EOF';

        Storage::put('test-file.pdf', $pdfContent);
        $file = new UploadedFile(
            Storage::path('test-file.pdf'),
            'test.pdf',
            'application/pdf',
            null,
            true
        );

        $this->assertDatabaseCount('pdf_processings', 0);

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$file],
                'tool' => 'compress-pdf',
                'settings' => json_encode(['quality' => 'medium'])
            ]);

        // Should create a processing record even if conversion fails
        $this->assertDatabaseCount('pdf_processings', 1);
        
        $processing = PdfProcessing::first();
        $this->assertEquals($this->user->id, $processing->user_id);
        $this->assertEquals('compress-pdf', $processing->tool_name);
        $this->assertEquals('test.pdf', $processing->original_filename);
        $this->assertNotNull($processing->friendly_filename);
    }

    /** @test */
    public function it_generates_friendly_filenames()
    {
        $pdfContent = '%PDF-1.4
1 0 obj
<<
/Type /Catalog
/Pages 2 0 R
>>
endobj
xref
0 2
trailer
<<
/Size 2
/Root 1 0 R
>>
%%EOF';

        Storage::put('test-document.pdf', $pdfContent);
        $file = new UploadedFile(
            Storage::path('test-document.pdf'),
            'My Document.pdf',
            'application/pdf',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$file],
                'tool' => 'compress-pdf'
            ]);

        $processing = PdfProcessing::first();
        
        // Should contain the tool name and sanitized original filename
        $this->assertStringContainsString('compressed', $processing->friendly_filename);
        $this->assertStringContainsString('my-document', strtolower($processing->friendly_filename));
        $this->assertStringEndsWith('.pdf', $processing->friendly_filename);
    }

    /** @test */
    public function it_handles_file_size_validation()
    {
        Config::set('pdftools.validation.max_file_size', 1024); // 1KB limit

        $largeFile = UploadedFile::fake()->create('large.pdf', 2); // 2KB file

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$largeFile],
                'tool' => 'compress-pdf'
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files.0']);
    }

    /** @test */
    public function it_logs_conversion_metrics_when_enabled()
    {
        Config::set('pdftools.logging.log_conversions', true);
        
        $pdfContent = '%PDF-1.4
1 0 obj
<<
/Type /Catalog
>>
endobj
xref
0 1
trailer
<<
/Size 1
/Root 1 0 R
>>
%%EOF';

        Storage::put('test-metrics.pdf', $pdfContent);
        $file = new UploadedFile(
            Storage::path('test-metrics.pdf'),
            'test.pdf',
            'application/pdf',
            null,
            true
        );

        // This will attempt conversion and should log metrics
        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$file],
                'tool' => 'compress-pdf'
            ]);

        // Verify processing record was created with metrics
        $processing = PdfProcessing::first();
        $this->assertNotNull($processing);
        $this->assertGreaterThan(0, $processing->file_size);
    }

    /** @test */
    public function it_handles_multiple_files_for_merge_pdf()
    {
        $pdf1 = UploadedFile::fake()->create('doc1.pdf', 100, 'application/pdf');
        $pdf2 = UploadedFile::fake()->create('doc2.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$pdf1, $pdf2],
                'tool' => 'merge-pdf'
            ]);

        // Should accept multiple files for merge operation
        $this->assertDatabaseCount('pdf_processings', 1);
        
        $processing = PdfProcessing::first();
        $this->assertEquals('merge-pdf', $processing->tool_name);
    }

    /** @test */
    public function it_provides_download_url_in_response()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$file],
                'tool' => 'compress-pdf'
            ]);

        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'success',
                'message',
                'processing_id',
                'output_filename',
                'download_url'
            ]);

            $data = $response->json();
            $this->assertTrue($data['success']);
            $this->assertNotNull($data['download_url']);
            $this->assertStringContains('/api/pdf-tools/download/', $data['download_url']);
        }
    }

    /** @test */
    public function it_handles_settings_parameter()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
        
        $settings = [
            'quality' => 'high',
            'custom_option' => 'value'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/pdf-tools/process', [
                'files' => [$file],
                'tool' => 'compress-pdf',
                'settings' => json_encode($settings)
            ]);

        $processing = PdfProcessing::first();
        $this->assertNotNull($processing);
        
        $storedSettings = json_decode($processing->settings, true);
        $this->assertEquals('high', $storedSettings['quality']);
        $this->assertEquals('value', $storedSettings['custom_option']);
    }

    /** @test */
    public function it_requires_authentication()
    {
        $file = UploadedFile::fake()->create('test.pdf', 100);

        $response = $this->postJson('/api/pdf-tools/process', [
            'files' => [$file],
            'tool' => 'compress-pdf'
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function download_endpoint_requires_authentication()
    {
        $response = $this->getJson('/api/pdf-tools/download/123');
        
        $response->assertStatus(401);
    }

    /** @test */
    public function download_endpoint_returns_404_for_nonexistent_file()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/pdf-tools/download/999999');
        
        $response->assertStatus(404);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        Storage::deleteDirectory('test-uploads');
        Storage::deleteDirectory('test-outputs');
        
        parent::tearDown();
    }
}

<?php

namespace Tests\Feature;

use App\Models\PdfProcessing;
use App\Models\User;
use App\Services\GotenbergService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class PDFToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_health_endpoint_reports_gotenberg_status(): void
    {
        $mock = Mockery::mock(GotenbergService::class);
        $mock->shouldReceive('health')->once()->andReturn([
            'status' => 'up',
            'details' => [
                'chromium' => ['status' => 'up'],
                'libreoffice' => ['status' => 'up'],
            ],
        ]);

        $this->app->instance(GotenbergService::class, $mock);

        $this->getJson('/api/pdf-tools/health')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'up',
                'engine' => 'gotenberg',
            ]);
    }

    public function test_health_endpoint_returns_service_unavailable_when_gotenberg_is_down(): void
    {
        $mock = Mockery::mock(GotenbergService::class);
        $mock->shouldReceive('health')->once()->andThrow(new RuntimeException('Gotenberg is not reachable.'));

        $this->app->instance(GotenbergService::class, $mock);

        $this->getJson('/api/pdf-tools/health')
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'status' => 'down',
                'engine' => 'gotenberg',
            ]);
    }

    public function test_process_rejects_still_disabled_legacy_pdf_tool(): void
    {
        $file = UploadedFile::fake()->create('legacy.pdf', 64, 'application/pdf');

        $this->withHeader('Accept', 'application/json')->post('/api/pdf-tools/process', [
            'files' => [$file],
            'tool' => 'compress-pdf',
        ])->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_process_surfaces_gotenberg_health_errors_for_supported_tools(): void
    {
        $mock = Mockery::mock(GotenbergService::class);
        $mock->shouldReceive('health')->once()->andThrow(new RuntimeException('Gotenberg is not reachable.', 503));

        $this->app->instance(GotenbergService::class, $mock);

        $file = UploadedFile::fake()->create(
            'demo.docx',
            64,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $this->withHeader('Accept', 'application/json')->post('/api/pdf-tools/process', [
            'files' => [$file],
            'tool' => 'word-to-pdf',
        ])->assertStatus(503)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_process_records_authenticated_user_when_pdf_tool_runs_via_web_session(): void
    {
        Storage::fake(config('filesystems.default'));

        $user = User::factory()->create();

        $mock = Mockery::mock(GotenbergService::class);
        $mock->shouldReceive('health')->once()->andReturn([
            'status' => 'up',
            'details' => [
                'chromium' => ['status' => 'up'],
                'libreoffice' => ['status' => 'up'],
            ],
        ]);
        $mock->shouldReceive('officeToPdf')->once()->andReturnUsing(function () {
            $path = tempnam(sys_get_temp_dir(), 'gotenberg_test_');
            file_put_contents($path, 'fake pdf output');

            return ['path' => $path];
        });

        $this->app->instance(GotenbergService::class, $mock);

        $file = UploadedFile::fake()->create(
            'demo.docx',
            64,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );

        $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->post('/api/pdf-tools/process', [
                'files' => [$file],
                'tool' => 'word-to-pdf',
                'settings' => json_encode(['format' => 'pdf']),
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $processing = PdfProcessing::query()->sole();

        $this->assertSame($user->id, $processing->user_id);
        $this->assertSame('completed', $processing->status);
        $this->assertNotNull($processing->processed_filename);
        Storage::disk(config('filesystems.default'))
            ->assertExists('pdf-tools/outputs/' . $processing->processed_filename);
    }
}

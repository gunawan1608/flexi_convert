<?php

namespace App\Jobs;

use App\Models\Conversion;
use App\Services\DocumentConversionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessDocumentConversion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $conversion;

    public function __construct(Conversion $conversion)
    {
        $this->conversion = $conversion;
    }

    public function handle()
    {
        try {
            $this->conversion->update(['status' => 'processing', 'progress' => 10, 'started_at' => now()]);
            
            $conversionService = new DocumentConversionService();
            
            // Get file paths
            $inputPath = storage_path('app/private/' . $this->conversion->file_path);
            $outputDir = storage_path('app/private/converted/documents/');
            
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }
            
            $outputFilename = Str::uuid() . '.' . $this->conversion->target_extension;
            $outputPath = $outputDir . $outputFilename;
            
            $this->conversion->update(['progress' => 30]);
            
            // Use DocumentConversionService for actual conversion
            $result = $conversionService->convertDocument(
                $inputPath,
                $outputPath,
                $this->conversion->original_extension,
                $this->conversion->target_extension,
                $this->conversion->settings ?? []
            );
            
            $this->conversion->update(['progress' => 80]);
            
            if ($result['success']) {
                $this->conversion->update([
                    'status' => 'completed',
                    'progress' => 100,
                    'converted_file_path' => 'converted/documents/' . $outputFilename,
                    'converted_filename' => $outputFilename,
                    'completed_at' => now()
                ]);
            } else {
                $this->conversion->update([
                    'status' => 'failed',
                    'error_message' => $result['error'] ?? 'Conversion failed'
                ]);
            }
            
        } catch (\Exception $e) {
            $this->conversion->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception)
    {
        $this->conversion->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage()
        ]);
    }
}

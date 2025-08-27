<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\PdfProcessing;
use Carbon\Carbon;

class CleanupOldFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'files:cleanup 
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Force deletion without confirmation}
                            {--hours= : Override retention hours from config}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old uploaded and processed files based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForced = $this->option('force');
        $retentionHours = $this->option('hours') ?? config('pdftools.cleanup.retention_hours', 24);

        $this->info("Starting file cleanup process...");
        $this->info("Retention period: {$retentionHours} hours");
        
        if ($isDryRun) {
            $this->warn("DRY RUN MODE - No files will be deleted");
        }

        $cutoffTime = Carbon::now()->subHours($retentionHours);
        $this->info("Deleting files older than: {$cutoffTime->format('Y-m-d H:i:s')}");

        // Clean up database records and associated files
        $this->cleanupProcessingRecords($cutoffTime, $isDryRun);
        
        // Clean up orphaned files in storage
        $this->cleanupOrphanedFiles($cutoffTime, $isDryRun);

        $this->info("File cleanup completed successfully!");
    }

    /**
     * Clean up old processing records and their associated files
     */
    private function cleanupProcessingRecords($cutoffTime, $isDryRun)
    {
        $this->info("\n=== Cleaning up processing records ===");
        
        $oldRecords = PdfProcessing::where('created_at', '<', $cutoffTime)->get();
        
        if ($oldRecords->isEmpty()) {
            $this->info("No old processing records found.");
            return;
        }

        $this->info("Found {$oldRecords->count()} old processing records");

        $deletedFiles = 0;
        $deletedRecords = 0;
        $totalSize = 0;

        foreach ($oldRecords as $record) {
            $this->line("Processing record ID: {$record->id} ({$record->original_filename})");
            
            // Delete associated files
            $fileDeleted = false;
            $fileSize = 0;
            
            if ($record->processed_filename) {
                $outputPath = config('pdftools.storage.outputs_path') . '/' . $record->processed_filename;
                
                if (Storage::exists($outputPath)) {
                    $fileSize = Storage::size($outputPath);
                    
                    if (!$isDryRun) {
                        Storage::delete($outputPath);
                        $fileDeleted = true;
                        $deletedFiles++;
                        $totalSize += $fileSize;
                    } else {
                        $this->line("  Would delete: {$outputPath} (" . $this->formatBytes($fileSize) . ")");
                        $deletedFiles++;
                        $totalSize += $fileSize;
                    }
                } else {
                    $this->line("  Output file not found: {$outputPath}");
                }
            }

            // Delete database record
            if (!$isDryRun) {
                $record->delete();
                $deletedRecords++;
            } else {
                $deletedRecords++;
            }
        }

        $this->info("Processed {$deletedRecords} records");
        $this->info("Deleted {$deletedFiles} files (" . $this->formatBytes($totalSize) . ")");
    }

    /**
     * Clean up orphaned files in storage directories
     */
    private function cleanupOrphanedFiles($cutoffTime, $isDryRun)
    {
        $this->info("\n=== Cleaning up orphaned files ===");
        
        $directories = [
            config('pdftools.storage.uploads_path'),
            config('pdftools.storage.outputs_path')
        ];

        $totalOrphanedFiles = 0;
        $totalOrphanedSize = 0;

        foreach ($directories as $directory) {
            if (!Storage::exists($directory)) {
                $this->line("Directory does not exist: {$directory}");
                continue;
            }

            $this->line("Scanning directory: {$directory}");
            $files = Storage::files($directory);
            
            foreach ($files as $file) {
                $lastModified = Carbon::createFromTimestamp(Storage::lastModified($file));
                
                if ($lastModified->lt($cutoffTime)) {
                    $fileSize = Storage::size($file);
                    $totalOrphanedFiles++;
                    $totalOrphanedSize += $fileSize;
                    
                    if (!$isDryRun) {
                        Storage::delete($file);
                        $this->line("  Deleted orphaned file: {$file} (" . $this->formatBytes($fileSize) . ")");
                    } else {
                        $this->line("  Would delete orphaned file: {$file} (" . $this->formatBytes($fileSize) . ")");
                    }
                }
            }
        }

        if ($totalOrphanedFiles > 0) {
            $this->info("Cleaned up {$totalOrphanedFiles} orphaned files (" . $this->formatBytes($totalOrphanedSize) . ")");
        } else {
            $this->info("No orphaned files found");
        }
    }

    /**
     * Format bytes into human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfProcessing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tool_name',
        'original_filename',
        'processed_filename',
        'file_size',
        'processed_file_size',
        'supabase_input_path',
        'supabase_output_path',
        'supabase_input_url',
        'supabase_output_url',
        'status',
        'settings',
        'error_message',
        'processing_time',
        'completed_at'
    ];

    protected $casts = [
        'settings' => 'array',
        'completed_at' => 'datetime',
        'file_size' => 'integer',
        'processed_file_size' => 'integer',
        'processing_time' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'completed' => 'green',
            'failed' => 'red',
            'processing' => 'blue',
            'pending' => 'yellow',
            default => 'gray'
        };
    }

    public function getFormattedFileSizeAttribute(): string
    {
        return $this->formatBytes($this->file_size);
    }

    public function getFormattedProcessedFileSizeAttribute(): string
    {
        return $this->formatBytes($this->processed_file_size);
    }

    public function getFormattedProcessingTimeAttribute(): string
    {
        if (!$this->processing_time) return 'N/A';
        
        if ($this->processing_time < 60) {
            return $this->processing_time . 's';
        }
        
        $minutes = floor($this->processing_time / 60);
        $seconds = $this->processing_time % 60;
        return $minutes . 'm ' . $seconds . 's';
    }

    private function formatBytes($bytes): string
    {
        if ($bytes === 0) return '0 B';
        
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoProcessing extends Model
{
    protected $fillable = [
        'user_id',
        'tool_name',
        'original_filename',
        'converted_filename',
        'original_path',
        'converted_path',
        'original_file_size',
        'converted_file_size',
        'status',
        'progress',
        'processing_settings',
        'error_message',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'processing_settings' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'integer',
        'original_file_size' => 'integer',
        'converted_file_size' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

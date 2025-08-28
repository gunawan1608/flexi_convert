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
        'processed_filename',
        'file_size',
        'processed_file_size',
        'status',
        'progress',
        'settings',
        'error_message',
        'completed_at'
    ];

    protected $casts = [
        'settings' => 'array',
        'completed_at' => 'datetime',
        'progress' => 'integer',
        'file_size' => 'integer',
        'processed_file_size' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

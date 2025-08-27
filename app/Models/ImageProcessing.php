<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageProcessing extends Model
{
    use HasFactory;

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
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

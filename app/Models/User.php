<?php

namespace App\Models;

use App\Notifications\CustomPasswordResetNotification;
use App\Notifications\CustomVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'company',
        'bio',
        'timezone',
        'language',
        'email_notifications',
        'marketing_emails',
        'total_conversions',
        'storage_used',
        'last_conversion_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'last_conversion_at' => 'datetime',
    ];

    public function pdfProcessings(): HasMany
    {
        return $this->hasMany(PdfProcessing::class);
    }

    public function imageProcessings(): HasMany
    {
        return $this->hasMany(ImageProcessing::class);
    }

    public function getTotalProcessingsAttribute()
    {
        return $this->pdfProcessings()->count() + $this->imageProcessings()->count();
    }

    public function getCompletedProcessingsAttribute()
    {
        return $this->pdfProcessings()->where('status', 'completed')->count() + 
               $this->imageProcessings()->where('status', 'completed')->count();
    }

    public function getTodayProcessingsAttribute()
    {
        return $this->pdfProcessings()->whereDate('created_at', today())->count() + 
               $this->imageProcessings()->whereDate('created_at', today())->count();
    }

    public function getStorageUsedAttribute()
    {
        $pdfStorage = $this->pdfProcessings()->sum('file_size') + $this->pdfProcessings()->sum('processed_file_size');
        $imageStorage = $this->imageProcessings()->sum('file_size') + $this->imageProcessings()->sum('processed_file_size');
        return $pdfStorage + $imageStorage;
    }

    public function getStorageUsedHumanAttribute(): string
    {
        $bytes = $this->storage_used;
        if ($bytes === 0) return '0 B';
        
        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmail);
    }

    /**
     * Send the password reset notification.
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomPasswordResetNotification($token));
    }
}

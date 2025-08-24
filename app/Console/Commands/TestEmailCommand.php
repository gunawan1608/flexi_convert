<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmailCommand extends Command
{
    protected $signature = 'test:email {email}';
    protected $description = 'Test email configuration';

    public function handle()
    {
        $email = $this->argument('email');
        
        try {
            Mail::raw('Test email dari FlexiConvert Laravel', function ($message) use ($email) {
                $message->to($email)
                        ->subject('Test Email Configuration - FlexiConvert');
            });
            
            $this->info("✅ Email berhasil dikirim ke: {$email}");
            $this->info("Cek inbox atau spam folder.");
            
        } catch (\Exception $e) {
            $this->error("❌ Error mengirim email:");
            $this->error($e->getMessage());
        }
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('company')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('company');
            $table->string('timezone')->default('Asia/Jakarta')->after('bio');
            $table->string('language', 10)->default('en')->after('timezone');
            $table->boolean('email_notifications')->default(true)->after('language');
            $table->boolean('marketing_emails')->default(false)->after('email_notifications');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'company', 
                'bio',
                'timezone',
                'language',
                'email_notifications',
                'marketing_emails'
            ]);
        });
    }
};

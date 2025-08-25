<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            $table->string('stored_filename')->nullable()->after('original_filename');
            $table->string('converted_filename')->nullable()->after('stored_filename');
            $table->string('conversion_type')->default('document')->after('status');
            $table->json('settings')->nullable()->after('conversion_type');
            $table->integer('progress')->default(0)->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('conversions', function (Blueprint $table) {
            $table->dropColumn(['stored_filename', 'converted_filename', 'conversion_type', 'settings', 'progress']);
        });
    }
};

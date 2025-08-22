<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('total_conversions')->default(0);
            $table->integer('storage_used')->default(0); // in bytes
            $table->timestamp('last_conversion_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['total_conversions', 'storage_used', 'last_conversion_at']);
        });
    }
};

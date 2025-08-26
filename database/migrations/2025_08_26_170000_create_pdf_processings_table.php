<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_processings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('tool_name');
            $table->string('original_filename')->nullable();
            $table->string('processed_filename')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->bigInteger('processed_file_size')->nullable();
            $table->string('input_path')->nullable();
            $table->string('output_path')->nullable();
            $table->string('output_filename')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('settings')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('processing_time')->nullable(); // in seconds
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
            $table->index('tool_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_processings');
    }
};

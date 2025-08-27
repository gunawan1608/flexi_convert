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
        Schema::create('image_processings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('tool_name');
            $table->string('original_filename');
            $table->string('processed_filename')->nullable();
            $table->bigInteger('file_size');
            $table->bigInteger('processed_file_size')->nullable();
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing');
            $table->integer('progress')->default(0);
            $table->json('settings')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_processings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syllabi', function (Blueprint $table) {
            $table->id('syllabi_id');
            $table->foreignId('request_id')
                  ->constrained('document_requests', 'request_id')
                  ->cascadeOnDelete();
            $table->string('course_name')->nullable();
            $table->string('syllabi_availability', 100)->nullable();
            $table->integer('no_pages')->nullable();
            $table->string('drf_availability', 100)->nullable();
            $table->string('drf_no', 100)->nullable();
            $table->date('drf_date')->nullable();
            $table->date('drf_received_date')->nullable();
            $table->string('scanned_drf')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syllabi');
    }
};
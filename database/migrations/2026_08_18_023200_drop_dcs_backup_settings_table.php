<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dcs_backup_settings');
    }

    public function down(): void
    {
        Schema::create('dcs_backup_settings', function (Blueprint $table) {
            $table->id();
            $table->string('drive_folder_id')->nullable();
            $table->string('service_account_path')->nullable();
            $table->timestamp('last_backup_at')->nullable();
            $table->string('last_backup_status')->nullable();
            $table->timestamps();
        });
    }
};

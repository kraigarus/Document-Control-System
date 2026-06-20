<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_type', function (Blueprint $table) {
            $table->id('version_id');
            $table->string('version_name');
        });

        DB::table('version_type')->insert([
            ['version_id' => 1, 'version_name' => 'new'],
            ['version_id' => 2, 'version_name' => 'revised'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('version_type');
    }
};
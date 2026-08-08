<?php
// 003 — version_type

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_type', function (Blueprint $table) {
            $table->id();
            $table->string('version_name');
        });

        DB::table('version_type')->insert([
            ['id' => 1, 'version_name' => 'New'],
            ['id' => 2, 'version_name' => 'Revised'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('version_type');
    }
};

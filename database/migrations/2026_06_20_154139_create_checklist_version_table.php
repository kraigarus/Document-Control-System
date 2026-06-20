<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_version', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')
                  ->constrained('checklist_types', 'checklist_id')
                  ->cascadeOnDelete();
            $table->foreignId('version_id')
                  ->constrained('version_type', 'version_id')
                  ->cascadeOnDelete();
        });

        DB::table('checklist_version')->insert([
            ['id' => 1, 'checklist_id' => 1, 'version_id' => 1],
            ['id' => 2, 'checklist_id' => 3, 'version_id' => 1],
            ['id' => 3, 'checklist_id' => 5, 'version_id' => 1],
            ['id' => 4, 'checklist_id' => 1, 'version_id' => 2],
            ['id' => 5, 'checklist_id' => 2, 'version_id' => 2],
            ['id' => 6, 'checklist_id' => 3, 'version_id' => 2],
            ['id' => 7, 'checklist_id' => 4, 'version_id' => 2],
            ['id' => 8, 'checklist_id' => 5, 'version_id' => 2],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_version');
    }
};
<?php
// 022 — colleges

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dcs_colleges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')
                  ->nullable()
                  ->constrained('offices')
                  ->cascadeOnDelete();
            $table->string('college_code', 50)->unique();
            $table->string('college_name');
            $table->timestamps();
        });

        DB::table('dcs_colleges')->insert([
            ['id' => 1, 'office_id' => 28, 'college_code' => 'CCS',   'college_name' => 'College of Computer Studies', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'office_id' => 29, 'college_code' => 'CEA',   'college_name' => 'College of Engineering and Architecture', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'office_id' => 30, 'college_code' => 'CHS',   'college_name' => 'College of Health Sciences', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'office_id' => 27, 'college_code' => 'CTDE',  'college_name' => 'College of Technological and Developmental Education', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'office_id' => 31, 'college_code' => 'CTHBM', 'college_name' => 'College of Tourism, Hospitality and Business Management', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'office_id' => 26, 'college_code' => 'CAS',   'college_name' => 'College of Arts and Sciences', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dcs_colleges');
    }
};
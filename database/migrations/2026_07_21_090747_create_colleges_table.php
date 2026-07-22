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
        Schema::create('colleges', function (Blueprint $table) {
            $table->id('college_id');
            $table->string('college_code', 50)->unique();
            $table->string('college_name');
            $table->timestamps();
        });

        // Insert Default Data immediately after table creation
        DB::table('colleges')->insert([
            ['college_id' => 1, 'college_code' => 'CCS', 'college_name' => 'College of Computer Studies', 'created_at' => now(), 'updated_at' => now()],
            ['college_id' => 2, 'college_code' => 'CEA', 'college_name' => 'College of Engineering and Architecture', 'created_at' => now(), 'updated_at' => now()],
            ['college_id' => 3, 'college_code' => 'CHS', 'college_name' => 'College of Health Sciences', 'created_at' => now(), 'updated_at' => now()],
            ['college_id' => 4, 'college_code' => 'CTDE', 'college_name' => 'College of Technological and Developmental Education', 'created_at' => now(), 'updated_at' => now()],
            ['college_id' => 5, 'college_code' => 'CTHBM', 'college_name' => 'College of Tourism, Hospitality and Business Management', 'created_at' => now(), 'updated_at' => now()],
            ['college_id' => 6, 'college_code' => 'CAS', 'college_name' => 'College of Arts and Sciences', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('colleges');
    }
};

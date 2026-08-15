<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dcs_dcn_offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dcn_id')
                  ->constrained('dcs_document_change_notice')
                  ->cascadeOnDelete();
            $table->foreignId('office_id')
                  ->constrained('offices')
                  ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['dcn_id', 'office_id']);
            $table->index('dcn_id');
        });

        $existing = DB::table('dcs_document_change_notice')
            ->whereNotNull('office_id')
            ->get(['id', 'office_id']);

        foreach ($existing as $row) {
            DB::table('dcs_dcn_offices')->insert([
                'dcn_id'     => $row->id,
                'office_id'  => $row->office_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dcs_dcn_offices');
    }
};

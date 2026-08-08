<?php
// 005 — offices

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('office_name');
            $table->string('status', 20)->default('active');
        });

        $offices = [
            ['id' => 1,  'office_name' => 'President', 'status' => 'active'],
            ['id' => 2,  'office_name' => 'VP for Academic Affairs', 'status' => 'active'],
            ['id' => 3,  'office_name' => 'VP for Administration & Finance', 'status' => 'active'],
            ['id' => 4,  'office_name' => 'VP for Research, Innovation & Collaboration', 'status' => 'active'],
            ['id' => 5,  'office_name' => 'Chief Administrative Officer', 'status' => 'active'],
            ['id' => 6,  'office_name' => 'Center for Quality Assurance', 'status' => 'active'],
            ['id' => 7,  'office_name' => 'Supervising Administrative Officer', 'status' => 'active'],
            ['id' => 8,  'office_name' => 'Board Secretary', 'status' => 'active'],
            ['id' => 9,  'office_name' => 'Internal Audit Unit', 'status' => 'active'],
            ['id' => 10, 'office_name' => 'Legal Affairs Unit', 'status' => 'active'],
            ['id' => 11, 'office_name' => 'Institutional Planning and Development Unit', 'status' => 'active'],
            ['id' => 12, 'office_name' => 'Center for International Relations and Linkages', 'status' => 'active'],
            ['id' => 13, 'office_name' => 'Buhi Campus', 'status' => 'active'],
            ['id' => 14, 'office_name' => 'Human Resource Management and Development Unit', 'status' => 'active'],
            ['id' => 15, 'office_name' => 'Health Services Unit', 'status' => 'active'],
            ['id' => 16, 'office_name' => 'General Services Unit', 'status' => 'active'],
            ['id' => 17, 'office_name' => 'Supply and Property Management Unit', 'status' => 'active'],
            ['id' => 18, 'office_name' => 'Accounting Unit', 'status' => 'active'],
            ['id' => 19, 'office_name' => 'Budget Unit', 'status' => 'active'],
            ['id' => 20, 'office_name' => 'Cash Unit', 'status' => 'active'],
            ['id' => 21, 'office_name' => 'Procurement Unit', 'status' => 'active'],
            ['id' => 22, 'office_name' => 'Project Management Unit', 'status' => 'active'],
            ['id' => 23, 'office_name' => 'Information and Communications Technology Unit', 'status' => 'active'],
            ['id' => 24, 'office_name' => 'Records and Freedom of Information Unit', 'status' => 'active'],
            ['id' => 25, 'office_name' => 'Graduate School', 'status' => 'active'],
            ['id' => 26, 'office_name' => 'College of Arts & Sciences', 'status' => 'active'],
            ['id' => 27, 'office_name' => 'College of Technological and Developmental Education', 'status' => 'active'],
            ['id' => 28, 'office_name' => 'College of Computer Studies', 'status' => 'active'],
            ['id' => 29, 'office_name' => 'College of Engineering & Architecture', 'status' => 'active'],
            ['id' => 30, 'office_name' => 'College of Health Sciences', 'status' => 'active'],
            ['id' => 31, 'office_name' => 'College of Tourism, Hospitality & Business Management', 'status' => 'active'],
            ['id' => 32, 'office_name' => 'Information and Alumni Affairs Unit', 'status' => 'active'],
            ['id' => 33, 'office_name' => 'Center for Human Rights Education', 'status' => 'active'],
            ['id' => 34, 'office_name' => 'Center for Gender and Development', 'status' => 'active'],
            ['id' => 35, 'office_name' => 'Student Registration and Records', 'status' => 'active'],
            ['id' => 36, 'office_name' => 'Student Testing and Admission', 'status' => 'active'],
            ['id' => 37, 'office_name' => 'Learning Resources and Development', 'status' => 'active'],
            ['id' => 38, 'office_name' => 'Academic Center for Continuing Enhancement Services for Students', 'status' => 'inactive'],
            ['id' => 39, 'office_name' => 'National Service Training Program', 'status' => 'active'],
            ['id' => 40, 'office_name' => 'Student Affairs Services', 'status' => 'active'],
            ['id' => 41, 'office_name' => 'Guidance and Counseling', 'status' => 'active'],
            ['id' => 42, 'office_name' => 'Competency and Assessment Center-TESDA', 'status' => 'active'],
            ['id' => 43, 'office_name' => 'Research and Development Services Office', 'status' => 'active'],
            ['id' => 44, 'office_name' => 'Extension and Community Services Office', 'status' => 'active'],
            ['id' => 45, 'office_name' => 'Research Ethics Services', 'status' => 'active'],
            ['id' => 46, 'office_name' => 'Center for Rinconada Culture and Arts', 'status' => 'active'],
            ['id' => 47, 'office_name' => 'Center for Intellectual Property Management', 'status' => 'active'],
            ['id' => 48, 'office_name' => 'Production and Auxiliary Services', 'status' => 'active'],
            ['id' => 49, 'office_name' => 'AI Research Center for Community Development', 'status' => 'active'],
            ['id' => 50, 'office_name' => 'Technology Transfer Office', 'status' => 'active'],
            ['id' => 51, 'office_name' => 'Center for Futures Thinking and Strategic Foresight', 'status' => 'active'],
            ['id' => 52, 'office_name' => 'Center for Future Energy and Sustainable Technology', 'status' => 'active'],
            ['id' => 53, 'office_name' => 'Center for Research in Integrative, Social and Special Sciences and Policy', 'status' => 'active'],
            ['id' => 54, 'office_name' => 'Rinconada Center for Environmental Sustainability', 'status' => 'active'],
            ['id' => 55, 'office_name' => 'Broadcast Center', 'status' => 'active'],
            ['id' => 56, 'office_name' => 'Document Control', 'status' => 'active'],
            ['id' => 57, 'office_name' => 'Top Management', 'status' => 'active'],
            ['id' => 58, 'office_name' => 'Faculty Association Inc', 'status' => 'active'],
            ['id' => 59, 'office_name' => 'Student Publication', 'status' => 'active'],
        ];

        DB::table('offices')->insert($offices);
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
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
            ['office_name' => 'President', 'status' => 'active'],
            ['office_name' => 'VP for Academic Affairs', 'status' => 'active'],
            ['office_name' => 'VP for Administration & Finance', 'status' => 'active'],
            ['office_name' => 'VP for Research, Innovation & Collaboration', 'status' => 'active'],
            ['office_name' => 'Chief Administrative Officer', 'status' => 'active'],
            ['office_name' => 'Center for Quality Assurance', 'status' => 'active'],
            ['office_name' => 'Supervising Administrative Officer', 'status' => 'active'],
            ['office_name' => 'Board Secretary', 'status' => 'active'],
            ['office_name' => 'Internal Audit Unit', 'status' => 'active'],
            ['office_name' => 'Legal Affairs Unit', 'status' => 'active'],
            ['office_name' => 'Institutional Planning and Development Unit', 'status' => 'active'],
            ['office_name' => 'Center for International Relations and Linkages', 'status' => 'active'],
            ['office_name' => 'Buhi Campus', 'status' => 'active'],
            ['office_name' => 'Human Resource Management and Development Unit', 'status' => 'active'],
            ['office_name' => 'Health Services Unit', 'status' => 'active'],
            ['office_name' => 'General Services Unit', 'status' => 'active'],
            ['office_name' => 'Supply and Property Management Unit', 'status' => 'active'],
            ['office_name' => 'Accounting Unit', 'status' => 'active'],
            ['office_name' => 'Budget Unit', 'status' => 'active'],
            ['office_name' => 'Cash Unit', 'status' => 'active'],
            ['office_name' => 'Procurement Unit', 'status' => 'active'],
            ['office_name' => 'Project Management Unit', 'status' => 'active'],
            ['office_name' => 'Information and Communications Technology Unit', 'status' => 'active'],
            ['office_name' => 'Records and Freedom of Information Unit', 'status' => 'active'],
            ['office_name' => 'Graduate School', 'status' => 'active'],
            ['office_name' => 'College of Arts & Sciences', 'status' => 'active'],
            ['office_name' => 'College of Technological and Developmental Education', 'status' => 'active'],
            ['office_name' => 'College of Computer Studies', 'status' => 'active'],
            ['office_name' => 'College of Engineering & Architecture', 'status' => 'active'],
            ['office_name' => 'College of Health Sciences', 'status' => 'active'],
            ['office_name' => 'College of Tourism, Hospitality & Business Management', 'status' => 'active'],
            ['office_name' => 'Information and Alumni Affairs Unit', 'status' => 'active'],
            ['office_name' => 'Center for Human Rights Education', 'status' => 'active'],
            ['office_name' => 'Center for Gender and Development', 'status' => 'active'],
            ['office_name' => 'Student Registration and Records', 'status' => 'active'],
            ['office_name' => 'Student Testing and Admission', 'status' => 'active'],
            ['office_name' => 'Learning Resources and Development', 'status' => 'active'],
            ['office_name' => 'Academic Center for Continuing Enhancement Services for Students', 'status' => 'inactive'],
            ['office_name' => 'National Service Training Program', 'status' => 'active'],
            ['office_name' => 'Student Affairs Services', 'status' => 'active'],
            ['office_name' => 'Guidance and Counseling', 'status' => 'active'],
            ['office_name' => 'Competency and Assessment Center-TESDA', 'status' => 'active'],
            ['office_name' => 'Research and Development Services Office', 'status' => 'active'],
            ['office_name' => 'Extension and Community Services Office', 'status' => 'active'],
            ['office_name' => 'Research Ethics Services', 'status' => 'active'],
            ['office_name' => 'Center for Rinconada Culture and Arts', 'status' => 'active'],
            ['office_name' => 'Center for Intellectual Property Management', 'status' => 'active'],
            ['office_name' => 'Production and Auxiliary Services', 'status' => 'active'],
            ['office_name' => 'AI Research Center for Community Development', 'status' => 'active'],
            ['office_name' => 'Technology Transfer Office', 'status' => 'active'],
            ['office_name' => 'Center for Futures Thinking and Strategic Foresight', 'status' => 'active'],
            ['office_name' => 'Center for Future Energy and Sustainable Technology', 'status' => 'active'],
            ['office_name' => 'Center for Research in Integrative, Social and Special Sciences and Policy', 'status' => 'active'],
            ['office_name' => 'Rinconada Center for Environmental Sustainability', 'status' => 'active'],
            ['office_name' => 'Broadcast Center', 'status' => 'active'],
            ['office_name' => 'Document Control', 'status' => 'active'],
            ['office_name' => 'Top Management', 'status' => 'active'],
            ['office_name' => 'Faculty Association Inc', 'status' => 'active'],
            ['office_name' => 'Student Publication', 'status' => 'active'],
        ];

        DB::table('offices')->insert($offices);
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
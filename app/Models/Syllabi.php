<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Syllabi extends Model
{
    use HasFactory;

    protected $table = 'dcs_syllabi';

    protected $fillable = [
        'request_id',
        'doc_type_id',
        'college_id',
        'program_id',
        'semester_id',
        'school_year_id',
        'course_id',
        'is_available',
        'no_copies',
        'no_pages',
        'date_received',
        'time_received',
    ];

    public function request()
    {
        return $this->belongsTo(DocumentRequest::class, 'request_id');
    }

    public function docType()
    {
        return $this->belongsTo(DocType::class, 'doc_type_id');
    }

    public function college()
    {
        return $this->belongsTo(College::class, 'college_id');
    }

    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function schoolYear()
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id');
    }

    public function course()
    {
        return $this->belongsTo(ProgramCourse::class, 'course_id');
    }

    public function drfs()
    {
        return $this->hasMany(SyllabiDrf::class, 'syllabi_id');
    }
}
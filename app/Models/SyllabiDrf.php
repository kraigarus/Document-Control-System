<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyllabiDrf extends Model
{
    use HasFactory;

    protected $table = 'dcs_syllabi_drf';

    protected $fillable = [
        'syllabi_id',
        'faculty_id',
        'faculty_name',
        'is_drf_available',
        'drf_no',
        'drf_date',
        'drf_received_date',
        'scanned_drf',
    ];

    protected function casts(): array
    {
        return [
            'is_drf_available' => 'boolean',
        ];
    }

    public function syllabus()
    {
        return $this->belongsTo(Syllabi::class, 'syllabi_id');
    }

    public function facultyMaster()
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }
}
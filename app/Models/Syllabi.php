<?php
// app/Models/Syllabi.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Syllabi extends Model
{
    protected $table = 'syllabi';
    protected $primaryKey = 'syllabi_id';

    protected $fillable = [
        'request_id',
        'college_id',
        'program_id',
        'semester_id',
        'school_year_id',
        'drf_id',
        'course_name',
        'syllabi_availability',
        'no_copies',
        'no_pages',
        'date_received',
        'time_received',
        'drf_availability',
    ];

    protected $casts = [
        'date_received' => 'date',
    ];

    // Relationships
    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id', 'college_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id', 'program_id');
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id', 'semester_id');
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class, 'school_year_id', 'school_year_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'request_id', 'request_id');
    }

    public function drf(): BelongsTo
    {
        return $this->belongsTo(DocumentRequestForm::class, 'drf_id', 'drf_id');
    }

    public function rowFaculty(): HasMany
    {
        return $this->hasMany(SyllabiFaculty::class, 'syllabi_id', 'syllabi_id');
    }

    /** Convenience accessor: "Dr. Cruz, Dr. Santos" for display/review purposes. */
    public function getFacultyNamesAttribute(): string
    {
        return $this->relationLoaded('rowFaculty')
            ? $this->rowFaculty->pluck('faculty_name')->implode(', ')
            : '';
    }
}
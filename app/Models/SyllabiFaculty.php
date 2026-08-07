<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyllabiFaculty extends Model
{
    protected $table = 'syllabi_row_faculty';        // ← was 'syllabi_faculty' (never existed)
    protected $primaryKey = 'syllabi_row_faculty_id'; // ← was 'syllabi_faculty_id'

    protected $fillable = ['syllabi_id', 'faculty_id', 'faculty_name'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'faculty_id', 'faculty_id');
    }

    public function syllabi(): BelongsTo
    {
        return $this->belongsTo(Syllabi::class, 'syllabi_id', 'syllabi_id');
    }
}
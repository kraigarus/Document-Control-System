<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Faculty extends Model
{
    protected $table = 'faculties';

    protected $fillable = [
        'faculty_name',
        'college_id',
    ];

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id');
    }
}

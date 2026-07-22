<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Program extends Model
{
    protected $table = 'programs';
    protected $primaryKey = 'program_id';

    protected $fillable = [
        'college_id',
        'program_name',
    ];

    /**
     * Get the college that owns the program.
     */
    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class, 'college_id');
    }
}

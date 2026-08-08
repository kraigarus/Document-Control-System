<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class College extends Model
{
    protected $table = 'colleges';

    protected $fillable = [
        'office_id',
        'college_code',
        'college_name',
    ];

    /**
     * Get the administrative office associated with this college.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
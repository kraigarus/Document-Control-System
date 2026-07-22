<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class College extends Model
{
    protected $table = 'colleges';
    protected $primaryKey = 'college_id';

    protected $fillable = [
        'college_name',
    ];

    /**
     * Get the programs associated with the college.
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class, 'college_id', 'college_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolYear extends Model
{
    protected $table = 'school_years';
    protected $primaryKey = 'school_year_id';

    protected $fillable = [
        'school_year',
    ];
}

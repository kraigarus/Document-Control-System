<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolYear extends Model
{
    protected $table = 'dcs_school_years';

    protected $fillable = [
        'school_year',
    ];
}

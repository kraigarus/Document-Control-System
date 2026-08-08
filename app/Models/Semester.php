<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    protected $table = 'dcs_semesters';

    protected $fillable = [
        'semester_name',
    ];
}

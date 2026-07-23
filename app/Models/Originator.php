<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Originator extends Model
{
    protected $primaryKey = 'originator_id';

    protected $fillable = [
        'originator_name',
    ];
}
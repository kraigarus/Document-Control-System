<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Originator extends Model
{
    protected $table = 'dcs_originators';
    
    protected $fillable = [
        'originator_name',
    ];
}
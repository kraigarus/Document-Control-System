<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpcrRating extends Model
{
    protected $table = 'dcs_opcr_ratings';
    
    protected $fillable = [
        'request_id',
        'sub_type',
        'rating_q',
        'rating_e',
        'rating_t',
        'rating_a',
    ];
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrfOffice extends Model
{
    protected $table = 'drf_offices';
    protected $primaryKey = 'id';

    protected $fillable = [
        'drf_id',
        'office_id',
    ];

    public function drf()
    {
        return $this->belongsTo(DocumentRequestForm::class, 'drf_id', 'drf_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id', 'office_id');
    }
}
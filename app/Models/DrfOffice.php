<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrfOffice extends Model
{
    protected $table = 'drf_offices';
    protected $primaryKey = 'id';

    protected $fillable = [
        'request_id',
        'office_id',
    ];

    public function request()
    {
        return $this->belongsTo(DocumentRequest::class, 'request_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterlistOrigin extends Model
{
    protected $table = 'masterlist_origins';
    protected $primaryKey = 'origin_id';

    protected $fillable = [
        'masterlist_id',
        'office_id',
        'originator_name',
    ];

    public function masterlist()
    {
        return $this->belongsTo(MasterlistRegistration::class, 'masterlist_id', 'masterlist_id');
    }

    public function office()
    {
        return $this->belongsTo(\App\Models\Office::class, 'office_id', 'office_id');
    }
}
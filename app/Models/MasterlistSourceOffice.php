<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterlistSourceOffice extends Model
{
    protected $table = 'masterlist_source_offices';
    protected $primaryKey = 'masterlist_office_id';

    protected $fillable = [
        'masterlist_id',
        'office_id',
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
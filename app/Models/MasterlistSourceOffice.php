<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterlistSourceOffice extends Model
{
    protected $table = 'dcs_masterlist_source_offices';

    protected $fillable = [
        'masterlist_id',
        'office_id',
    ];

    public function masterlist()
    {
        return $this->belongsTo(MasterlistRegistration::class, 'masterlist_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
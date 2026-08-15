<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DcnOffice extends Model
{
    protected $table = 'dcs_dcn_offices';

    protected $fillable = [
        'dcn_id',
        'office_id',
    ];

    public function dcn()
    {
        return $this->belongsTo(DocumentChangeNotice::class, 'dcn_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}

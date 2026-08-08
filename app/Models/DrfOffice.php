<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrfOffice extends Model
{
    protected $table = 'dcs_drf_offices';

    protected $fillable = [
        'document_request_form_id',
        'office_id',
    ];

    public function documentRequestForm()
    {
        return $this->belongsTo(DocumentRequestForm::class, 'document_request_form_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
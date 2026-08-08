<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterlistRelatedDoc extends Model
{
    protected $table = 'dcs_masterlist_related_docs';
    public $timestamps = false;

    protected $fillable = [
        'masterlist_id',
        'related_doc_id',
    ];

    public function masterlist()
    {
        return $this->belongsTo(MasterlistRegistration::class, 'masterlist_id');
    }

    public function relatedDoc()
    {
        return $this->belongsTo(MasterlistRegistration::class, 'related_doc_id');
    }
}
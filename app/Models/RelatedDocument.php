<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RelatedDocument extends Model
{
    protected $table = 'related_documents';
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
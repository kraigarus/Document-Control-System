<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetrievalOffice extends Model
{
    protected $table = 'retrieval_offices';
    public $timestamps = false;

    protected $fillable = [
        'retrieval_id',
        'office_id',
        'copies',
    ];

    public function retrieval()
    {
        return $this->belongsTo(DocumentRetrieval::class, 'retrieval_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
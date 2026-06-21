<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocRevision extends Model
{
    protected $table = 'doc_revision';
    protected $primaryKey = 'revision_id';

    protected $fillable = [
        'dcn_id',
        'title',
        'document_no',
        'effectivity_date',
        'revision_no',
        'scanned_copy',
        'brief_purpose',
    ];

    protected $casts = [
        'effectivity_date' => 'date',
    ];

    public function dcn()
    {
        return $this->belongsTo(DocumentChangeNotice::class, 'dcn_id');
    }
}
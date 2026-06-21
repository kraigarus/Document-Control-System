<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRetrieval extends Model
{
    protected $table = 'document_retrieval';
    protected $primaryKey = 'retrieval_id';

    protected $fillable = [
        'checklist_id',
        'version_id',
        'request_id',
        'doc_type_id',
        'doc_retrieval_date_actual',
        'doc_retrieval_time_actual',
        'doc_retrieval_date_file',
        'doc_retrieval_time_file',
        'time_spent',
        'remarks',
        'scanned_retrieval',
        'created_by',
    ];

    protected $casts = [
        'doc_retrieval_date_actual' => 'date',
        'doc_retrieval_date_file' => 'date',
    ];

    public function checklist()
    {
        return $this->belongsTo(ChecklistType::class, 'checklist_id');
    }

    public function version()
    {
        return $this->belongsTo(VersionType::class, 'version_id');
    }

    public function request()
    {
        return $this->belongsTo(DocumentRequest::class, 'request_id');
    }

    public function docType()
    {
        return $this->belongsTo(DocType::class, 'doc_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(Account::class, 'created_by');
    }

    public function offices()
    {
        return $this->hasMany(RetrievalOffice::class, 'retrieval_id');
    }
}
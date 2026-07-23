<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequestForm extends Model
{
    protected $table = 'document_request_form';
    protected $primaryKey = 'drf_id';

    protected $fillable = [
        'checklist_id',
        'version_id',
        'request_id',
        'doc_type_id',
        'drf_no',
        'drf_date',
        'drf_receipt_date',
        'drf_receipt_time',
        'doc_title',
        'scanned_drf',
        'created_by',
    ];

    protected $casts = [
        'drf_date' => 'date',
        'drf_receipt_date' => 'date',
        'drf_receipt_time' => 'datetime:H:i',
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

    public function drfOffices()
    {
        return $this->hasMany(DrfOffice::class, 'drf_id', 'drf_id');
    }

    public function creator()
    {
        return $this->belongsTo(Account::class, 'created_by');
    }
}
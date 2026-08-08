<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentChangeNotice extends Model
{
    protected $table = 'document_change_notice';

    protected $fillable = [
        'checklist_id',
        'version_id',
        'request_id',
        'doc_type_id',
        'dcn_no',
        'dcn_date',
        'dcn_receipt_date',
        'dcn_receipt_time',
        'office_id',
        'scanned_dcn',
        'created_by',
    ];

    protected $casts = [
        'dcn_date' => 'date',
        'dcn_receipt_date' => 'date',
        'dcn_receipt_time' => 'datetime:H:i',
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

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }

    public function creator()
    {
        return $this->belongsTo(Account::class, 'created_by');
    }

    public function revisions()
    {
        return $this->hasMany(DocRevision::class, 'dcn_id');
    }
}

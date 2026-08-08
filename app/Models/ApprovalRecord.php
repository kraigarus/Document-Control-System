<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRecord extends Model
{
    protected $table = 'approval_records';
    public $timestamps = false;

    protected $fillable = [
        'checklist_id',
        'version_id',
        'request_id',
        'doc_type_id',
        'approval_body_id',
        'approval_date',
        'approval_no',
    ];

    protected $casts = [
        'approval_date' => 'date',
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

    public function approvalBody()
    {
        return $this->belongsTo(ApprovalBody::class, 'approval_body_id');
    }
}

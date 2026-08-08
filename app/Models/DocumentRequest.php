<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentRequest extends Model
{
    protected $table = 'document_requests';

    protected $fillable = [
        'version_id',
        'doc_type_id',
        'sub_type_id',
        'approval_status',
        'created_by',
        'updated_by',
    ];

    public function version()
    {
        return $this->belongsTo(VersionType::class, 'version_id');
    }

    public function docType()
    {
        return $this->belongsTo(DocType::class, 'doc_type_id');
    }

    public function subType()
    {
        return $this->belongsTo(DocType::class, 'sub_type_id');
    }

    public function creator()
    {
        return $this->belongsTo(Account::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(Account::class, 'updated_by');
    }

    public function approvalRecords()
    {
        return $this->hasMany(ApprovalRecord::class, 'request_id');
    }

    public function documentRequestForm()
    {
        return $this->hasOne(DocumentRequestForm::class, 'request_id');
    }

    public function documentChangeNotice()
    {
        return $this->hasOne(DocumentChangeNotice::class, 'request_id');
    }

    public function documentDistribution()
    {
        return $this->hasOne(DocumentDistribution::class, 'request_id');
    }

    public function documentRetrieval()
    {
        return $this->hasOne(DocumentRetrieval::class, 'request_id');
    }

    public function masterlistRegistration()
    {
        return $this->hasOne(MasterlistRegistration::class, 'request_id');
    }

    public function syllabi()
    {
        return $this->hasMany(Syllabi::class, 'request_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MasterlistRegistration extends Model
{
    protected $table = 'masterlist_registration';

    protected $fillable = [
        'checklist_id',
        'version_id',
        'request_id',
        'doc_type_id',
        'doc_no',
        'doc_receipt_date',
        'doc_receipt_time',
        'doc_registered_date',
        'doc_registered_time',
        'time_spent',
        'doc_title',
        'effectivity_date',
        'revise_no',
        'no_pages',
        'originator_name',
        'deadline',
        'brief_purpose',
        'scanned_masterlist',
        'created_by',
    ];

    protected $casts = [
        'doc_receipt_date' => 'date',
        'doc_registered_date' => 'date',
        'effectivity_date' => 'date',
        'deadline' => 'date',
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

    public function relatedDocuments()
    {
        return $this->belongsToMany(
            MasterlistRegistration::class,
            'masterlist_related_docs',
            'masterlist_id',
            'related_doc_id'
        )->withTimestamps();
    }

    public function relatedToDocuments()
    {
        return $this->belongsToMany(
            MasterlistRegistration::class,
            'masterlist_related_docs',
            'related_doc_id',
            'masterlist_id'
        )->withTimestamps();
    }

    public function allRelatedDocuments()
    {
        return $this->relatedDocuments->merge($this->relatedToDocuments)->unique('id');
    }

    public function sourceOffices()
    {
        return $this->hasMany(MasterlistSourceOffice::class, 'masterlist_id');
    }
}
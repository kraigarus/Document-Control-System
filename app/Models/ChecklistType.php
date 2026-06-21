<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistType extends Model
{
    protected $table = 'checklist_types';
    protected $primaryKey = 'checklist_id';
    public $timestamps = false;

    protected $fillable = [
        'checklist_name',
    ];

    public function versions()
    {
        return $this->belongsToMany(VersionType::class, 'checklist_version', 'checklist_id', 'version_id');
    }

    public function approvalRecords()
    {
        return $this->hasMany(ApprovalRecord::class, 'checklist_id');
    }

    public function documentRequestForms()
    {
        return $this->hasMany(DocumentRequestForm::class, 'checklist_id');
    }

    public function documentChangeNotices()
    {
        return $this->hasMany(DocumentChangeNotice::class, 'checklist_id');
    }

    public function documentDistributions()
    {
        return $this->hasMany(DocumentDistribution::class, 'checklist_id');
    }

    public function documentRetrievals()
    {
        return $this->hasMany(DocumentRetrieval::class, 'checklist_id');
    }

    public function masterlistRegistrations()
    {
        return $this->hasMany(MasterlistRegistration::class, 'checklist_id');
    }
}
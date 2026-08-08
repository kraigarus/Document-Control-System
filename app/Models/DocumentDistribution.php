<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentDistribution extends Model
{
    protected $table = 'document_distribution';

    protected $fillable = [
        'checklist_id',
        'version_id',
        'request_id',
        'doc_type_id',
        'doc_distribution_date_actual',
        'doc_distribution_time_actual',
        'doc_distribution_date_file',
        'doc_distribution_time_file',
        'time_spent',
        'remarks',
        'scanned_distribution',
        'created_by',
    ];

    protected $casts = [
        'doc_distribution_date_actual' => 'date',
        'doc_distribution_date_file' => 'date',
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
        return $this->hasMany(DistributionOffice::class, 'distribution_id');
    }
}
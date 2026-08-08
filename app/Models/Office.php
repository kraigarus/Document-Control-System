<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Office extends Model
{
    protected $table = 'offices';
    public $timestamps = false;

    protected $fillable = [
        'office_name',
        'status',
    ];

    public function documentRequestForms(): BelongsToMany
    {
        return $this->belongsToMany(
            DocumentRequestForm::class,
            'dcs_drf_offices',
            'office_id',
            'document_request_form_id'
        );
    }

    public function documentChangeNotices()
    {
        return $this->hasMany(DocumentChangeNotice::class, 'office_id');
    }

    public function masterlistRegistrations(): BelongsToMany
    {
        return $this->belongsToMany(
            MasterlistRegistration::class,
            'dcs_masterlist_source_offices',
            'office_id',
            'masterlist_id'
        );
    }

    public function college(): HasOne
    {
        return $this->hasOne(College::class, 'office_id');
    }
}
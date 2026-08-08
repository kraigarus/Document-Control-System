<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VersionType extends Model
{
    protected $table = 'version_type';
    public $timestamps = false;

    protected $fillable = [
        'version_name',
    ];

    public function checklists()
    {
        return $this->belongsToMany(ChecklistType::class, 'checklist_version', 'version_id', 'checklist_id');
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class, 'version_id');
    }
}
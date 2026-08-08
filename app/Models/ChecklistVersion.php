<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistVersion extends Model
{
    protected $table = 'checklist_version';
    public $timestamps = false;

    protected $fillable = [
        'checklist_id',
        'version_id',
    ];

    public function checklist()
    {
        return $this->belongsTo(ChecklistType::class, 'checklist_id');
    }

    public function version()
    {
        return $this->belongsTo(VersionType::class, 'version_id');
    }
}

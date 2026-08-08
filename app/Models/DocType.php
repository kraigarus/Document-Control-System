<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocType extends Model
{
    protected $table = 'doc_types';
    public $timestamps = false;

    protected $fillable = [
        'parent_id',
        'doc_type_name',
    ];

    public function parent()
    {
        return $this->belongsTo(DocType::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(DocType::class, 'parent_id');
    }

    public function documentRequests()
    {
        return $this->hasMany(DocumentRequest::class, 'doc_type_id');
    }

    public function subTypes()
    {
        return $this->hasMany(DocType::class, 'parent_id');
    }

    public function parentType()
    {
        return $this->belongsTo(DocType::class, 'parent_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalBody extends Model
{
    protected $table = 'approval_body';
    protected $primaryKey = 'approval_body_id';
    public $timestamps = false;

    protected $fillable = [
        'approval_name',
    ];

    public function approvalRecords()
    {
        return $this->hasMany(ApprovalRecord::class, 'approval_body_id');
    }
}
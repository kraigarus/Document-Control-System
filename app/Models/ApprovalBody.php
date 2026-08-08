<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalBody extends Model
{
    protected $table = 'dcs_approval_body';
    public $timestamps = false;

    protected $fillable = [
        'approval_name',
    ];

    public function approvalRecords()
    {
        return $this->hasMany(ApprovalRecord::class, 'approval_body_id');
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentStamp extends Model
{
    protected $fillable = [
        'document_request_id',
        'file_key',
        'file_path',
        'stamp_type',
        'position',
        'all_pages',
        'certified_by',
        'designation',
        'stamped_by',
        'stamped_at',
    ];

    protected $casts = [
        'all_pages'  => 'boolean',
        'stamped_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'document_request_id', 'request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'stamped_by');
    }
}
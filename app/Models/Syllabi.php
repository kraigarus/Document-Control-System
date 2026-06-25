<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Syllabi extends Model
{
    public $timestamps = false;
    protected $table = 'syllabi';
    protected $primaryKey = 'syllabi_id';

    protected $fillable = [
        'request_id',
        'course_name',
        'syllabi_availability',
        'no_pages',
        'drf_availability',
        'drf_no',
        'drf_date',
        'drf_received_date',
        'scanned_drf',
    ];

    protected $casts = [
        'drf_date' => 'date',
        'drf_received_date' => 'date',
    ];

    public function request()
    {
        return $this->belongsTo(DocumentRequest::class, 'request_id');
    }
}
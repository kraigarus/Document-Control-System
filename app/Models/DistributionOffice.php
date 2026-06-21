<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributionOffice extends Model
{
    protected $table = 'distribution_offices';
    public $timestamps = false;

    protected $fillable = [
        'distribution_id',
        'office_id',
        'copies',
    ];

    public function distribution()
    {
        return $this->belongsTo(DocumentDistribution::class, 'distribution_id');
    }

    public function office()
    {
        return $this->belongsTo(Office::class, 'office_id');
    }
}
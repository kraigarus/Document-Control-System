<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Office extends Model
{
    protected $table = 'offices';
    protected $primaryKey = 'office_id';

    protected $fillable = [
        'office_name',
        'status',
    ];

    public function documentRequestForms()
    {
        return $this->hasMany(DocumentRequestForm::class, 'office_id');
    }

    public function documentChangeNotices()
    {
        return $this->hasMany(DocumentChangeNotice::class, 'office_id');
    }

    public function masterlistRegistrations()
    {
        return $this->hasMany(MasterlistRegistration::class, 'office_id');
    }
}

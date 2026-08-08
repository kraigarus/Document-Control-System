<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'accounts';
    public $timestamps = false;

    protected $fillable = [
        'email',
        'password',
        'account_role',
        'account_active',
    ];

    protected $hidden = [
        'password',
    ];
}
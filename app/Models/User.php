<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
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

    protected function casts(): array
    {
        return [
            'account_active' => 'boolean',
            'date_created' => 'datetime',
            'date_modified' => 'datetime',
        ];
    }
}
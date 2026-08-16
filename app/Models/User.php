<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;
    protected $table = 'account';

    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'account_status',
        'account_role',
        'account_active',
        'date_created',
        'date_updated',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'account_active' => 'boolean',
            'account_status' => 'integer',
            'account_role' => 'integer',
            'date_created' => 'datetime',
            'date_updated' => 'datetime',
        ];
    }

    public function getNameAttribute(): string
    {
        $details = $this->details;
        if ($details) {
            $full = trim(($details->first_name ?? '') . ' ' . ($details->last_name ?? ''));
            if ($full !== '') {
                return $full;
            }
        }

        return (string) ($this->username ?? '');
    }

    public function details(): HasOne
    {
        return $this->hasOne(AccountDetail::class, 'account_id');
    }

    public function permissions(): HasOneThrough
    {
        return $this->hasOneThrough(
            role_permission::class,
            role_list::class,
            'id',
            'key_id',
            'account_role',
            'modifier_key'
        );
    }
}

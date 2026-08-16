<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDetail extends Model
{
    protected $table = 'account_details';

    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'account_id',
        'first_name',
        'last_name',
        'middle_name',
        'office_id',
        'email',
        'contact_number',
        'is_currently_online',
        'last_online_time',
    ];

    protected function casts(): array
    {
        return [
            'is_currently_online' => 'boolean',
            'last_online_time' => 'datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(office::class, 'office_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }
}

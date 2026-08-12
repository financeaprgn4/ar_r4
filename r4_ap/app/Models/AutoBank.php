<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoBank extends Model
{
    protected $table = 'auto_bank';

    protected $fillable = [
        'cabang',
        'bank',
        'no_rek',
        'balance'
    ];

    public $timestamps = false;
}

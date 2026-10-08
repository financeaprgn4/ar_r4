<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    protected $table = 'bank';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'cabang',
        'bank',
        'jns_bank',
        'no_rek',
        'akun',
        'ce',
        'site',
        'bank_name',
        'acc_num',
        'receipt_method',
    ];
}
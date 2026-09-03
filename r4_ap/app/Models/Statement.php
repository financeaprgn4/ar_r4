<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Statement extends Model
{
    protected $table = 'rk';

    protected $fillable = [
        'cabang', 'nama_bank', 'jns_rek', 'no_rek', 'file', 'periode'
    ];

    public $timestamps = false;
}

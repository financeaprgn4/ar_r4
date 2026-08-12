<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FtpRoot extends Model
{
    protected $table = 'ftp_root';

    public $timestamps = false;

    protected $fillable = [
        'cabang',
        'root'
    ];
}
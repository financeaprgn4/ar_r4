<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    protected $table = 'users';

    protected $fillable = [
        'nama', 'username', 'pass', 'level_user', 'ip', 'foto', 'cabang', 'password'
    ];

    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mail extends Model
{
    use HasFactory;
    public $timestamps = false;
    
    protected $table = 'mail';

    
    protected $fillable = [
        'cabang',
        'mail',
        'untuk',
        'sub',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Periode extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $table = 'periode';

    protected $fillable = [
        'Cabang',
        'kategori',
        'periode',
        'start_date',
        'end_date',
        'status',
    ];
}

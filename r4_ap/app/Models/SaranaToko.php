<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaranaToko extends Model
{
    protected $table = 'sarana_toko';

    protected $fillable = [
        'rab',
        'kategori',
        'kode',
        'uraian',
        'satuan',
        'qty',
        'harga_satuan',
        'dpp',
        'ppn',
        'total',
        'flag_realisasi',
    ];
    public $timestamps = false;
}

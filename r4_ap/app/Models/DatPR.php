<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaranaToko extends Model
{
    protected $table = 'dat_pr';

    protected $fillable = [
        'rab',
        'site',
        'seri',
        'keterangan',
        'surkas',
        'inv_num',
        'tgl_perolehan',
        'harga',
        'flag_realisasi',
    ];
    public $timestamps = false;
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promesa extends Model
{

    use SoftDeletes;
    
    protected $fillable = [
        "prestamo_id",
        "to_pay"
    ];
}

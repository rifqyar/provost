<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Polda extends Model
{
    use HasFactory;
    protected $table = 'poldas';

    protected $guarded = [];
}
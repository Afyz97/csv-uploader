<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'unique_key',
        'product_title',
        'product_description',
        'style_no',
        'sanmar_mainframe_color',
        'size',
        'color_name',
        'piece_price',
    ];
}

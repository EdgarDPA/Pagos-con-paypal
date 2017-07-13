<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
   protected $fillable = [
        'id_compra_paypal','inten', 'state', 'cart','payment_method','total'
    ];
}

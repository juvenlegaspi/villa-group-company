<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name',
        'business_type',
        'tin',
        'address',
        'products',
        'tax_type',
        'lead_time',
        'credit_term',
        'limit_advances',
        'contact_person',
        'telephone',
        'mobile',
        'email',
        'status',
        'added_by',
    ];
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'added_by');
    }
}

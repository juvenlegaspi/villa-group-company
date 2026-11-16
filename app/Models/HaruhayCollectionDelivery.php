<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayCollectionDelivery extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'started_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function collection()
    {
        return $this->belongsTo(HaruhayCollection::class, 'collection_id');
    }
}

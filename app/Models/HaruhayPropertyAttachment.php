<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HaruhayPropertyAttachment extends Model
{
    use \App\Models\Concerns\ScopesHaruhayProperties;

    protected $guarded = ['id'];

    public function property() { return $this->belongsTo(HaruhayProperty::class, 'property_id'); }
}

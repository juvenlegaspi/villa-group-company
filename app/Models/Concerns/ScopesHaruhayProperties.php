<?php

namespace App\Models\Concerns;

use App\Services\HaruhayPropertyAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ScopesHaruhayProperties
{
    public static function bootScopesHaruhayProperties(): void
    {
        static::addGlobalScope('haruhay-property-access', fn (Builder $query) => app(HaruhayPropertyAccess::class)->scope($query));
        static::saving(fn (Model $record) => app(HaruhayPropertyAccess::class)->authorizeSave($record));
    }
}

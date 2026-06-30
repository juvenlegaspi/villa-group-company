<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HyveRoom extends Model
{
    public const VISIBILITY_ACTIVE = 0;

    public const ROOM_STATUS_AVAILABLE = 0;
    public const ROOM_STATUS_OCCUPIED = 1;
    public const ROOM_STATUS_BOOKED = 2;

    protected $table = 'hyve_rooms';

    protected $fillable = [
        'room_name',
        'description',
        'room_status',
        'status'
    ];

    protected $casts = [
        'room_status' => 'integer',
        'status' => 'integer',
    ];

    public function bookingDetails(): HasMany
    {
        return $this->hasMany(BookingDetail::class, 'hyve_room_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::VISIBILITY_ACTIVE);
    }

    public static function statusMap(): array
    {
        return [
            self::ROOM_STATUS_AVAILABLE => [
                'label' => 'Available',
                'class' => 'available',
            ],
            self::ROOM_STATUS_OCCUPIED => [
                'label' => 'Occupied',
                'class' => 'occupied',
            ],
            self::ROOM_STATUS_BOOKED => [
                'label' => 'Booked',
                'class' => 'reserved',
            ],
        ];
    }

    public static function legend(): array
    {
        return array_values(self::statusMap());
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->statusMeta()['label'];
    }

    public function getStatusClassAttribute(): string
    {
        return $this->statusMeta()['class'];
    }

    private function statusMeta(): array
    {
        return self::statusMap()[$this->room_status] ?? self::statusMap()[self::ROOM_STATUS_AVAILABLE];
    }
}

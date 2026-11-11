<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory, HasPublicUuid;

    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'creator_id',
        'session_type_id',
        'guest_name',
        'guest_email',
        'guest_timezone',
        'notes',
        'start_at_utc',
        'end_at_utc',
        'status',
    ];

    protected $hidden = [
        'id',
        'creator_id',
        'session_type_id',
    ];

    protected function casts(): array
    {
        return [
            'start_at_utc' => 'immutable_datetime',
            'end_at_utc' => 'immutable_datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function sessionType(): BelongsTo
    {
        return $this->belongsTo(SessionType::class);
    }
}

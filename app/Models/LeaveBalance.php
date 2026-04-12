<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'year',
        'total_quota',
        'used',
        'pending',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'total_quota' => 'integer',
            'used' => 'integer',
            'pending' => 'integer',
        ];
    }

    public function remaining(): int
    {
        return $this->total_quota - $this->used - $this->pending;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

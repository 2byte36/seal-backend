<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'year' => $this->year,
            'total_quota' => $this->total_quota,
            'used' => $this->used,
            'pending' => $this->pending,
            'remaining' => $this->remaining(),
        ];
    }
}

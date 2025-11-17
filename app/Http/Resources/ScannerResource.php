<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'uid' => $this->uid,
            'priority' => $this->priority,
            'active' => $this->active,
            'synced_at' => $this->synced_at,
        ];
    }
}

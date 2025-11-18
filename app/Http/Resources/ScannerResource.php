<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'uid' => $this->uid,
            'priority' => $this->priority,
            'active' => $this->active,
            'synced' => $this->synced_at,
        ];

        if (isset($this->employees_count)) {
            $data['employees'] = $this->employees_count;
        }

        return $data;
    }
}

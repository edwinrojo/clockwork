<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'prefix_name' => $this->prefix_name,
            'suffix_name' => $this->suffix_name,
            'qualifier_name' => $this->qualifier_name,
            'status' => $this->status,
            'substatus' => $this->substatus,
        ];

        if ($this->relationLoaded('pivot') && isset($this->pivot->uid)) {
            $data['uid'] = $this->pivot->uid;
        } elseif (isset($this->pivot_uid)) {
            $data['uid'] = $this->pivot_uid;
        }

        if ($this->relationLoaded('scanners')) {
            $data['scanners'] = $this->scanners->map(function ($scanner) {
                return [
                    'id' => $scanner->id,
                    'name' => $scanner->name,
                    'uid' => $scanner->pivot->uid ?? null,
                ];
            });
        }

        return $data;
    }
}

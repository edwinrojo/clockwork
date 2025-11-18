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
            'tag' => $this->uid,
            'email' => $this->email,
            'birthdate' => $this->birthdate,
            'sex' => $this->sex,
        ];

        if ($this->relationLoaded('pivot') && isset($this->pivot->uid)) {
            $data['uid'] = $this->pivot->uid;
        }

        if ($this->relationLoaded('scanners')) {
            $data['scanners'] = ScannerResource::collection($this->scanners)
                ->map(function ($resource) use ($request) {
                    $array = $resource->toArray($request);

                    $array['uid'] = $resource->resource->pivot->uid ?? null;
                    $array['active'] = $resource->resource->pivot->active ?? null;

                    return $array;
                });
        }

        if ($this->relationLoaded('offices')) {
            $data['offices'] = OfficeResource::collection($this->offices)
                ->map(function ($resource) use ($request) {
                    $array = $resource->toArray($request);

                    $array['current'] = $resource->resource->pivot->current ?? null;
                    $array['active'] = $resource->resource->pivot->active ?? null;

                    return $array;
                });
        }

        if ($this->relationLoaded('groups')) {
            $data['groups'] = GroupResource::collection($this->groups)
                ->map(function ($resource) use ($request) {
                    $array = $resource->toArray($request);

                    $array['active'] = $resource->resource->pivot->active ?? null;

                    return $array;
                });
        }

        return $data;
    }
}

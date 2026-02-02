<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @ai-context API Resource for serializing Setting model.
 *
 * @property \App\Models\Setting $resource
 */
class SettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->key,
            'value' => $this->resource->typed_value,
            'type' => $this->resource->type,
            'group' => $this->resource->group,
            'description' => $this->resource->description,
            'is_public' => $this->resource->is_public,
        ];
    }
}

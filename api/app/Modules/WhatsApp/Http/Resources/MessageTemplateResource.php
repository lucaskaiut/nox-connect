<?php

namespace App\Modules\WhatsApp\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'] ?? null,
            'name' => $this->resource['name'] ?? null,
            'language' => $this->resource['language'] ?? null,
            'category' => $this->resource['category'] ?? null,
            'sub_category' => $this->resource['sub_category'] ?? null,
            'status' => $this->resource['status'] ?? null,
            'components' => $this->resource['components'] ?? null,
            'parameter_format' => $this->resource['parameter_format'] ?? null,
            'display_format' => $this->resource['display_format'] ?? null,
            'quality_score' => $this->resource['quality_score'] ?? null,
            'health_status' => $this->resource['health_status'] ?? null,
            'rejected_reason' => $this->resource['rejected_reason'] ?? null,
            'source' => $this->resource['source'] ?? null,
            'message_send_ttl_seconds' => $this->resource['message_send_ttl_seconds'] ?? null,
            'cta_url_link_tracking_opted_out' => $this->resource['cta_url_link_tracking_opted_out'] ?? null,
            'allow_category_change' => $this->resource['allow_category_change'] ?? null,
            'is_primary_device_delivery_only' => $this->resource['is_primary_device_delivery_only'] ?? null,
            'is_sms_fallback_enabled' => $this->resource['is_sms_fallback_enabled'] ?? null,
            'library_template_name' => $this->resource['library_template_name'] ?? null,
            'previous_category' => $this->resource['previous_category'] ?? null,
            'correct_category' => $this->resource['correct_category'] ?? null,
            'last_updated_time' => isset($this->resource['last_updated_time'])
                ? Carbon::createFromTimestamp($this->resource['last_updated_time'])->toIso8601String()
                : null,
            'created_at' => isset($this->resource['created_at'])
                ? Carbon::parse($this->resource['created_at'])->toIso8601String()
                : null,
        ];
    }
}

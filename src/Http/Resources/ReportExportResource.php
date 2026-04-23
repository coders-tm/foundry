<?php

namespace Foundry\Http\Resources;

use Foundry\Services\Reports\ReportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportExportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => ReportService::getLabel($this->type),
            'category' => ReportService::getCategory($this->type),
            'status' => $this->status,
            'file_name' => $this->file_name,
            'file_path' => $this->file_path,
            'file_size' => $this->file_size,
            'total_records' => $this->total_records,
            'error_message' => $this->error_message,
            'filters' => $this->filters,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'admin' => $this->whenLoaded('admin', function () {
                return [
                    'id' => $this->admin->id,
                    'name' => $this->admin->name,
                    'email' => $this->admin->email,
                ];
            }),
        ];
    }
}

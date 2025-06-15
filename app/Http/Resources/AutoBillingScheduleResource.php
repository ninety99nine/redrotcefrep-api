<?php

namespace App\Http\Resources;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Helpers\ResourceLink;

class AutoBillingScheduleResource extends BaseResource
{
    public function toArray($request)
    {
        return $this->transformedStructure();
    }

    public function setLinks()
    {
        $autoBillingSchedule = $this->resource;

        $this->resourceLinks = [
            new ResourceLink('show.auto.billing.schedule', route('show.auto.billing.schedule', ['autoBillingScheduleId' => $autoBillingSchedule->id])),
            new ResourceLink('update.auto.billing.schedule', route('update.auto.billing.schedule', ['autoBillingScheduleId' => $autoBillingSchedule->id])),
            new ResourceLink('delete.auto.billing.schedule', route('delete.auto.billing.schedule', ['autoBillingScheduleId' => $autoBillingSchedule->id])),
        ];
    }
}

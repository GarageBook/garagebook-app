<?php

namespace App\Services\Lifecycle\Rules;

use App\Services\Lifecycle\Rules\Rules\FirstMaintenanceRule;
use App\Services\Lifecycle\Rules\Rules\InactiveMaintenanceRule;
use App\Services\Lifecycle\Rules\Rules\NoVehicleRule;
use App\Services\Lifecycle\Rules\Rules\UploadDocumentRule;
use App\Services\Lifecycle\Rules\Rules\VehiclePhotoReminderRule;
use Illuminate\Support\Collection;

class LifecycleRuleRegistry
{
    /**
     * @return Collection<int, LifecycleRule>
     */
    public function rules(): Collection
    {
        return collect([
            app(NoVehicleRule::class),
            app(FirstMaintenanceRule::class),
            app(UploadDocumentRule::class),
            app(VehiclePhotoReminderRule::class),
            app(InactiveMaintenanceRule::class),
        ]);
    }
}

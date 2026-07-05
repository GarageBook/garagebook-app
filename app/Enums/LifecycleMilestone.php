<?php

namespace App\Enums;

enum LifecycleMilestone: string
{
    case FIRST_VEHICLE = 'first_vehicle';
    case FIRST_MAINTENANCE = 'first_maintenance';
    case FIRST_PHOTO = 'first_photo';
    case FIRST_DOCUMENT = 'first_document';
    case PUBLIC_GARAGE = 'public_garage';
    case FIVE_MAINTENANCE_LOGS = 'five_maintenance_logs';
    case TEN_MAINTENANCE_LOGS = 'ten_maintenance_logs';
    case FIRST_SERVICE_COST = 'first_service_cost';
    case FIRST_FUEL_LOG = 'first_fuel_log';
    case COMPLETE_PROFILE = 'complete_profile';
}

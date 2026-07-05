<?php

namespace App\Enums;

enum LifecycleState: string
{
    case REGISTERED = 'registered';
    case VEHICLE_ADDED = 'vehicle_added';
    case FIRST_MAINTENANCE_LOGGED = 'first_maintenance_logged';
    case VEHICLE_PROFILE_COMPLETE = 'vehicle_profile_complete';
    case DOCUMENTS_ADDED = 'documents_added';
    case PUBLIC_GARAGE_ENABLED = 'public_garage_enabled';
    case HEALTHY_GARAGE = 'healthy_garage';
}

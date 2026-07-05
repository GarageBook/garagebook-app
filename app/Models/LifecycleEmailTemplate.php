<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LifecycleEmailTemplate extends Model
{
    public const NO_VEHICLE_DAY2 = 'no_vehicle_day2';

    public const NO_VEHICLE_ADDED = 'no_vehicle_added';

    public const NO_MAINTENANCE_LOG_DAY_3 = 'no_maintenance_log_day_3';

    public const NO_MAINTENANCE_LOG_DAY_14 = 'no_maintenance_log_day_14';

    public const NO_MAINTENANCE_LOG_DAY_30 = 'no_maintenance_log_day_30';

    public const AFTER_FIRST_MAINTENANCE_LOG = 'after_first_maintenance_log';

    public const INACTIVE_USER_RETURN = 'inactive_user_return';

    public const EMAIL_KEYS = [
        self::NO_VEHICLE_DAY2,
        self::NO_VEHICLE_ADDED,
        self::NO_MAINTENANCE_LOG_DAY_3,
        self::NO_MAINTENANCE_LOG_DAY_14,
        self::NO_MAINTENANCE_LOG_DAY_30,
        self::AFTER_FIRST_MAINTENANCE_LOG,
        self::INACTIVE_USER_RETURN,
    ];

    protected $fillable = [
        'email_key',
        'name',
        'subject',
        'body',
        'cta_text',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

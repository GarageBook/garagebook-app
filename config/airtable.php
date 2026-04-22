<?php

return [
    'personal_access_token' => env('AIRTABLE_PERSONAL_ACCESS_TOKEN'),
    'base_id' => env('AIRTABLE_BASE_ID', 'appkw5g8mLdGZpIkt'),
    'users_table' => env('AIRTABLE_USERS_TABLE', 'Users'),
    'users_name_field' => env('AIRTABLE_USERS_NAME_FIELD', 'Name'),
    'users_email_field' => env('AIRTABLE_USERS_EMAIL_FIELD', 'E-mail'),
    'vehicles_table' => env('AIRTABLE_VEHICLES_TABLE', 'Vehicles'),
    'maintenance_table' => env('AIRTABLE_MAINTENANCE_TABLE', 'Maintenance'),
    'media_table' => env('AIRTABLE_MEDIA_TABLE', 'Media'),
];

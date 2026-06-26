<?php

namespace App\Support\Growth;

use App\Models\AnalyticsDailySummary;
use App\Models\AnalyticsTopPage;
use App\Models\GrowthCampaign;
use App\Models\MaintenanceLog;
use App\Models\SearchConsolePage;
use App\Models\SearchConsoleQuery;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\AnalyticsDataWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GrowthDashboardData
{
    private const PARTNERS = [
        'geratel',
        'motorfreaks',
        'nieuwsmotor',
        'motoplus',
        'knmv',
        'reddit',
        'motor-forum',
        'tweakers',
        'garagebook.nl',
    ];

    private array $tablePresence = [];

    private array $columnPresence = [];

    public function kpiOverview(): array
    {
        $today = Carbon::today();
        $sevenDayStart = $today->copy()->subDays(6);
        $thirtyDayStart = $today->copy()->subDays(29);

        $visitorCounts = $this->visitorCounts($today, $sevenDayStart, $thirtyDayStart);

        $registrationsToday = User::query()
            ->where('created_at', '>=', $today->copy()->startOfDay())
            ->count();

        $registrationsSevenDays = User::query()
            ->where('created_at', '>=', $sevenDayStart->copy()->startOfDay())
            ->count();

        $registrationsThirtyDays = User::query()
            ->where('created_at', '>=', $thirtyDayStart->copy()->startOfDay())
            ->count();

        $latestRegistration = User::query()
            ->select(['id', 'name', 'created_at'])
            ->latest('created_at')
            ->first();

        $conversionRateThirtyDays = null;
        if (filled($visitorCounts['thirty_days']) && $visitorCounts['thirty_days'] >= 10) {
            $conversionRateThirtyDays = round(($registrationsThirtyDays / $visitorCounts['thirty_days']) * 100, 2);
            // Cap at 100% if data is inconsistent
            if ($conversionRateThirtyDays > 100) {
                $conversionRateThirtyDays = 100;
            }
        }

        $isAnalyticsIncomplete = $registrationsThirtyDays > 0 && ($visitorCounts['thirty_days'] ?? 0) < $registrationsThirtyDays;

        return [
            'is_analytics_incomplete' => $isAnalyticsIncomplete,
            'analytics_window' => $visitorCounts['window'] ?? AnalyticsDataWindow::forTable('analytics_daily_summaries'),
            'cards' => [
                [
                    'label' => 'Bezoekers vandaag',
                    'value' => $visitorCounts['today'],
                    'is_available' => $visitorCounts['today'] !== null,
                    'meta' => collect([$visitorCounts['window']['label'] ?? null, $visitorCounts['window']['warning'] ?? null])->filter()->implode(' · ') ?: null,
                ],
                [
                    'label' => 'Bezoekers laatste 7 dagen',
                    'value' => $visitorCounts['seven_days'],
                    'is_available' => $visitorCounts['seven_days'] !== null,
                    'meta' => collect([$visitorCounts['window']['label'] ?? null, $visitorCounts['window']['warning'] ?? null])->filter()->implode(' · ') ?: null,
                ],
                [
                    'label' => 'Bezoekers laatste 30 dagen',
                    'value' => $visitorCounts['thirty_days'],
                    'is_available' => $visitorCounts['thirty_days'] !== null,
                    'meta' => collect([$visitorCounts['window']['label'] ?? null, $visitorCounts['window']['warning'] ?? null])->filter()->implode(' · ') ?: null,
                ],
                [
                    'label' => 'Registraties vandaag',
                    'value' => $registrationsToday,
                    'is_available' => true,
                ],
                [
                    'label' => 'Registraties laatste 7 dagen',
                    'value' => $registrationsSevenDays,
                    'is_available' => true,
                ],
                [
                    'label' => 'Registraties laatste 30 dagen',
                    'value' => $registrationsThirtyDays,
                    'is_available' => true,
                ],
                [
                    'label' => 'Conversieratio 30 dagen',
                    'value' => $conversionRateThirtyDays,
                    'is_available' => $conversionRateThirtyDays !== null,
                    'suffix' => '%',
                ],
                [
                    'label' => 'Laatste registratie',
                    'value' => $latestRegistration?->name ?: 'Nog geen registraties',
                    'is_available' => $latestRegistration !== null,
                    'meta' => $latestRegistration?->created_at?->format('d-m-Y H:i'),
                ],
            ],
        ];
    }

    public function acquisitionPerformance(): array
    {
        $rows = $this->attributedRegistrationRecords();

        if ($rows->isEmpty()) {
            return [
                'disclaimer' => 'Gebaseerd op beschikbare attribution data',
                'rows' => [],
            ];
        }

        $grouped = [];

        foreach ($rows as $row) {
            $source = $this->sourceLabel($row);
            $medium = $this->mediumLabel($row);
            $campaign = $this->campaignLabel($row);
            $key = implode('|', [$source, $medium, $campaign]);

            if (! array_key_exists($key, $grouped)) {
                $grouped[$key] = [
                    'source' => $source,
                    'medium' => $medium,
                    'campaign' => $campaign,
                    'visits' => null,
                    'registrations' => 0,
                    'conversion_rate' => null,
                    'latest_activity_at' => null,
                    'note' => 'Gebaseerd op beschikbare attribution data',
                ];
            }

            $grouped[$key]['registrations']++;

            if ($grouped[$key]['latest_activity_at'] === null || $row['created_at']->gt($grouped[$key]['latest_activity_at'])) {
                $grouped[$key]['latest_activity_at'] = $row['created_at'];
            }
        }

        $rows = collect($grouped)
            ->sortByDesc(fn (array $row) => [$row['registrations'], $row['latest_activity_at']?->timestamp ?? 0])
            ->values()
            ->map(fn (array $row) => [
                ...$row,
                'latest_activity' => $row['latest_activity_at']?->format('d-m-Y H:i') ?? '—',
            ])
            ->all();

        return [
            'disclaimer' => 'Gebaseerd op beschikbare attribution data',
            'rows' => $rows,
        ];
    }

    public function partnerPerformance(): array
    {
        $rows = $this->attributedRegistrationRecords();

        $partners = collect(self::PARTNERS)->mapWithKeys(fn (string $partner) => [
            $partner => [
                'partner' => $partner,
                'visits' => null,
                'registrations' => 0,
                'conversion_rate' => null,
                'latest_registration_at' => null,
                'status' => 'Gebaseerd op beschikbare attribution data',
            ],
        ])->all();

        foreach ($rows as $row) {
            foreach (array_keys($partners) as $partner) {
                if (! $this->matchesPartner($partner, $row)) {
                    continue;
                }

                $partners[$partner]['registrations']++;

                if ($partners[$partner]['latest_registration_at'] === null || $row['created_at']->gt($partners[$partner]['latest_registration_at'])) {
                    $partners[$partner]['latest_registration_at'] = $row['created_at'];
                }
            }
        }

        $result = collect($partners)
            ->map(fn (array $row) => [
                ...$row,
                'latest_registration' => $row['latest_registration_at']?->format('d-m-Y H:i') ?? '—',
            ])
            ->sortByDesc(fn (array $row) => [$row['registrations'], $row['latest_registration_at']?->timestamp ?? 0])
            ->values()
            ->all();

        return [
            'rows' => $result,
        ];
    }

    public function sourceActivation(): array
    {
        $rows = $this->registrationAttributionRecords();
        $vehicleUserIds = $this->userIdsWithVehicles();
        $maintenanceUserIds = $this->userIdsWithMaintenanceLogs();
        $grouped = [];

        foreach ($rows as $row) {
            $source = $this->activationSourceLabel($row);

            if (! array_key_exists($source, $grouped)) {
                $grouped[$source] = [
                    'source' => $source,
                    'registrations' => 0,
                    'users_with_vehicle' => 0,
                    'users_with_maintenance_log' => 0,
                    'activation_percentage' => 0.0,
                    'maintenance_activation_percentage' => 0.0,
                    'latest_registration_at' => null,
                    'source_values' => [],
                    'campaign_values' => [],
                    'partner_values' => [],
                ];
            }

            $grouped[$source]['registrations']++;

            if ($vehicleUserIds->has($row['id'])) {
                $grouped[$source]['users_with_vehicle']++;
            }

            if ($maintenanceUserIds->has($row['id'])) {
                $grouped[$source]['users_with_maintenance_log']++;
            }

            if ($grouped[$source]['latest_registration_at'] === null || $row['created_at']->gt($grouped[$source]['latest_registration_at'])) {
                $grouped[$source]['latest_registration_at'] = $row['created_at'];
            }

            foreach (['source' => 'source_values', 'campaign_slug' => 'campaign_values', 'partner_slug' => 'partner_values'] as $field => $target) {
                if (filled($row[$field])) {
                    $grouped[$source][$target][(string) $row[$field]] = true;
                }
            }
        }

        $result = collect($grouped)
            ->map(function (array $row): array {
                $registrations = max(1, $row['registrations']);

                return [
                    'source' => $row['source'],
                    'registrations' => $row['registrations'],
                    'users_with_vehicle' => $row['users_with_vehicle'],
                    'users_with_maintenance_log' => $row['users_with_maintenance_log'],
                    'activation_percentage' => round(($row['users_with_vehicle'] / $registrations) * 100, 1),
                    'maintenance_activation_percentage' => round(($row['users_with_maintenance_log'] / $registrations) * 100, 1),
                    'latest_registration' => $row['latest_registration_at']?->format('d-m-Y H:i') ?? '—',
                    'sources' => $this->compactValues($row['source_values']),
                    'campaigns' => $this->compactValues($row['campaign_values']),
                    'partners' => $this->compactValues($row['partner_values']),
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['registrations'], $row['activation_percentage'], $row['source']])
            ->values()
            ->all();

        return [
            'disclaimer' => 'Gebaseerd op lokale registratie-, voertuig- en onderhoudsdata.',
            'rows' => $result,
            'totals' => [
                'registrations' => $rows->count(),
                'users_with_vehicle' => $vehicleUserIds->count(),
                'users_with_maintenance_log' => $maintenanceUserIds->count(),
            ],
        ];
    }

    public function campaignPerformance(): array
    {
        if (! $this->hasTable('growth_campaigns')) {
            return [
                'disclaimer' => 'Gebaseerd op bestaande growth-attributie.',
                'rows' => [],
            ];
        }

        $campaigns = GrowthCampaign::query()
            ->select(['id', 'name', 'slug', 'status'])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        if ($campaigns->isEmpty()) {
            return [
                'disclaimer' => 'Gebaseerd op bestaande growth-attributie.',
                'rows' => [],
            ];
        }

        $rows = $this->registrationAttributionRecords();
        $vehicleUserIds = $this->userIdsWithVehicles();
        $maintenanceUserIds = $this->userIdsWithMaintenanceLogs();

        $result = $campaigns
            ->map(function (GrowthCampaign $campaign) use ($rows, $vehicleUserIds, $maintenanceUserIds): array {
                $matchingRows = $rows->filter(fn (array $row): bool => $this->matchesCampaign($campaign->slug, $row));
                $registrations = $matchingRows->count();
                $usersWithVehicle = $matchingRows->filter(fn (array $row): bool => $vehicleUserIds->has($row['id']))->count();
                $usersWithMaintenanceLog = $matchingRows->filter(fn (array $row): bool => $maintenanceUserIds->has($row['id']))->count();
                $latestRegistrationAt = $matchingRows->max('created_at');

                return [
                    'name' => $campaign->name,
                    'slug' => $campaign->slug,
                    'status' => $campaign->status,
                    'registrations' => $registrations,
                    'users_with_vehicle' => $usersWithVehicle,
                    'users_with_maintenance_log' => $usersWithMaintenanceLog,
                    'activation_percentage' => $registrations > 0 ? round(($usersWithVehicle / $registrations) * 100, 1) : 0.0,
                    'maintenance_activation_percentage' => $registrations > 0 ? round(($usersWithMaintenanceLog / $registrations) * 100, 1) : 0.0,
                    'latest_registration' => $latestRegistrationAt instanceof Carbon ? $latestRegistrationAt->format('d-m-Y H:i') : '—',
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['registrations'], $row['activation_percentage'], $row['name']])
            ->values()
            ->all();

        return [
            'disclaimer' => 'Gebaseerd op growth_campaigns.slug met user_attributions.campaign_slug en fallback utm_campaign.',
            'rows' => $result,
        ];
    }

    public function seoIntelligence(): array
    {
        return [
            'has_queries' => $this->hasTable('search_console_queries'),
            'has_pages' => $this->hasTable('search_console_pages'),
            'query_window' => AnalyticsDataWindow::forTable('search_console_queries'),
            'page_window' => AnalyticsDataWindow::forTable('search_console_pages'),
            'top_queries_by_clicks' => $this->searchConsoleQueryRows('clicks'),
            'top_queries_by_impressions' => $this->searchConsoleQueryRows('impressions'),
            'high_impression_low_ctr_queries' => $this->searchConsoleQueryRows('impressions', fn ($query) => $query
                ->havingRaw('SUM(impressions) >= 100')
                ->havingRaw('AVG(ctr) < 0.02')),
            'position_opportunity_queries' => $this->searchConsoleQueryRows('position', fn ($query) => $query
                ->havingRaw('AVG(position) between 4 and 15')),
            'top_pages' => $this->searchConsolePageRows('clicks'),
            'high_impression_low_ctr_pages' => $this->searchConsolePageRows('impressions', fn ($query) => $query
                ->havingRaw('SUM(impressions) >= 100')
                ->havingRaw('AVG(ctr) < 0.02')),
        ];
    }

    public function landingPageConversion(): array
    {
        $rows = $this->registrationAttributionRecords()
            ->filter(fn (array $row) => filled($row['landing_page']));

        if ($rows->isEmpty()) {
            return [
                'disclaimer' => 'Gebaseerd op beschikbare attribution data',
                'analytics_window' => AnalyticsDataWindow::forTable('analytics_top_pages'),
                'rows' => [],
            ];
        }

        $visitsByPage = $this->analyticsTopPageUsersByPath();
        $grouped = [];

        foreach ($rows as $row) {
            $landingPage = $row['landing_page'];

            if (! array_key_exists($landingPage, $grouped)) {
                $grouped[$landingPage] = [
                    'landing_page' => $landingPage,
                    'visits' => $visitsByPage[$landingPage] ?? null,
                    'registrations' => 0,
                    'latest_registration_at' => null,
                    'top_source_counts' => [],
                ];
            }

            $grouped[$landingPage]['registrations']++;
            $source = $this->sourceLabel($row);
            $grouped[$landingPage]['top_source_counts'][$source] = ($grouped[$landingPage]['top_source_counts'][$source] ?? 0) + 1;

            if ($grouped[$landingPage]['latest_registration_at'] === null || $row['created_at']->gt($grouped[$landingPage]['latest_registration_at'])) {
                $grouped[$landingPage]['latest_registration_at'] = $row['created_at'];
            }
        }

        $result = collect($grouped)
            ->map(function (array $row) {
                arsort($row['top_source_counts']);
                $topSource = array_key_first($row['top_source_counts']);
                $conversionRate = filled($row['visits']) && $row['visits'] > 0
                    ? round(($row['registrations'] / $row['visits']) * 100, 2)
                    : null;

                return [
                    'landing_page' => $row['landing_page'],
                    'visits' => $row['visits'],
                    'registrations' => $row['registrations'],
                    'conversion_rate' => $conversionRate,
                    'top_source' => $topSource ?: '—',
                    'latest_registration' => $row['latest_registration_at']?->format('d-m-Y H:i') ?? '—',
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['registrations'], $row['visits'] ?? 0])
            ->values()
            ->all();

        return [
            'disclaimer' => 'Gebaseerd op beschikbare attribution data',
            'analytics_window' => AnalyticsDataWindow::forTable('analytics_top_pages'),
            'rows' => $result,
        ];
    }

    public function weeklyGrowthReport(): array
    {
        $activation = $this->activationFunnel();
        $stats = $activation['stats'];
        $conversions = collect($activation['conversions']);

        $largestDropOff = $conversions
            ->filter(fn (array $conversion) => $conversion['percentage'] !== null)
            ->sortBy('percentage')
            ->first();

        $attentionPoint = $largestDropOff
            ? 'Grootste drop-off: '.$largestDropOff['label'].' ('.number_format((float) $largestDropOff['percentage'], 1, ',', '.').'%).'
            : 'Nog onvoldoende data om een duidelijk aandachtspunt te bepalen.';

        $interpretation = [
            'largest_drop_off' => $largestDropOff
                ? $largestDropOff['label']
                : 'Nog onvoldoende data',
            'attention_point' => $attentionPoint,
            'summary' => $stats['users_with_maintenance'] === 0
                ? 'De activatie stopt nog voor de eerste onderhoudslog. Focus op het sneller vastleggen van het eerste onderhoud.'
                : (($stats['users_with_active_reminder'] ?? 0) === 0
                    ? 'Er zijn wel onderhoudslogs, maar reminders worden nog nauwelijks geactiveerd.'
                    : 'De basisactivatie loopt. Kijk vooral of remindergebruik en onderhoudsboekje-downloads blijven meegroeien.'),
        ];

        return [
            'stats' => [
                'total_users' => $stats['total_users'],
                'registrations_last_7_days' => $stats['registrations_last_7_days'],
                'registrations_last_30_days' => $stats['registrations_last_30_days'],
                'users_with_vehicle' => $stats['users_with_vehicle'],
                'users_with_maintenance' => $stats['users_with_maintenance'],
                'users_with_active_reminder' => $stats['users_with_active_reminder'],
                'users_with_booklet_download' => $stats['users_with_booklet_download'],
                'public_vehicles' => $stats['public_vehicles'],
                'active_last_7_days' => $stats['active_last_7_days'],
                'active_last_30_days' => $stats['active_last_30_days'],
            ],
            'conversions' => $conversions->all(),
            'interpretation' => $interpretation,
        ];
    }

    public function activationFunnel(): array
    {
        $today = Carbon::today();
        $sevenDayStart = $today->copy()->subDays(6)->startOfDay();
        $thirtyDayStart = $today->copy()->subDays(29)->startOfDay();
        $hasVehicles = $this->hasTable('vehicles');
        $hasMaintenanceLogs = $this->hasTable('maintenance_logs');
        $hasVehicleDocuments = $this->hasTable('vehicle_documents');
        $hasFuelLogs = $this->hasTable('fuel_logs');
        $hasFirstLoginAt = $this->hasColumn('users', 'first_login_at');
        $hasLastLoginAt = $this->hasColumn('users', 'last_login_at');
        $hasBookletDownloads = $this->hasColumn('users', 'first_booklet_downloaded_at');

        $stats = [
            'total_users' => User::query()->count(),
            'registrations_last_7_days' => User::query()->where('created_at', '>=', $sevenDayStart)->count(),
            'registrations_last_30_days' => User::query()->where('created_at', '>=', $thirtyDayStart)->count(),
            'users_with_vehicle' => $hasVehicles ? User::query()->whereHas('vehicles')->count() : null,
            'users_with_maintenance' => $hasVehicles && $hasMaintenanceLogs ? User::query()->whereHas('vehicles.maintenanceLogs')->count() : null,
            'users_with_three_maintenance' => $hasVehicles && $hasMaintenanceLogs ? $this->usersWithMinimumMaintenanceLogs(3) : null,
            'users_with_documents' => $hasVehicles && $hasVehicleDocuments ? User::query()->whereHas('vehicles.documents')->count() : null,
            'users_with_fuel_entries' => $hasVehicles && $hasFuelLogs ? User::query()->whereHas('vehicles.fuelLogs')->count() : null,
            'users_with_active_reminder' => $hasVehicles && $hasMaintenanceLogs ? $this->usersWithActiveReminder() : null,
            'users_with_booklet_download' => $hasBookletDownloads ? User::query()->whereNotNull('first_booklet_downloaded_at')->count() : null,
            'public_vehicles' => $hasVehicles && $this->hasColumn('vehicles', 'is_public') ? Vehicle::query()->where('is_public', true)->count() : null,
            'active_last_7_days' => $hasLastLoginAt ? User::query()->where('last_login_at', '>=', Carbon::now()->subDays(7))->count() : null,
            'active_last_30_days' => $hasLastLoginAt ? User::query()->where('last_login_at', '>=', Carbon::now()->subDays(30))->count() : null,
        ];

        $totalUsers = max(1, $stats['total_users']);
        $returnedAfterSevenDays = null;

        if ($hasFirstLoginAt && $hasLastLoginAt) {
            if (DB::getDriverName() === 'sqlite') {
                $returnedAfterSevenDays = User::query()
                    ->whereNotNull('first_login_at')
                    ->whereNotNull('last_login_at')
                    ->whereColumn('last_login_at', '>', DB::raw("datetime(first_login_at, '+7 days')"))
                    ->count();
            } else {
                $returnedAfterSevenDays = User::query()
                    ->whereNotNull('first_login_at')
                    ->whereNotNull('last_login_at')
                    ->get(['first_login_at', 'last_login_at'])
                    ->filter(fn (User $user) => $user->last_login_at?->gte($user->first_login_at?->copy()->addDays(7)))
                    ->count();
            }
        }

        $funnel = [
            ['step' => 'Registratie', 'count' => $stats['total_users']],
            ['step' => 'Voertuig toegevoegd', 'count' => $stats['users_with_vehicle']],
            ['step' => 'Eerste onderhoudslog', 'count' => $stats['users_with_maintenance']],
            ['step' => 'Reminder actief', 'count' => $stats['users_with_active_reminder']],
            ['step' => 'Onderhoudsboekje gedownload', 'count' => $stats['users_with_booklet_download']],
            ['step' => 'Teruggekomen na 7 dagen', 'count' => $returnedAfterSevenDays],
        ];

        return [
            'stats' => $stats,
            'funnel' => array_map(fn (array $row) => [
                ...$row,
                'percentage' => $stats['total_users'] > 0 && $row['count'] !== null
                    ? round(($row['count'] / $totalUsers) * 100, 1)
                    : null,
            ], $funnel),
            'conversions' => [
                $this->buildConversion('Registratie → voertuig', $stats['total_users'], $stats['users_with_vehicle']),
                $this->buildConversion('Voertuig → eerste onderhoudslog', $stats['users_with_vehicle'], $stats['users_with_maintenance']),
                $this->buildConversion('Eerste onderhoudslog → reminder actief', $stats['users_with_maintenance'], $stats['users_with_active_reminder']),
                $this->buildConversion('Eerste onderhoudslog → onderhoudsboekje download', $stats['users_with_maintenance'], $this->usersWithMaintenanceAndBookletDownload($hasBookletDownloads, $hasVehicles, $hasMaintenanceLogs)),
            ],
        ];
    }

    public function recentActivity(): array
    {
        $registrationSources = $this->registrationAttributionRecords()
            ->mapWithKeys(fn (array $row) => [$row['id'] => $this->sourceLabel($row)]);

        $registrations = User::query()
            ->select(['id', 'name', 'created_at'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (User $user) => [
                'label' => trim(($user->name ?: 'Gebruiker').' (#'.$user->id.')'),
                'timestamp' => $user->created_at?->format('d-m-Y H:i') ?? '—',
                'source' => $registrationSources->get($user->id, '—'),
            ])
            ->all();

        $vehicles = [];

        if ($this->hasTable('vehicles')) {
            $vehicles = Vehicle::query()
                ->join('users', 'users.id', '=', 'vehicles.user_id')
                ->select([
                    'vehicles.id',
                    'vehicles.created_at',
                    'vehicles.brand',
                    'vehicles.model',
                    'vehicles.nickname',
                    'users.id as user_id',
                    'users.name as user_name',
                ])
                ->latest('vehicles.created_at')
                ->limit(5)
                ->get()
                ->map(fn ($vehicle) => [
                    'label' => trim(($vehicle->nickname ?: trim($vehicle->brand.' '.$vehicle->model) ?: 'Voertuig').' door '.($vehicle->user_name ?: 'user').' (#'.$vehicle->user_id.')'),
                    'timestamp' => Carbon::parse($vehicle->created_at)->format('d-m-Y H:i'),
                    'source' => $registrationSources->get($vehicle->user_id, '—'),
                ])
                ->all();
        }

        $maintenanceLogs = [];

        if ($this->hasTable('maintenance_logs') && $this->hasTable('vehicles')) {
            $maintenanceLogs = MaintenanceLog::query()
                ->join('vehicles', 'vehicles.id', '=', 'maintenance_logs.vehicle_id')
                ->join('users', 'users.id', '=', 'vehicles.user_id')
                ->select([
                    'maintenance_logs.id',
                    'maintenance_logs.created_at',
                    'maintenance_logs.description',
                    'users.id as user_id',
                    'users.name as user_name',
                ])
                ->latest('maintenance_logs.created_at')
                ->limit(5)
                ->get()
                ->map(fn ($log) => [
                    'label' => trim(($log->description ?: 'Onderhoudslog').' door '.($log->user_name ?: 'user').' (#'.$log->user_id.')'),
                    'timestamp' => Carbon::parse($log->created_at)->format('d-m-Y H:i'),
                    'source' => $registrationSources->get($log->user_id, '—'),
                ])
                ->all();
        }

        return [
            'registrations' => $registrations,
            'vehicles' => $vehicles,
            'maintenance_logs' => $maintenanceLogs,
        ];
    }

    private function visitorCounts(Carbon $today, Carbon $sevenDayStart, Carbon $thirtyDayStart): array
    {
        $window = AnalyticsDataWindow::forTable('analytics_daily_summaries');

        if (! $window['has_data']) {
            return [
                'today' => null,
                'seven_days' => null,
                'thirty_days' => null,
                'window' => $window,
            ];
        }

        $endDate = Carbon::parse($window['end_at']);
        $sevenDayStart = $endDate->copy()->subDays(6)->toDateString();
        $thirtyDayStart = $endDate->copy()->subDays(29)->toDateString();

        return [
            'today' => (int) (AnalyticsDailySummary::query()
                ->where('date', '>=', $window['end_date'].' 00:00:00')
                ->where('date', '<=', $window['end_at'])
                ->sum('users')),
            'seven_days' => (int) (AnalyticsDailySummary::query()
                ->where('date', '>=', $sevenDayStart)
                ->where('date', '<=', $window['end_at'])
                ->sum('users')),
            'thirty_days' => (int) (AnalyticsDailySummary::query()
                ->where('date', '>=', $thirtyDayStart)
                ->where('date', '<=', $window['end_at'])
                ->sum('users')),
            'window' => $window,
        ];
    }

    private function registrationAttributionRecords(): Collection
    {
        $query = User::query()
            ->select([
                'users.id',
                'users.created_at',
            ]);

        if ($this->hasColumn('users', 'registration_source')) {
            $query->addSelect('users.registration_source');
        }

        if ($this->hasTable('user_attributions')) {
            $query->leftJoin('user_attributions as ua', 'ua.user_id', '=', 'users.id');

            foreach (['source', 'campaign_slug', 'partner_slug', 'utm_source', 'utm_medium', 'utm_campaign', 'landing_page', 'referrer'] as $column) {
                if ($this->hasColumn('user_attributions', $column)) {
                    $query->addSelect('ua.'.$column);
                }
            }
        }

        return $query
            ->orderByDesc('users.created_at')
            ->get()
            ->map(function ($row): array {
                return [
                    'id' => (int) $row->id,
                    'created_at' => Carbon::parse($row->created_at),
                    'registration_source' => $row->registration_source ?? null,
                    'source' => $row->source ?? null,
                    'campaign_slug' => $row->campaign_slug ?? null,
                    'partner_slug' => $row->partner_slug ?? null,
                    'utm_source' => $row->utm_source ?? null,
                    'utm_medium' => $row->utm_medium ?? null,
                    'utm_campaign' => $row->utm_campaign ?? null,
                    'landing_page' => $row->landing_page ?? null,
                    'referrer' => $row->referrer ?? null,
                    'referrer_host' => $this->referrerHost($row->referrer ?? null),
                ];
            });
    }

    private function attributedRegistrationRecords(): Collection
    {
        return $this->registrationAttributionRecords()
            ->filter(fn (array $row) => filled($row['utm_source'])
                || filled($row['utm_medium'])
                || filled($row['utm_campaign'])
                || filled($row['source'])
                || filled($row['campaign_slug'])
                || filled($row['partner_slug'])
                || filled($row['registration_source'])
                || filled($row['referrer_host']));
    }

    private function analyticsTopPageUsersByPath(): array
    {
        if (! $this->hasTable('analytics_top_pages')) {
            return [];
        }

        $window = AnalyticsDataWindow::forTable('analytics_top_pages');

        if (! $window['has_data']) {
            return [];
        }

        return AnalyticsTopPage::query()
            ->where('date', '>=', $window['start_at'])
            ->where('date', '<=', $window['end_at'])
            ->selectRaw('page_path')
            ->selectRaw('SUM(users) as users')
            ->groupBy('page_path')
            ->pluck('users', 'page_path')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function searchConsoleQueryRows(string $sortBy, ?callable $constraint = null): array
    {
        if (! $this->hasTable('search_console_queries')) {
            return [];
        }

        $window = AnalyticsDataWindow::forTable('search_console_queries');

        if (! $window['has_data']) {
            return [];
        }

        $query = SearchConsoleQuery::query()
            ->where('date', '>=', $window['start_at'])
            ->where('date', '<=', $window['end_at'])
            ->selectRaw('MIN(id) as id')
            ->selectRaw('query')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('AVG(ctr) as ctr')
            ->selectRaw('AVG(position) as position')
            ->groupBy('query');

        if ($constraint !== null) {
            $constraint($query);
        }

        if ($sortBy === 'position') {
            $query->orderBy('position');
        } else {
            $query->orderByDesc($sortBy);
        }

        return $query
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->query,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'ctr' => $row->ctr !== null ? round((float) $row->ctr * 100, 2) : null,
                'position' => $row->position !== null ? round((float) $row->position, 2) : null,
            ])
            ->all();
    }

    private function searchConsolePageRows(string $sortBy, ?callable $constraint = null): array
    {
        if (! $this->hasTable('search_console_pages')) {
            return [];
        }

        $window = AnalyticsDataWindow::forTable('search_console_pages');

        if (! $window['has_data']) {
            return [];
        }

        $query = SearchConsolePage::query()
            ->where('date', '>=', $window['start_at'])
            ->where('date', '<=', $window['end_at'])
            ->selectRaw('MIN(id) as id')
            ->selectRaw('page')
            ->selectRaw('SUM(clicks) as clicks')
            ->selectRaw('SUM(impressions) as impressions')
            ->selectRaw('AVG(ctr) as ctr')
            ->selectRaw('AVG(position) as position')
            ->groupBy('page');

        if ($constraint !== null) {
            $constraint($query);
        }

        $query->orderByDesc($sortBy);

        return $query
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->page,
                'clicks' => (int) $row->clicks,
                'impressions' => (int) $row->impressions,
                'ctr' => $row->ctr !== null ? round((float) $row->ctr * 100, 2) : null,
                'position' => $row->position !== null ? round((float) $row->position, 2) : null,
            ])
            ->all();
    }

    private function usersWithMinimumMaintenanceLogs(int $minimumLogs): int
    {
        return DB::query()
            ->fromSub(function ($query) use ($minimumLogs): void {
                $query->select('vehicles.user_id')
                    ->from('vehicles')
                    ->join('maintenance_logs', 'maintenance_logs.vehicle_id', '=', 'vehicles.id')
                    ->groupBy('vehicles.user_id')
                    ->havingRaw('COUNT(maintenance_logs.id) >= ?', [$minimumLogs]);
            }, 'qualified_users')
            ->count();
    }

    private function usersWithActiveReminder(): int
    {
        return User::query()
            ->whereHas('vehicles.maintenanceLogs', function ($query) {
                $query->where('reminder_enabled', true)
                    ->where(function ($inner) {
                        $inner->whereNotNull('interval_months')
                            ->orWhereNotNull('interval_km');
                    });
            })
            ->count();
    }

    private function usersWithMaintenanceAndBookletDownload(bool $hasBookletDownloads, bool $hasVehicles, bool $hasMaintenanceLogs): ?int
    {
        if (! $hasBookletDownloads || ! $hasVehicles || ! $hasMaintenanceLogs) {
            return null;
        }

        return User::query()
            ->whereNotNull('first_booklet_downloaded_at')
            ->whereHas('vehicles.maintenanceLogs')
            ->count();
    }

    private function userIdsWithVehicles(): Collection
    {
        if (! $this->hasTable('vehicles')) {
            return collect();
        }

        return Vehicle::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
    }

    private function userIdsWithMaintenanceLogs(): Collection
    {
        if (! $this->hasTable('vehicles') || ! $this->hasTable('maintenance_logs')) {
            return collect();
        }

        return MaintenanceLog::query()
            ->join('vehicles', 'vehicles.id', '=', 'maintenance_logs.vehicle_id')
            ->whereNotNull('vehicles.user_id')
            ->distinct()
            ->pluck('vehicles.user_id')
            ->map(fn ($id) => (int) $id)
            ->flip();
    }

    private function buildConversion(string $label, ?int $from, ?int $to): array
    {
        return [
            'label' => $label,
            'from' => $from,
            'to' => $to,
            'percentage' => $from && $to !== null
                ? round(($to / max(1, $from)) * 100, 1)
                : null,
        ];
    }

    private function sourceLabel(array $row): string
    {
        if (filled($row['utm_source'])) {
            return (string) $row['utm_source'];
        }

        if (filled($row['source'])) {
            return (string) $row['source'];
        }

        if (filled($row['partner_slug'])) {
            return (string) $row['partner_slug'];
        }

        if (filled($row['registration_source'])) {
            return (string) $row['registration_source'];
        }

        if (filled($row['referrer_host'])) {
            return (string) $row['referrer_host'];
        }

        return 'direct/unknown';
    }

    private function activationSourceLabel(array $row): string
    {
        if (filled($row['registration_source'])) {
            return (string) $row['registration_source'];
        }

        if (filled($row['source'])) {
            return (string) $row['source'];
        }

        if (filled($row['partner_slug'])) {
            return (string) $row['partner_slug'];
        }

        if (filled($row['utm_source'])) {
            return (string) $row['utm_source'];
        }

        if (filled($row['referrer_host'])) {
            return (string) $row['referrer_host'];
        }

        return 'direct';
    }

    private function compactValues(array $values): string
    {
        $labels = array_keys($values);
        sort($labels);

        return $labels === [] ? '—' : implode(', ', array_slice($labels, 0, 3));
    }

    private function mediumLabel(array $row): string
    {
        if (filled($row['utm_medium'])) {
            return (string) $row['utm_medium'];
        }

        if (filled($row['registration_source'])) {
            return 'partner';
        }

        if (filled($row['referrer_host'])) {
            return 'referral';
        }

        return '—';
    }

    private function campaignLabel(array $row): string
    {
        if (filled($row['campaign_slug'])) {
            return (string) $row['campaign_slug'];
        }

        return filled($row['utm_campaign']) ? (string) $row['utm_campaign'] : '—';
    }

    private function matchesPartner(string $partner, array $row): bool
    {
        $needles = array_filter([
            mb_strtolower((string) ($row['utm_source'] ?? '')),
            mb_strtolower((string) ($row['utm_campaign'] ?? '')),
            mb_strtolower((string) ($row['source'] ?? '')),
            mb_strtolower((string) ($row['campaign_slug'] ?? '')),
            mb_strtolower((string) ($row['partner_slug'] ?? '')),
            mb_strtolower((string) ($row['registration_source'] ?? '')),
            mb_strtolower((string) ($row['referrer_host'] ?? '')),
        ]);

        foreach ($needles as $needle) {
            if (str_contains($needle, mb_strtolower($partner))) {
                return true;
            }
        }

        return false;
    }

    private function matchesCampaign(string $campaignSlug, array $row): bool
    {
        if (filled($row['campaign_slug']) && mb_strtolower((string) $row['campaign_slug']) === mb_strtolower($campaignSlug)) {
            return true;
        }

        return filled($row['utm_campaign']) && mb_strtolower((string) $row['utm_campaign']) === mb_strtolower($campaignSlug);
    }

    private function referrerHost(?string $referrer): ?string
    {
        if (! filled($referrer)) {
            return null;
        }

        $host = parse_url($referrer, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : null;
    }

    private function hasTable(string $table): bool
    {
        if (! array_key_exists($table, $this->tablePresence)) {
            $this->tablePresence[$table] = Schema::hasTable($table);
        }

        return $this->tablePresence[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;

        if (! array_key_exists($key, $this->columnPresence)) {
            $this->columnPresence[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $this->columnPresence[$key];
    }
}

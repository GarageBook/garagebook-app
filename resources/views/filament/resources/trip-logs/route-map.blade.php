@php
    /** @var \App\Models\TripLog $record */
    $record = $getRecord();
    $mapId = 'trip-route-map-' . $record->getKey();
    $routeGeojson = $record->simplified_geojson ? json_decode($record->simplified_geojson, true) : null;
    $bounds = $record->bounds;
@endphp

<div style="display:flex; flex-direction:column; gap:12px;">
    @if ($record->status === \App\Models\TripLog::STATUS_PROCESSED && $routeGeojson)
        <div
            x-data="tripRouteMap({
                mapId: @js($mapId),
                geojson: @js($routeGeojson),
                bounds: @js($bounds),
                tileUrl: @js(config('maps.tile_url')),
                attribution: @js(config('maps.tile_attribution')),
            })"
            x-init="init()"
            style="display:flex; flex-direction:column; gap:8px;"
        >
            <div id="{{ $mapId }}" style="height: 420px; border-radius: 18px; overflow: hidden; border: 1px solid #dbe1ea;"></div>
        </div>
    @elseif ($record->status === \App\Models\TripLog::STATUS_FAILED)
        <div style="padding:16px 18px; border-radius:14px; background:#fff7ed; border:1px solid #fdba74; color:#9a3412;">
            {{ $record->failure_reason ?: __('trips.map.failed') }}
        </div>
    @else
        <div style="padding:16px 18px; border-radius:14px; background:#f8fafc; border:1px solid #dbe1ea; color:#475569;">
            {{ __('trips.map.pending') }}
        </div>
    @endif
</div>

<?php

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/share/{username}/{vehicleSlug}', function ($username, $vehicleSlug) {
    $user = User::whereRaw('LOWER(name) = ?', [strtolower($username)])->firstOrFail();

    $vehicle = Vehicle::where('user_id', $user->id)
        ->get()
        ->first(function ($vehicle) use ($vehicleSlug) {
            $slug = Str::slug(
                $vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model
            );

            return $slug === $vehicleSlug;
        });

    abort_if(!$vehicle, 404);

    $logs = MaintenanceLog::where('vehicle_id', $vehicle->id)
        ->latest('maintenance_date')
        ->get();

    return view('maintenance-share', compact('logs', 'vehicle', 'user'));
});

Route::get('/maintenance/pdf', function () {
    $vehicle = Vehicle::where('user_id', auth()->id())
        ->latest()
        ->firstOrFail();

    $logs = MaintenanceLog::where('vehicle_id', $vehicle->id)
        ->latest('maintenance_date')
        ->get();

    $pdf = Pdf::loadView('maintenance-share', compact('logs', 'vehicle'));

    return $pdf->download(
        Str::slug($vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model)
        . '-onderhoud.pdf'
    );
});
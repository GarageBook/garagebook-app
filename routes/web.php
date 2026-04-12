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
    $users = User::all();

    $user = $users->first(function ($user) use ($username) {
        return Str::slug(trim($user->name)) === trim($username);
    });

    if (! $user) {
        abort(404, 'User not found');
    }

    $vehicles = Vehicle::where('user_id', $user->id)->get();

    $vehicle = $vehicles->first(function ($vehicle) use ($vehicleSlug) {
        $slug = Str::slug(
            trim($vehicle->nickname ?: $vehicle->brand . ' ' . $vehicle->model)
        );

        return $slug === trim($vehicleSlug);
    });

    if (! $vehicle) {
        abort(404, 'Vehicle not found');
    }

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
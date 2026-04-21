<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class MyVehicles extends Widget
{
    protected string $view = 'filament.widgets.my-vehicles';

    protected int | string | array $columnSpan = 1;

    public function getViewData(): array
    {
        $user = auth()->user();

        return [
            'vehicles' => $user->vehicles()->latest()->get(),
        ];
    }
}
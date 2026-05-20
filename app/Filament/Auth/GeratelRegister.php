<?php

namespace App\Filament\Auth;

class GeratelRegister extends Register
{
    protected string $view = 'filament.auth.geratel-register';

    public function hasLogo(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data = parent::mutateFormDataBeforeRegister($data);
        $data['registration_source'] = 'geratel';

        return $data;
    }

    public function getSubheading(): string | \Illuminate\Contracts\Support\Htmlable | null
    {
        return null;
    }

}

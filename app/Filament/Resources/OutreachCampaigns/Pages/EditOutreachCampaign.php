<?php

namespace App\Filament\Resources\OutreachCampaigns\Pages;

use App\Filament\Resources\OutreachCampaigns\OutreachCampaignResource;
use App\Services\Outreach\OutreachEmailService;
use App\Support\Outreach\OutreachQuota;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;

class EditOutreachCampaign extends EditRecord
{
    protected static string $resource = OutreachCampaignResource::class;

    public function getHeader(): ?View
    {
        return view('filament.resources.outreach-campaigns.pages.header', [
            'actions' => $this->getCachedHeaderActions(),
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'quotaBanner' => app(OutreachQuota::class)->banner(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestMail')
                ->label('Verstuur testmail')
                ->requiresConfirmation()
                ->modalDescription('Testmail wordt verzonden naar: willemvanveelen@icloud.com. Afzender: '.config('mail.from.address').'. Antwoorden gaan naar: social@garagebook.nl')
                ->action(function (OutreachEmailService $service): void {
                    $prospect = $this->getRecord()->prospects()->orderBy('id')->first();

                    if (! $prospect) {
                        Notification::make()->title('Geen prospect beschikbaar voor testmail.')->danger()->send();

                        return;
                    }

                    $service->sendTestMail($this->getRecord(), $prospect);

                    Notification::make()->title('Testmail verstuurd.')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}

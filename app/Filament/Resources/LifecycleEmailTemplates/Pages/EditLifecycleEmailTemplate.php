<?php

namespace App\Filament\Resources\LifecycleEmailTemplates\Pages;

use App\Filament\Resources\LifecycleEmailTemplates\LifecycleEmailTemplateResource;
use App\Models\LifecycleEmailTemplate;
use App\Models\User;
use App\Services\LifecycleEmailService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use Throwable;

class EditLifecycleEmailTemplate extends EditRecord
{
    protected static string $resource = LifecycleEmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->modalHeading('Lifecycle e-mail preview')
                ->modalDescription('Preview gebruikt je eigen adminaccount als context voor naam, CTA en unsubscribe-link.')
                ->modalWidth(Width::FiveExtraLarge)
                ->modalSubmitAction(false)
                ->modalContent(fn (LifecycleEmailService $service) => view('filament.resources.lifecycle-email-templates.preview', $service->buildPreviewData($this->previewUser(), $this->previewTemplate()))),
            Action::make('sendTestMail')
                ->label('Testmail naar mezelf')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Deze testmail gebruikt je eigen adminaccount als context en verzendt naar je eigen e-mailadres.')
                ->action(function (LifecycleEmailService $service): void {
                    $previewUser = $this->previewUser();
                    $template = $this->previewTemplate();

                    Log::info('lifecycle_test_mail_action_start', [
                        'user_id' => $previewUser->getKey(),
                        'template_email_key' => $template->email_key,
                        'email' => $previewUser->email,
                    ]);

                    try {
                        $log = $service->sendTestMail($previewUser, $template);
                    } catch (Throwable $exception) {
                        Log::error('lifecycle_test_mail_action_exception', [
                            'user_id' => $previewUser->getKey(),
                            'template_email_key' => $template->email_key,
                            'message' => $exception->getMessage(),
                            'exception_class' => $exception::class,
                        ]);

                        Notification::make()
                            ->danger()
                            ->title('Testmail mislukt')
                            ->body($exception->getMessage())
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Testmail verzonden')
                        ->body('Verzonden naar ' . $previewUser->email . '. Log-ID: ' . $log->getKey() . '.')
                        ->send();
                }),
        ];
    }

    protected function previewTemplate(): LifecycleEmailTemplate
    {
        $template = $this->getRecord()->replicate();

        $template->forceFill([
            'email_key' => $this->getRecord()->email_key,
            'name' => $this->data['name'] ?? $this->getRecord()->name,
            'subject' => $this->data['subject'] ?? $this->getRecord()->subject,
            'body' => $this->data['body'] ?? $this->getRecord()->body,
            'cta_text' => $this->data['cta_text'] ?? $this->getRecord()->cta_text,
            'is_active' => (bool) ($this->data['is_active'] ?? $this->getRecord()->is_active),
        ]);

        return $template;
    }

    protected function previewUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}

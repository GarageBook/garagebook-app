<?php

namespace App\Filament\Resources\Blogs\Pages;

use App\Filament\Resources\Blogs\BlogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditBlog extends EditRecord
{
    protected static string $resource = BlogResource::class;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (Throwable $exception) {
            $this->logContentDiagnostics(
                event: 'blog_edit_save_failed',
                content: $this->data['content'] ?? null,
                exception: $exception,
            );

            throw $exception;
        }
    }

    protected function beforeValidate(): void
    {
        $content = $this->data['content'] ?? null;

        if (! $this->hasMalformedTipTapContent($content)) {
            return;
        }

        $this->logContentDiagnostics(
            event: 'blog_edit_invalid_content',
            content: $content,
        );

        throw ValidationException::withMessages([
            'data.content' => 'De bloginhoud heeft een ongeldig rich-text formaat. Vernieuw de editor en probeer het opnieuw.',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->logContentDiagnostics(
            event: 'blog_edit_dehydrated_content',
            content: $data['content'] ?? null,
        );

        return $data;
    }

    protected function hasMalformedTipTapContent(mixed $content): bool
    {
        return is_array($content)
            && (($content['type'] ?? null) === 'doc')
            && array_key_exists('content', $content)
            && (! is_array($content['content']));
    }

    protected function logContentDiagnostics(string $event, mixed $content, ?Throwable $exception = null): void
    {
        $context = [
            'page' => static::class,
            'resource' => static::getResource(),
            'route' => request()?->route()?->getName(),
            'content' => $this->summarizeContent($content),
        ];

        if ($exception) {
            $context['exception'] = [
                'class' => $exception::class,
                'message' => $exception->getMessage(),
            ];
        }

        Log::info($event, $context);
    }

    protected function summarizeContent(mixed $content): array
    {
        $summary = [
            'type' => get_debug_type($content),
        ];

        if (is_string($content)) {
            $summary['length'] = mb_strlen($content);

            return $summary;
        }

        if (! is_array($content)) {
            return $summary;
        }

        $summary['keys'] = array_values(array_map('strval', array_slice(array_keys($content), 0, 8)));
        $summary['node_type'] = is_string($content['type'] ?? null) ? $content['type'] : null;

        $nodes = $content['content'] ?? null;

        if (is_array($nodes)) {
            $summary['node_count'] = count($nodes);
            $summary['first_node_types'] = array_values(array_filter(array_map(
                fn (mixed $node): ?string => is_array($node) && is_string($node['type'] ?? null) ? $node['type'] : null,
                array_slice($nodes, 0, 5),
            )));
        } else {
            $summary['node_count'] = null;
            $summary['content_field_type'] = get_debug_type($nodes);
        }

        return $summary;
    }
}

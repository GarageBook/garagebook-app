<?php

namespace App\Console\Commands;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Console\Command;

class DebugAdminNavigationCommand extends Command
{
    protected $signature = 'garagebook:debug-admin-navigation {--email=willemvanveelen@icloud.com}';

    protected $description = 'Dump the runtime Filament admin navigation for debugging.';

    public function handle(): int
    {
        $user = User::query()
            ->where('email', (string) $this->option('email'))
            ->first();

        if (! $user) {
            $this->error('Admin user not found: '.$this->option('email'));

            return self::FAILURE;
        }

        auth()->login($user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $sourceIndex = $this->sourceIndex();
        $seoHealthItems = [];

        foreach (Filament::getNavigation() as $group) {
            foreach ($group->getItems() as $item) {
                $row = [
                    'label' => $item->getLabel(),
                    'url' => $item->getUrl(),
                    'group' => $group->getLabel(),
                    'sort' => $item->getSort(),
                    'source' => $sourceIndex[$this->key($item)] ?? null,
                    'item_class' => get_class($item),
                ];

                if ($row['label'] === 'SEO Health') {
                    $seoHealthItems[] = $row;
                }

                $this->line(json_encode($row, JSON_UNESCAPED_SLASHES));
            }
        }

        $this->line('SEO_HEALTH_ITEMS='.json_encode($seoHealthItems, JSON_UNESCAPED_SLASHES));
        $this->line('SEO_HEALTH_COUNT='.count($seoHealthItems));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function sourceIndex(): array
    {
        $panel = Filament::getPanel('admin');
        $sources = [];

        foreach ($panel->getPages() as $page) {
            foreach ($page::getNavigationItems() as $item) {
                $sources[$this->key($item)] = 'page: '.$page;
            }
        }

        foreach ($panel->getResources() as $resource) {
            foreach ($resource::getNavigationItems() as $item) {
                $sources[$this->key($item)] = 'resource: '.$resource;
            }
        }

        return $sources;
    }

    private function key(NavigationItem $item): string
    {
        return implode('|', [
            $item->getLabel(),
            $item->getUrl(),
            (string) $item->getGroup(),
            (string) $item->getSort(),
        ]);
    }
}

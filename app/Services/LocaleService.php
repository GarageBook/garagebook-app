<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class LocaleService
{
    public function all(): array
    {
        return config('locales.locales', []);
    }

    public function codes(): array
    {
        return array_keys($this->all());
    }

    public function default(): string
    {
        foreach ($this->all() as $locale => $config) {
            if (($config['default'] ?? false) === true) {
                return $locale;
            }
        }

        return config('app.locale', 'nl');
    }

    public function fallback(): string
    {
        return config('locales.fallback_locale', config('app.fallback_locale', 'en'));
    }

    public function current(?string $preferredLocale = null): string
    {
        if ($preferredLocale && $this->isEnabled($preferredLocale)) {
            return $preferredLocale;
        }

        $appLocale = app()->getLocale();

        if ($appLocale && array_key_exists($appLocale, $this->all())) {
            return $appLocale;
        }

        return $this->default();
    }

    public function isEnabled(string $locale): bool
    {
        return (bool) ($this->all()[$locale]['enabled'] ?? false);
    }

    public function translationFiles(): array
    {
        $files = [];

        foreach ($this->codes() as $locale) {
            $directory = lang_path($locale);

            if (! File::isDirectory($directory)) {
                continue;
            }

            foreach (File::files($directory) as $file) {
                $files[] = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    public function translationCatalog(?string $file = null): array
    {
        $file ??= $this->translationFiles()[0] ?? null;

        if (! $file) {
            return [];
        }

        $catalog = [];

        foreach ($this->codes() as $locale) {
            foreach ($this->translationsForFile($locale, $file) as $key => $value) {
                $catalog[$key] ??= ['key' => $key, 'values' => []];
                $catalog[$key]['values'][$locale] = $value;
            }
        }

        ksort($catalog);

        foreach ($catalog as &$row) {
            foreach ($this->codes() as $locale) {
                $row['values'][$locale] = $row['values'][$locale] ?? null;
            }
        }

        return array_values($catalog);
    }

    public function localeSummaries(): array
    {
        $summaries = [];

        foreach ($this->all() as $locale => $config) {
            $flattened = [];

            foreach ($this->translationFiles() as $file) {
                $flattened += $this->translationsForFile($locale, $file);
            }

            $summaries[] = [
                'code' => $locale,
                'native_name' => $config['native_name'] ?? $locale,
                'enabled' => (bool) ($config['enabled'] ?? false),
                'default' => (bool) ($config['default'] ?? false),
                'fallback_locale' => $config['fallback_locale'] ?? $this->fallback(),
                'translation_count' => count($flattened),
            ];
        }

        return $summaries;
    }

    public function firstAvailableFile(): ?string
    {
        return $this->translationFiles()[0] ?? null;
    }

    /**
     * @return array<string, scalar|null>
     */
    public function translationsForFile(string $locale, string $file): array
    {
        $path = lang_path($locale . DIRECTORY_SEPARATOR . $file . '.php');

        if (! File::exists($path)) {
            return [];
        }

        $translations = require $path;

        if (! is_array($translations)) {
            return [];
        }

        return $this->flattenTranslations($translations, $file);
    }

    /**
     * @param  array<string, mixed>  $translations
     * @return array<string, scalar|null>
     */
    protected function flattenTranslations(array $translations, string $prefix): array
    {
        $flattened = [];

        foreach ($translations as $key => $value) {
            $fullKey = $prefix . '.' . $key;

            if (is_array($value)) {
                $flattened += $this->flattenTranslations($value, $fullKey);

                continue;
            }

            $flattened[$fullKey] = is_scalar($value) || $value === null
                ? $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        ksort($flattened);

        return $flattened;
    }
}

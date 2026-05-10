# Localization Architecture

Last updated: 2026-05-10

## Summary

GarageBook uses Laravel's file-based localization system with per-locale PHP language files under `lang/{locale}`. The implementation is intentionally incremental: Dutch stays primary, no route structure changes were introduced, and public locale selection is not enabled yet.

Reference: Laravel 13 localization documentation  
https://laravel.com/docs/13.x/localization

## Locale Structure

The application currently uses:

- `lang/nl`
- `lang/en`
- `lang/de`
- `lang/fr`

Current project language files include:

- `app.php`
- `auth.php`
- `dashboard.php`
- `documents.php`
- `emails.php`
- `fuel.php`
- `maintenance.php`
- `notifications.php`
- `reminders.php`
- `validation.php`
- `vehicles.php`

Laravel-published framework files are also present where needed, such as `validation.php`, `auth.php`, `pagination.php`, and `passwords.php`.

## Translation Access Pattern

The codebase uses Laravel translation helpers with short keys, for example:

- `__('dashboard.timeline_heading')`
- `__('vehicles.fields.brand')`
- `__('maintenance.table.cost')`
- `trans_choice('dashboard.timeline.images_count', $count, ['count' => $count])`

This follows Laravel's standard keyed file approach rather than JSON translation files.

## Core Configuration

### `config/app.php`

Application-level locale settings:

- `locale = nl`
- `fallback_locale = en`

### `config/locales.php`

Project-specific locale metadata:

- enabled / disabled state per locale
- default locale flag
- native language name
- fallback locale metadata

This config is the canonical source for supported locales in GarageBook, separate from Laravel's base config.

## `LocaleService`

[app/Services/LocaleService.php](/home/willem/garagebook/app/Services/LocaleService.php) provides a small application-level abstraction over Laravel localization.

Responsibilities:

- return configured locale definitions
- resolve the default locale
- resolve the fallback locale
- determine the current locale for future extension points
- list available translation files dynamically from `lang/{locale}`
- flatten nested translation arrays into comparable key/value catalogs
- build per-locale summaries for admin visibility

This service is intentionally simple and does not yet perform public locale negotiation.

## Filament Localization Overview

[app/Filament/Pages/LocalizationOverview.php](/home/willem/garagebook/app/Filament/Pages/LocalizationOverview.php) is an admin-only, read-only Filament page.

It works by:

- restricting access to admins only
- reading locale metadata from `LocaleService`
- listing discovered translation files
- flattening translation arrays into rows such as `dashboard.timeline_heading`
- rendering one file at a time across all configured locales

The page is diagnostic and operational. It is not a translation editor.

## Why PHP Lang Files Instead of Database Translations

This implementation deliberately uses PHP language files because they are:

- native to Laravel
- easy to diff and review in Git
- safe for incremental rollout
- simple to validate in tests
- low-risk compared to introducing a translation manager or database-backed editing layer

For the current phase, PHP files are the smallest stable solution.

## Why Public Locale Switching Is Not Enabled Yet

Public locale switching is intentionally deferred because the current goal is technical readiness, not multilingual launch.

Reasons:

- Dutch-first product and UX remain intact
- route structure must not change yet
- not all public content domains are localized
- user preference storage and locale negotiation rules are not finalized

## Design Choices

### Incremental rollout

Localization was introduced in phases by domain to avoid broad unsafe refactors.

### No breaking route changes

Existing Dutch URLs, route names, and slugs remain unchanged.

### Dutch-first strategy

Dutch is the default locale and remains the primary operational language.

### Future-proofing

The architecture leaves room for:

- future user locale preferences
- future public locale switching
- future localized routes
- eventual translation editing workflows if needed

## Laravel Localization Fundamentals

### `php artisan lang:publish`

Laravel 13 does not include the `lang` directory by default in the application skeleton. The `lang:publish` command scaffolds the directory and publishes the default framework language files.

### Translation keys

GarageBook uses keyed translation files under `lang/{locale}`. Keys are referenced with helpers such as `__()` and typically follow `domain.section.key`.

### `trans_choice`

Pluralized strings use `trans_choice(...)` where singular/plural UI labels must stay correct, for example timeline counts for photos and files.

### Fallback locales

Laravel falls back to `config('app.fallback_locale')` when a translation key is missing in the active locale. GarageBook keeps this at `en`, while also storing the same fallback intent in `config/locales.php`.


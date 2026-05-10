# Localization Overview

Last updated: 2026-05-10

## Current Locale Setup

- Supported locales:
  - `nl`
  - `en`
  - `de`
  - `fr`
- Default locale: `nl`
- Fallback locale: `en`

## Current Status

- The application is localization-ready.
- There is no public language switcher.
- There are no localized routes such as `/en`, `/de`, or `/fr`.
- Filament admin includes a read-only localization overview page for admins.

## Localized Domains

The following domains have been prepared and localized in the current implementation:

- `dashboard`
- `vehicles`
- `maintenance`
- `documents`
- `fuel`
- `reminders`
- `mails`

## Domains Not Fully Completed

The following areas still need additional parity or broader rollout work:

- `auth` / `validation` parity across all locales
- `users` / admin resources
- public / blog / marketing content

## Important Constraints

- Dutch remains the primary operational language.
- Existing Dutch URLs and route names remain unchanged.
- Database fields, model properties, slugs, and technical identifiers are not translated.
- The current implementation prepares future internationalization without launching a public multilingual UX.

## Related Files

- [config/locales.php](/home/willem/garagebook/config/locales.php)
- [app/Services/LocaleService.php](/home/willem/garagebook/app/Services/LocaleService.php)
- [app/Filament/Pages/LocalizationOverview.php](/home/willem/garagebook/app/Filament/Pages/LocalizationOverview.php)

